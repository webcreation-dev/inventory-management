<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Sale\Models\Sale;
use Modules\Sale\Services\SaleService;
use Modules\Stock\Repositories\StockRepository;

class OnlineOrderController extends Controller
{
    /** @var list<string> */
    public const ORDER_STATUSES = [
        'Pending',
        'Confirmed',
        'Rejected',
        'On The Way',
        'Delivered',
        'Cancelled',
        'Returned',
    ];

    /** @var list<string> */
    public const FULFILLMENT_STATUSES = [
        'Confirmed',
        'On The Way',
        'Delivered',
    ];

    public function __construct(
        protected SaleService $saleService,
        protected StockRepository $stockRepository,
    ) {
    }

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return response()->json($this->dataTableData([
                'draw' => (int) (request()->draw ?? 1),
                'length' => (int) (request()->length ?? 10),
                'start' => (int) (request()->start ?? 0),
                'search' => (string) (request()->search['value'] ?? ''),
            ]));
        }

        return view('sale::online-order.index');
    }

    public function show(string $id): View|RedirectResponse
    {
        $sale = $this->findOnlineSale($id);
        if (! $sale) {
            return redirect()->route('online-order.index')
                ->with('error', __('Online order not found.'));
        }

        $orderStatus = $sale->order_status ?: 'Pending';
        $availableImei = $this->availableImeiForSale($sale);

        return view('sale::online-order.show', [
            'sale' => $sale,
            'paymentStatus' => $this->resolvePaymentStatus($sale),
            'fulfillmentStatuses' => self::FULFILLMENT_STATUSES,
            'availableImei' => $availableImei,
            'canUpdateFulfillment' => in_array($orderStatus, ['Confirmed', 'On The Way'], true),
            'isDelivered' => $orderStatus === 'Delivered',
        ]);
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            $sale = $this->findOnlineSale($id);
            if (! $sale) {
                return redirect()->route('online-order.index')
                    ->with('error', __('Online order not found.'));
            }

            $this->saleService->deleteSale($id);

            return redirect()->route('online-order.index')
                ->with('success', __('Online order deleted successfully.'));
        } catch (\Throwable $e) {
            return redirect()->route('online-order.index')
                ->with('error', $e->getMessage() ?: __('Unable to delete online order.'));
        }
    }

    public function accept(string $id): RedirectResponse
    {
        return $this->setOrderStatus($id, 'Confirmed', __('Order accepted successfully.'));
    }

    public function reject(string $id): RedirectResponse
    {
        return $this->setOrderStatus($id, 'Rejected', __('Order rejected successfully.'));
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $sale = $this->findOnlineSale($id);
        if (! $sale) {
            return redirect()->route('online-order.index')
                ->with('error', __('Online order not found.'));
        }

        if (($sale->order_status ?: 'Pending') === 'Delivered') {
            return redirect()->route('online-order.show', $id)
                ->with('error', __('Delivered orders cannot change status.'));
        }

        $validated = $request->validate([
            'order_status' => ['required', 'in:'.implode(',', self::FULFILLMENT_STATUSES)],
        ]);

        return $this->setOrderStatus(
            $id,
            $validated['order_status'],
            __('Order status updated successfully.')
        );
    }

    public function updateImeiSerial(Request $request, string $id): RedirectResponse
    {
        $sale = $this->findOnlineSale($id);
        if (! $sale) {
            return redirect()->route('online-order.index')
                ->with('error', __('Online order not found.'));
        }

        if (($sale->order_status ?: 'Pending') === 'Delivered') {
            return redirect()->route('online-order.show', $id)
                ->with('error', __('Delivered orders cannot be modified.'));
        }

        $serials = (array) $request->input('imei', []);
        $available = $this->availableImeiForSale($sale);
        $usedInRequest = [];

        foreach ($sale->saleDetails as $detail) {
            $item = $detail->item;
            $type = (string) ($item->type ?? $detail->item_type ?? '');
            if (! in_array($type, ['IMEI_Product', 'Serial_Product'], true)) {
                continue;
            }

            $detailId = (int) $detail->id;
            $selected = trim((string) ($serials[$detailId] ?? ''));
            if ($selected === '') {
                return redirect()->route('online-order.show', $id)
                    ->with('error', __('Please select IMEI/Serial for :item.', ['item' => $item->name ?? __('item')]));
            }

            $options = $available[$detailId] ?? [];
            if (! in_array($selected, $options, true)) {
                return redirect()->route('online-order.show', $id)
                    ->with('error', __('Selected IMEI/Serial is not available for :item.', ['item' => $item->name ?? __('item')]));
            }

            if (isset($usedInRequest[$selected])) {
                return redirect()->route('online-order.show', $id)
                    ->with('error', __('Each IMEI/Serial can only be used once.'));
            }
            $usedInRequest[$selected] = true;

            $detail->update([
                'expiry_imei_serial' => $selected,
                'item_type' => $type,
            ]);
        }

        return redirect()->route('online-order.show', $id)
            ->with('success', __('IMEI/Serial numbers saved successfully.'));
    }

    /**
     * @return array<int, list<string>>
     */
    protected function availableImeiForSale(Sale $sale): array
    {
        $outletId = (int) ($sale->outlet_id ?: session('outlet.outlet_id'));
        $map = [];

        $imeiItemIds = [];
        foreach ($sale->saleDetails as $detail) {
            $item = $detail->item;
            $type = (string) ($item->type ?? $detail->item_type ?? '');
            if (in_array($type, ['IMEI_Product', 'Serial_Product'], true) && $item) {
                $imeiItemIds[] = (int) $item->id;
            }
        }
        $imeiItemIds = array_values(array_unique($imeiItemIds));

        $batch = [];
        if ($imeiItemIds && $outletId > 0) {
            try {
                $batch = $this->stockRepository->getIMEINumbersBatch($imeiItemIds, $outletId);
            } catch (\Throwable) {
                $batch = [];
            }
        }

        foreach ($sale->saleDetails as $detail) {
            $item = $detail->item;
            $type = (string) ($item->type ?? $detail->item_type ?? '');
            if (! in_array($type, ['IMEI_Product', 'Serial_Product'], true) || ! $item) {
                continue;
            }

            $options = array_values(array_unique(array_filter(
                $batch[(int) $item->id]['imei_numbers'] ?? []
            )));

            $current = trim((string) ($detail->expiry_imei_serial ?? ''));
            if ($current !== '' && ! in_array($current, $options, true)) {
                array_unshift($options, $current);
            }

            $map[(int) $detail->id] = $options;
        }

        return $map;
    }

    protected function setOrderStatus(string $encryptedId, string $status, string $successMessage): RedirectResponse
    {
        $sale = $this->findOnlineSale($encryptedId);
        if (! $sale) {
            return redirect()->route('online-order.index')
                ->with('error', __('Online order not found.'));
        }

        if (($sale->order_status ?: 'Pending') === 'Delivered') {
            return redirect()->route('online-order.show', $encryptedId)
                ->with('error', __('Delivered orders cannot change status.'));
        }

        if (! in_array($status, self::ORDER_STATUSES, true)) {
            return redirect()->route('online-order.show', $encryptedId)
                ->with('error', __('Invalid order status.'));
        }

        $payload = [
            'order_status' => $status,
            'delivery_status' => $status,
        ];

        if ($status === 'Delivered' && empty($sale->close_time)) {
            $payload['close_time'] = now();
        }

        $sale->update($payload);

        return redirect()->route('online-order.show', $encryptedId)
            ->with('success', $successMessage);
    }

    /**
     * @param  array{draw:int,length:int,start:int,search:string}  $params
     * @return array<string, mixed>
     */
    protected function dataTableData(array $params): array
    {
        $companyId = (int) session('company.company_id');
        $outletId = session('outlet.outlet_id');

        $base = Sale::query()
            ->with(['customer'])
            ->where('del_status', 'Live')
            ->where('online_yes_no', 'Yes')
            ->where('company_id', $companyId);

        if ($outletId) {
            $base->where('outlet_id', $outletId);
        }

        $recordsTotal = (clone $base)->count();
        $query = (clone $base)->orderByDesc('id');

        if ($params['search'] !== '') {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('sale_no', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhere('account_note', 'like', "%{$search}%")
                    ->orWhere('order_status', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($params['start'])->take($params['length'])->get();
        $startingNumber = $recordsFiltered - $params['start'];

        $data = $rows->map(function (Sale $sale, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $sale->id,
                'sale_no' => $sale->sale_no,
                'customer_name' => $sale->customer?->name ?: __('Walk-in Customer'),
                'customer_phone' => $sale->customer?->phone ?: '-',
                'sale_date' => formatDate($sale->sale_date),
                'grand_total' => formatAmount($sale->grand_total),
                'order_status' => $sale->order_status ?: 'Pending',
                'payment_status' => $this->resolvePaymentStatus($sale),
                'fulfillment' => $sale->charge_type ?: '-',
                'encrypted_id' => $sale->encrypted_id,
            ];
        });

        return [
            'draw' => $params['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];
    }

    protected function findOnlineSale(string $encryptedId): ?Sale
    {
        $sale = $this->saleService->getSaleByEncryptedId($encryptedId);
        if (! $sale || $sale->online_yes_no !== 'Yes' || $sale->del_status !== 'Live') {
            return null;
        }

        return $sale;
    }

    protected function resolvePaymentStatus(Sale $sale): string
    {
        $paid = (float) ($sale->paid_amount ?? 0);
        $due = (float) ($sale->due_amount ?? 0);

        if ($sale->payment_status === 'Refunded') {
            return 'Refunded';
        }

        if ($due <= 0.0001 && $paid > 0) {
            return 'Paid';
        }

        if ($paid > 0) {
            return 'Partial';
        }

        return $sale->payment_status ?: 'Unpaid';
    }
}
