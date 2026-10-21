<?php

namespace Modules\Ecommerce\Services;

use App\Facades\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Configuration\Models\Outlet;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;
use Modules\Sale\Models\SalePayment;
use Modules\Sale\Services\GstTaxService;
use Modules\Sale\Services\SaleNumberService;
use Modules\Stock\Models\Item;

class OrderService
{
    public function createSale(array $payload): Sale
    {
        return DB::transaction(function () use ($payload) {
            $companyId = (int) $payload['company_id'];
            $outletId = $payload['outlet_id'] ?? $this->defaultOutletId($companyId);
            $subtotal = (float) $payload['subtotal'];
            $tax = (float) $payload['tax'];
            $shipping = (float) $payload['shipping'];
            $total = (float) $payload['total'];
            $items = $payload['items'] ?? [];
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $payload['payment_method'] ?? null;
            $markPaid = (bool) ($payload['mark_paid'] ?? false);

            $paidAmount = $markPaid ? $total : 0;
            $dueAmount = $markPaid ? 0 : $total;
            $saleNo = ! empty($payload['invoice'])
                ? (string) $payload['invoice']
                : app(SaleNumberService::class)->generate($companyId);

            $noteParts = [
                'Ecommerce Order',
                'Fulfillment: ' . ($payload['fulfillment_type'] ?? 'delivery'),
            ];
            if (! empty($payload['address'])) {
                $noteParts[] = 'Address: ' . $payload['address'];
            }
            if (! empty($payload['phone'])) {
                $noteParts[] = 'Phone: ' . $payload['phone'];
            }

            $sale = Sale::query()->create([
                'customer_id' => $payload['customer_id'] ?? null,
                'employee_id' => null,
                'sale_no' => $saleNo,
                'total_items' => count($items),
                'sub_total' => $subtotal,
                'given_amount' => $paidAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => 0,
                'previous_due' => 0,
                'due_amount' => $dueAmount,
                'disc' => 0,
                'disc_actual' => 0,
                'vat' => $tax,
                'rounding' => 0,
                'total_payable' => $total,
                'total_item_discount_amount' => 0,
                'sub_total_with_discount' => $subtotal,
                'sub_total_discount_amount' => 0,
                'total_discount_amount' => 0,
                'delivery_charge' => $shipping,
                'charge_type' => ($payload['fulfillment_type'] ?? '') === 'pickup' ? 'Pickup' : 'Delivery',
                'sub_total_discount_value' => 0,
                'sub_total_discount_type' => null,
                'sale_date' => now()->toDateString(),
                'date_time' => now(),
                'order_time' => now(),
                'order_date_time' => now(),
                'grand_total' => $total,
                'online_yes_no' => 'Yes',
                'order_status' => 'Pending',
                'payment_status' => $markPaid ? 'Paid' : 'Unpaid',
                'delivery_status' => ($payload['fulfillment_type'] ?? '') === 'pickup' ? 'Pickup' : 'Pending',
                'account_type' => $paymentMethod->account_type ?? null,
                'account_note' => $paymentMethod->name ?? null,
                'note' => implode(' | ', $noteParts),
                'random_code' => bin2hex(random_bytes(8)),
                'user_id' => null,
                'outlet_id' => $outletId,
                'company_id' => $companyId,
                'del_status' => 'Live',
                'sale_vat_objects' => $this->buildSaleVatObjectsJson($items, $payload['customer_id'] ?? null, (int) $outletId, $companyId),
            ]);

            foreach ($items as $item) {
                $qty = (float) ($item['qty'] ?? 1);
                $unitPrice = (float) ($item['price'] ?? 0);
                $itemId = (int) ($item['id'] ?? 0);
                $stockItem = $itemId > 0 ? Item::query()->with('parent')->find($itemId) : null;

                $cartLine = [
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'applicable_tax_id' => $item['applicable_tax_id']
                        ?? $stockItem?->applicable_tax_id
                        ?? $stockItem?->parent?->applicable_tax_id,
                    'tax_type' => $item['tax_type']
                        ?? $stockItem?->tax_type
                        ?? $stockItem?->parent?->tax_type
                        ?? 'Inclusive',
                ];

                $menuTaxes = [];
                $itemTaxAmount = 0.0;
                $menuVatPct = 0.0;
                try {
                    if ($outletId && $companyId > 0) {
                        $menuTaxes = GstTaxService::getItemMenuTaxes(
                            $cartLine,
                            isset($payload['customer_id']) ? (int) $payload['customer_id'] : null,
                            (int) $outletId,
                            $companyId
                        );
                        $itemTaxAmount = GstTaxService::getItemTaxAmount($menuTaxes);
                        $menuVatPct = GstTaxService::getItemTaxPercentage($menuTaxes);
                    }
                } catch (\Throwable) {
                    $menuTaxes = [];
                    $itemTaxAmount = 0.0;
                    $menuVatPct = 0.0;
                }

                SaleDetail::query()->create([
                    'item_id' => $itemId > 0 ? $itemId : 0,
                    'qty' => $qty,
                    'menu_price_without_discount' => $unitPrice,
                    'menu_price_with_discount' => $unitPrice,
                    'menu_unit_price' => $unitPrice,
                    'purchase_price' => $stockItem->purchase_price ?? 0,
                    'menu_vat_percentage' => $menuVatPct,
                    'item_tax_amount' => $itemTaxAmount,
                    'menu_taxes' => $menuTaxes !== [] ? json_encode($menuTaxes) : null,
                    'menu_discount_value' => 0,
                    'discount_type' => null,
                    'menu_note' => $item['name'] ?? null,
                    'discount_amount' => 0,
                    'item_type' => $stockItem->type ?? 'General_Product',
                    'sales_id' => $sale->id,
                    'is_promo_item' => 'No',
                    'delivery_status' => ($payload['fulfillment_type'] ?? '') === 'pickup' ? 'Pickup' : 'Pending',
                    'user_id' => null,
                    'outlet_id' => $outletId,
                    'company_id' => $companyId,
                    'del_status' => 'Live',
                ]);
            }

            if ($markPaid && $paymentMethod) {
                $this->recordPayment($sale, $paymentMethod, $total, $payload['payment_details'] ?? null);
            }

            $this->syncCustomerDeliveryAddress($payload);

            return $sale->fresh(['saleDetails', 'salePayments']);
        });
    }

    /**
     * Persist checkout delivery address onto the customer record.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function syncCustomerDeliveryAddress(array $payload): void
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $address = trim((string) ($payload['address'] ?? ''));
        $fulfillment = (string) ($payload['fulfillment_type'] ?? 'delivery');

        if ($customerId <= 0 || $address === '' || $fulfillment !== 'delivery') {
            return;
        }

        $customer = Customer::query()
            ->where('id', $customerId)
            ->where('del_status', 'Live')
            ->first();

        if (! $customer) {
            return;
        }

        $customer->delivery_address = mb_substr($address, 0, 255);
        $customer->save();

        // Keep ecommerce session in sync for the next checkout prefill.
        try {
            $auth = app(CustomerAuthService::class);
            if ($auth->id() === $customerId) {
                \Illuminate\Support\Facades\Session::put(
                    CustomerAuthService::SESSION_KEY,
                    $auth->toSessionPayload($customer->fresh())
                );
            }
        } catch (\Throwable) {
            // Session refresh is optional.
        }
    }

    public function markSalePaid(Sale $sale, PaymentMethod $paymentMethod, ?array $paymentDetails = null): Sale
    {
        return DB::transaction(function () use ($sale, $paymentMethod, $paymentDetails) {
            $total = (float) $sale->total_payable;
            $sale->update([
                'given_amount' => $total,
                'paid_amount' => $total,
                'due_amount' => 0,
                'payment_status' => 'Paid',
                'account_type' => $paymentMethod->account_type,
                'account_note' => $paymentMethod->name,
            ]);

            if (! $sale->salePayments()->where('del_status', 'Live')->exists()) {
                $this->recordPayment($sale, $paymentMethod, $total, $paymentDetails);
            }

            return $sale->fresh(['saleDetails', 'salePayments']);
        });
    }

    public function recordPayment(Sale $sale, PaymentMethod $paymentMethod, float $amount, ?array $paymentDetails = null): SalePayment
    {
        return SalePayment::query()->create([
            'date' => now()->toDateString(),
            'payment_id' => $paymentMethod->id,
            'currency_type' => null,
            'multi_currency' => 'No',
            'multi_currency_rate' => 1,
            'amount' => $amount,
            'usage_point' => 0,
            'sale_id' => $sale->id,
            'payment_name' => $paymentMethod->name,
            'payment_details' => $paymentDetails ? json_encode($paymentDetails) : null,
            'outlet_id' => $sale->outlet_id,
            'user_id' => null,
            'company_id' => $sale->company_id,
            'del_status' => 'Live',
        ]);
    }

    public function onlinePaymentMethods(int $companyId)
    {
        return PaymentMethod::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('status', 'Enable')
            ->where('enable_in_online', 'Yes')
            ->orderByRaw('COALESCE(sort_id, 999999) ASC')
            ->orderBy('name')
            ->get(['id', 'name', 'account_type', 'configuration', 'enable_in_online', 'status']);
    }

    public function gatewayKey(?PaymentMethod $method): ?string
    {
        if (! $method) {
            return null;
        }

        $map = [
            'Paypal' => 'paypal',
            'Stripe' => 'stripe',
            'Razorpay' => 'razorpay',
            'Paystack' => 'paystack',
            'Paytm' => 'paytm',
            'Flutterwave' => 'flutterwave',
        ];

        return $map[$method->account_type] ?? null;
    }

    public function isGatewayMethod(?PaymentMethod $method): bool
    {
        return in_array($this->gatewayKey($method), ['stripe', 'paypal'], true);
    }

    public function startGatewayPayment(Sale $sale, PaymentMethod $method, array $customerMeta = []): array
    {
        $gateway = $this->gatewayKey($method);
        if (! $gateway) {
            throw new \InvalidArgumentException(__('Unsupported payment gateway.'));
        }

        $metadata = array_merge([
            'sale_id' => $sale->id,
            'sale_no' => $sale->sale_no,
            'customer_id' => $sale->customer_id,
            'payment_method_id' => $method->id,
            'ecommerce' => '1',
            'return_url' => route('ecommerce.payment.' . $gateway . '.return', [
                'sale' => $sale->id,
                'payment_method_id' => $method->id,
            ]),
            'cancel_url' => route('ecommerce.payment.' . $gateway . '.cancel', [
                'sale' => $sale->id,
                'payment_method_id' => $method->id,
            ]),
            'customer_email' => $customerMeta['email'] ?? null,
            'customer_name' => trim(($customerMeta['first_name'] ?? '') . ' ' . ($customerMeta['last_name'] ?? '')),
            'customer_phone' => $customerMeta['phone'] ?? null,
        ], $customerMeta);

        $response = PaymentGateway::processPayment(
            gatewayName: $gateway,
            amount: (float) $sale->total_payable,
            currency: 'USD',
            description: __('Ecommerce order') . ' ' . $sale->sale_no,
            metadata: $metadata,
            paymentMethodId: (int) $method->id
        );

        $data = $response->getData();
        $payload = [
            'gateway' => $gateway,
            'transaction_id' => $response->getTransactionId(),
            'client_secret' => $data['client_secret'] ?? null,
            'redirect_url' => $response->getRedirectUrl(),
            'gateway_response' => $data,
        ];

        if ($gateway === 'stripe') {
            $config = PaymentGateway::getGatewayConfig('stripe', (int) $method->id);
            $payload['publishable_key'] = $config['api_key']
                ?? $config['publishable_key']
                ?? $config['public_key']
                ?? null;
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function buildSaleVatObjectsJson(array $items, mixed $customerId, int $outletId, int $companyId): ?string
    {
        if ($outletId <= 0 || $companyId <= 0 || $items === []) {
            return null;
        }

        try {
            $cartItems = [];
            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $stockItem = $itemId > 0 ? Item::query()->with('parent')->find($itemId) : null;
                $cartItems[] = [
                    'quantity' => (float) ($item['qty'] ?? 1),
                    'unit_price' => (float) ($item['price'] ?? 0),
                    'discount' => 0,
                    'discount_type' => 'fixed',
                    'applicable_tax_id' => $item['applicable_tax_id']
                        ?? $stockItem?->applicable_tax_id
                        ?? $stockItem?->parent?->applicable_tax_id,
                    'tax_type' => $item['tax_type']
                        ?? $stockItem?->tax_type
                        ?? $stockItem?->parent?->tax_type
                        ?? 'Inclusive',
                ];
            }

            $objects = GstTaxService::buildSaleVatObjects(
                $cartItems,
                $customerId !== null ? (int) $customerId : null,
                $outletId,
                $companyId
            );

            return $objects !== [] ? json_encode($objects) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function defaultOutletId(int $companyId): ?int
    {
        return Outlet::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('active_status', 'Active')
            ->orderBy('id')
            ->value('id');
    }
}
