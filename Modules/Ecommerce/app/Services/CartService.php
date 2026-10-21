<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Facades\Session;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\Outlet;
use Modules\Configuration\Models\Tax;
use Modules\Sale\Services\GstTaxService;
use Modules\Stock\Models\Item;
use Modules\Stock\Repositories\StockRepository;

class CartService
{
    public const SESSION_KEY = 'ecommerce_cart';

    public const SHIPPING_FEE = 8.00;

    public function items(): array
    {
        return array_values(Session::get(self::SESSION_KEY, []));
    }

    public function count(): int
    {
        return (int) collect($this->items())->sum('qty');
    }

    public function subtotal(): float
    {
        return round((float) collect($this->items())->sum(fn ($row) => (float) $row['price'] * (int) $row['qty']), 2);
    }

    /**
     * Exclusive tax only — same as POS payable tax (added on top of prices).
     */
    public function tax(): float
    {
        return $this->taxBreakdown()['exclusive'];
    }

    /**
     * Inclusive tax embedded in line prices (shown in POS as “Incl.”, not added again).
     */
    public function taxInclusive(): float
    {
        return $this->taxBreakdown()['inclusive'];
    }

    public function shipping(): float
    {
        return $this->count() > 0 ? self::SHIPPING_FEE : 0.0;
    }

    public function total(): float
    {
        if ($this->count() === 0) {
            return 0.0;
        }

        // Cart total excludes shipping — shipping is applied only at checkout.
        return round($this->subtotal() + $this->tax(), 2);
    }

    public function checkoutTotal(bool $withShipping = true): float
    {
        $total = $this->total();
        if ($withShipping) {
            $total += $this->shipping();
        }

        return round($total, 2);
    }

    public function add(int $productId, int $qty = 1, ?float $overridePrice = null): array
    {
        $qty = max(1, $qty);
        $product = $this->resolveProduct($productId, $overridePrice);

        if (! $product) {
            throw new \InvalidArgumentException(__('Product not found.'));
        }

        if (empty($product['in_stock'])) {
            throw new \InvalidArgumentException(__('This product is out of stock.'));
        }

        $cart = Session::get(self::SESSION_KEY, []);
        $key = (string) $productId;
        $existingQty = isset($cart[$key]) ? (int) $cart[$key]['qty'] : 0;
        $newQty = $existingQty + $qty;
        $available = (float) ($product['stock'] ?? 0);

        if ($available < $newQty) {
            throw new \InvalidArgumentException(
                $available <= 0
                    ? __('This product is out of stock.')
                    : __('Only :qty left in stock.', ['qty' => (int) $available])
            );
        }

        if (isset($cart[$key])) {
            $cart[$key]['qty'] = $newQty;
            $cart[$key]['name'] = $product['name'];
            $cart[$key]['stock'] = $available;
            $cart[$key]['applicable_tax_id'] = $product['applicable_tax_id'] ?? null;
            $cart[$key]['tax_type'] = $product['tax_type'] ?? 'Inclusive';
        } else {
            $cart[$key] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => (float) $product['price'],
                'qty' => $qty,
                'image' => $product['image'],
                'image_is_url' => (bool) ($product['image_is_url'] ?? false),
                'stock' => $available,
                'applicable_tax_id' => $product['applicable_tax_id'] ?? null,
                'tax_type' => $product['tax_type'] ?? 'Inclusive',
            ];
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->payload();
    }

    public function update(int $productId, int $qty): array
    {
        $cart = Session::get(self::SESSION_KEY, []);
        $key = (string) $productId;

        if (! isset($cart[$key])) {
            throw new \InvalidArgumentException(__('Cart item not found.'));
        }

        if ($qty <= 0) {
            unset($cart[$key]);
        } else {
            $available = $this->availableStock($productId);
            if ($available < $qty) {
                throw new \InvalidArgumentException(
                    $available <= 0
                        ? __('This product is out of stock.')
                        : __('Only :qty left in stock.', ['qty' => (int) $available])
                );
            }
            $resolved = $this->resolveProduct($productId);
            if ($resolved) {
                $cart[$key]['name'] = $resolved['name'];
                $cart[$key]['price'] = (float) ($cart[$key]['price'] ?? $resolved['price']);
                $cart[$key]['applicable_tax_id'] = $resolved['applicable_tax_id'] ?? null;
                $cart[$key]['tax_type'] = $resolved['tax_type'] ?? 'Inclusive';
            }
            $cart[$key]['qty'] = $qty;
            $cart[$key]['stock'] = $available;
        }

        Session::put(self::SESSION_KEY, $cart);

        return $this->payload();
    }

    public function remove(int $productId): array
    {
        $cart = Session::get(self::SESSION_KEY, []);
        unset($cart[(string) $productId]);
        Session::put(self::SESSION_KEY, $cart);

        return $this->payload();
    }

    public function clear(): array
    {
        Session::forget(self::SESSION_KEY);

        return $this->payload();
    }

    public function payload(): array
    {
        $taxes = $this->taxBreakdown();

        return [
            'items' => $this->items(),
            'count' => $this->count(),
            'subtotal' => $this->subtotal(),
            'tax' => $taxes['exclusive'],
            'tax_inclusive' => $taxes['inclusive'],
            'tax_rate' => null,
            'shipping' => $this->shipping(),
            'total' => $this->total(),
        ];
    }

    /**
     * POS-style tax breakdown for cart lines.
     *
     * @return array{exclusive: float, inclusive: float}
     */
    public function taxBreakdown(?int $customerStateId = null): array
    {
        $exclusive = 0.0;
        $inclusive = 0.0;

        if ($this->count() === 0 || ! $this->companyCollectsTax()) {
            return ['exclusive' => 0.0, 'inclusive' => 0.0];
        }

        $companyId = $this->companyId();
        $outletId = $this->defaultOutletId();
        $outletStateCode = null;
        if ($outletId) {
            // Same as GstTaxService / POS: state_code comes from outlets.state relation, not a column.
            $outlet = Outlet::query()->with('state')->find($outletId);
            $outletStateCode = $outlet?->state_code;
        }

        $taxsMap = Tax::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->get(['id', 'tax_name', 'tax_rate', 'parent_tax_id'])
            ->keyBy('id')
            ->map(fn (Tax $t) => [
                'id' => $t->id,
                'tax_name' => $t->tax_name,
                'tax_rate' => (float) $t->tax_rate,
                'parent_tax_id' => $t->parent_tax_id,
            ])
            ->toArray();

        if ($taxsMap === []) {
            return ['exclusive' => 0.0, 'inclusive' => 0.0];
        }

        foreach ($this->items() as $row) {
            $meta = $this->resolveLineTaxMeta($row);
            $applicableTaxIds = $meta['applicable_tax_id'];
            $taxType = $meta['tax_type'];

            if ($applicableTaxIds === null || trim((string) $applicableTaxIds) === '') {
                continue;
            }

            $calc = GstTaxService::calculateItemTax(
                (float) ($row['price'] ?? 0),
                (float) ($row['qty'] ?? 1),
                (string) $applicableTaxIds,
                $taxType,
                $outletStateCode ? (string) $outletStateCode : null,
                $customerStateId,
                $taxsMap
            );

            $lineTax = (float) ($calc['total_tax'] ?? 0);
            if ($taxType === 'Exclusive') {
                $exclusive += $lineTax;
            } else {
                $inclusive += $lineTax;
            }
        }

        return [
            'exclusive' => round($exclusive, 2),
            'inclusive' => round($inclusive, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{applicable_tax_id: ?string, tax_type: string}
     */
    protected function resolveLineTaxMeta(array $row): array
    {
        $taxType = (string) ($row['tax_type'] ?? '');
        $applicable = $row['applicable_tax_id'] ?? null;

        if ($applicable !== null && $taxType !== '') {
            return [
                'applicable_tax_id' => $applicable !== '' ? (string) $applicable : null,
                'tax_type' => in_array($taxType, ['Exclusive', 'Inclusive'], true) ? $taxType : 'Inclusive',
            ];
        }

        $itemId = (int) ($row['id'] ?? 0);
        if ($itemId <= 0) {
            return ['applicable_tax_id' => null, 'tax_type' => 'Inclusive'];
        }

        $item = Item::query()->with('parent')->find($itemId);
        if (! $item) {
            return ['applicable_tax_id' => null, 'tax_type' => 'Inclusive'];
        }

        return $this->taxMetaFromItem($item);
    }

    /**
     * @return array{applicable_tax_id: ?string, tax_type: string}
     */
    protected function taxMetaFromItem(Item $item): array
    {
        $applicable = $item->applicable_tax_id;
        $taxType = $item->tax_type ?? 'Inclusive';

        if ((empty($applicable) || $applicable === '') && $item->parent) {
            $applicable = $item->parent->applicable_tax_id;
            $taxType = $item->parent->tax_type ?? $taxType;
        }

        $taxType = in_array((string) $taxType, ['Exclusive', 'Inclusive'], true)
            ? (string) $taxType
            : 'Inclusive';

        $applicable = $applicable !== null && trim((string) $applicable) !== ''
            ? (string) $applicable
            : null;

        return [
            'applicable_tax_id' => $applicable,
            'tax_type' => $taxType,
        ];
    }

    protected function companyCollectsTax(): bool
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return false;
        }

        $fromSession = session('company.collect_tax');
        if ($fromSession !== null && $fromSession !== '') {
            return $fromSession === 'Yes';
        }

        try {
            return Company::query()->where('id', $companyId)->value('collect_tax') === 'Yes';
        } catch (\Throwable) {
            return false;
        }
    }

    protected function companyId(): int
    {
        try {
            return (int) app(EcommerceContentService::class)->companyId();
        } catch (\Throwable) {
            return (int) session('company.company_id', 0);
        }
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    public function stockMapForIds(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === []) {
            return [];
        }

        $outletId = $this->defaultOutletId();
        if (! $outletId) {
            return array_fill_keys($itemIds, 999999.0);
        }

        try {
            $batch = app(StockRepository::class)->getStockQuantitiesBatch($itemIds, $outletId);
        } catch (\Throwable) {
            return array_fill_keys($itemIds, 0.0);
        }

        $map = [];
        foreach ($itemIds as $id) {
            $map[$id] = (float) (($batch['in'][$id] ?? 0) - ($batch['out'][$id] ?? 0));
        }

        return $map;
    }

    public function availableStock(int $itemId): float
    {
        return (float) ($this->stockMapForIds([$itemId])[$itemId] ?? 0);
    }

    public function variationAttributeLabel(Item $child, string $parentName = ''): string
    {
        $details = $child->variation_details;
        if (is_string($details)) {
            $decoded = json_decode($details, true);
            $details = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($details)) {
            $details = [];
        }

        $parts = [];
        $attributes = $details['variation_attributes'] ?? null;
        if (is_array($attributes) && $attributes !== []) {
            foreach ($attributes as $attribute) {
                if (! is_array($attribute)) {
                    continue;
                }
                $attrName = trim((string) ($attribute['name'] ?? ''));
                $attrValue = trim((string) ($attribute['value'] ?? ''));
                if ($attrName !== '' && $attrValue !== '') {
                    $parts[] = $attrName . ': ' . $attrValue;
                } elseif ($attrValue !== '') {
                    $parts[] = $attrValue;
                }
            }
        }

        if ($parts === []) {
            $variationName = trim((string) ($details['variation_name'] ?? ''));
            if ($variationName === '') {
                $variationName = trim((string) $child->name);
            }
            if ($parentName !== '') {
                $variationName = preg_replace('/^' . preg_quote($parentName, '/') . '\s*[-–:>]\s*/i', '', $variationName) ?: $variationName;
            }
            $parts[] = $variationName !== '' ? $variationName : __('Variation');
        }

        return implode(' - ', $parts);
    }

    public function variationCartName(Item $child, ?string $parentName = null): string
    {
        if ($parentName === null) {
            $parentId = (int) ($child->parent_id ?? 0);
            $parentName = '';
            if ($parentId > 0) {
                $parentName = (string) (Item::query()->where('id', $parentId)->value('name') ?? '');
            }
        }

        $attrs = $this->variationAttributeLabel($child, (string) $parentName);
        $parentName = trim((string) $parentName);

        if ($parentName === '') {
            return $attrs !== '' ? $attrs : (string) $child->name;
        }

        return trim($parentName . ' ' . $attrs);
    }

    /**
     * Resolve a cartable product from live stock items only.
     */
    public function resolveProduct(int $productId, ?float $overridePrice = null): ?array
    {
        try {
            $item = Item::query()
                ->with(['category', 'brand', 'images', 'parent.images'])
                ->where('id', $productId)
                ->where('del_status', 'Live')
                ->where(function ($q) {
                    $q->whereNull('enable_disable_status')
                        ->orWhere('enable_disable_status', 'Enable');
                })
                ->first();
        } catch (\Throwable) {
            $item = null;
        }

        if (! $item) {
            return null;
        }

        if ((string) ($item->type ?? '') === 'Variation_Product') {
            throw new \InvalidArgumentException(__('Please choose a product variation first.'));
        }

        $imageSource = $item;
        if ((int) ($item->parent_id ?? 0) > 0 && $item->parent) {
            $imageSource = $item->parent;
            if (! $imageSource->relationLoaded('images')) {
                $imageSource->load('images');
            }
        }

        $photo = $imageSource->photo
            ? asset('public/uploads/items/' . $imageSource->photo)
            : asset('public/uploads/dummy_images/default-picture.png');

        $gallery = [$photo];
        foreach ($imageSource->images as $image) {
            $gallery[] = $image->url;
        }

        $regular = (float) ($item->sale_price ?? 0);
        $price = $overridePrice ?? $regular;
        $oldPrice = $regular;
        $discount = 0;

        if ($overridePrice === null) {
            try {
                $offerPrice = app(OfferService::class)->priceForItem((int) $item->id, $regular);
                if ($offerPrice) {
                    $price = $offerPrice['price'];
                    $oldPrice = $offerPrice['old_price'];
                    $discount = $offerPrice['discount'];
                }
            } catch (\Throwable) {
                // Offer tables may not exist yet.
            }
        }

        $parentId = (int) ($item->parent_id ?? 0);
        $displayName = (string) $item->name;
        if ($parentId > 0) {
            $parentName = (string) ($item->parent->name ?? Item::query()->where('id', $parentId)->value('name') ?? '');
            $displayName = $this->variationCartName($item, $parentName);
        }

        $stock = $this->availableStock((int) $item->id);
        $taxMeta = $this->taxMetaFromItem($item);

        return [
            'id' => (int) $item->id,
            'encrypted_id' => $item->encrypted_id ?? encrypt((int) $item->id),
            'name' => $displayName,
            'price' => $price,
            'old_price' => $oldPrice,
            'discount' => $discount,
            'category' => $item->category->name ?? '',
            'brand' => $item->brand->name ?? '',
            'rating' => 4.5,
            'reviews' => 0,
            'image' => $photo,
            'image_is_url' => true,
            'gallery' => array_values(array_unique(array_filter($gallery))),
            'description' => $item->description ?? '',
            'spec' => $item->code ?: '',
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'applicable_tax_id' => $taxMeta['applicable_tax_id'],
            'tax_type' => $taxMeta['tax_type'],
        ];
    }

    protected function defaultOutletId(): ?int
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            $companyId = 1;
        }

        return Outlet::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('active_status')->orWhere('active_status', 'Active');
            })
            ->orderBy('id')
            ->value('id');
    }
}
