<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sale\Models\Sale;

class QzTrayPrintController extends Controller
{
    public function invoice(string $id): JsonResponse
    {
        $saleId = $this->resolveSaleId($id);
        if (!$saleId) {
            return response()->json(['message' => __('Invalid invoice id.')], 422);
        }

        $sale = Sale::query()
            ->with([
                'customer:id,name,phone,address',
                'employee:id,name',
                'outlet:id,outlet_name,address,phone,email',
                'saleDetails' => fn ($query) => $query
                    ->where('del_status', 'Live')
                    ->with('item:id,name,code'),
                'salePayments' => fn ($query) => $query
                    ->where('del_status', 'Live')
                    ->with('paymentMethod:id,name'),
            ])
            ->where('id', $saleId)
            ->where('company_id', (int) session('company.company_id'))
            ->where('del_status', 'Live')
            ->first();

        if (!$sale) {
            return response()->json(['message' => __('Invoice not found.')], 404);
        }

        $items = $sale->saleDetails->map(function ($detail) {
            $name = $detail->item?->name ?: __('Unknown Item');
            $qty = (float) ($detail->qty ?? 0);
            $unitPrice = (float) ($detail->menu_price_with_discount ?? $detail->menu_unit_price ?? 0);

            return [
                'name' => $name,
                'qty' => $qty,
                'price' => $unitPrice,
                'line_total' => round($qty * $unitPrice, 3),
            ];
        })->values();

        $paymentLabel = $sale->salePayments->pluck('paymentMethod.name')->filter()->implode(', ');

        $invoice = [
            'id' => $sale->id,
            'encrypted_id' => $sale->encrypted_id,
            'invoice_no' => $sale->sale_no,
            'date_time' => optional($sale->date_time)->format('Y/m/d h:i A') ?: now()->format('Y/m/d h:i A'),
            'status' => $sale->due_amount > 0 ? 'Due' : 'Paid',
            'cashier' => $sale->employee?->name ?: ($sale->user?->name ?? 'N/A'),
            'customer' => [
                'name' => $sale->customer?->name ?: __('Walk-in Customer'),
                'phone' => $sale->customer?->phone,
                'address' => $sale->customer?->address,
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => (float) ($sale->sub_total ?? 0),
                'vat' => (float) ($sale->vat ?? 0),
                'discount' => (float) ($sale->total_discount_amount ?? 0),
                'grand_total' => (float) ($sale->grand_total ?? 0),
                'paid_amount' => (float) ($sale->paid_amount ?? 0),
                'due_amount' => (float) ($sale->due_amount ?? 0),
            ],
            'payment_method' => $paymentLabel ?: __('Cash'),
        ];

        $shop = [
            'name' => session('company.business_name') ?: config('app.name'),
            'outlet' => $sale->outlet?->outlet_name,
            'phone' => $sale->outlet?->phone,
            'email' => $sale->outlet?->email,
            'address' => $sale->outlet?->address,
            'footer' => config('qztray.invoice_footer', 'Thank you for shopping with us!'),
        ];

        return response()->json([
            'shop' => $shop,
            'invoice' => $invoice,
        ]);
    }

    public function certificate(): JsonResponse
    {
        return response()->json([
            'certificate' => config('qztray.certificate'),
        ]);
    }

    public function sign(Request $request): JsonResponse
    {
        $payload = (string) $request->input('toSign', '');
        if ($payload === '') {
            return response()->json(['message' => __('Missing toSign payload.')], 422);
        }

        $privateKey = config('qztray.private_key');
        if ($privateKey) {
            $signature = null;
            $resource = openssl_pkey_get_private($privateKey);
            if ($resource) {
                openssl_sign($payload, $signature, $resource, OPENSSL_ALGO_SHA256);
                openssl_pkey_free($resource);
            }

            if ($signature !== null) {
                return response()->json([
                    'signature' => base64_encode($signature),
                ]);
            }
        }

        // Development fallback only - replace with a real private key for production.
        return response()->json([
            'signature' => base64_encode(hash('sha256', $payload . config('app.key'), true)),
            'warning' => 'Using fallback signature. Configure QZ_TRAY_PRIVATE_KEY for production.',
        ]);
    }

    private function resolveSaleId(string $id): ?int
    {
        if (ctype_digit($id)) {
            return (int) $id;
        }

        try {
            return (int) decrypt($id);
        } catch (DecryptException) {
            return null;
        }
    }
}

