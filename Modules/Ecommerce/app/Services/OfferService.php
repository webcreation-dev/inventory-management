<?php

namespace Modules\Ecommerce\Services;

use Modules\Ecommerce\Models\EcommerceFlashSale;
use Modules\Ecommerce\Models\EcommerceFlashSaleItem;

class OfferService
{
    public function __construct(
        protected EcommerceContentService $content,
    ) {
    }

    public function activeOffer(): ?EcommerceFlashSale
    {
        return $this->content->activeFlashSale();
    }

    /**
     * Resolve offer price for an item. Returns [price, old_price, discount_percent] or null.
     */
    public function priceForItem(int $itemId, float $regularPrice, ?EcommerceFlashSale $offer = null): ?array
    {
        $offer ??= $this->activeOffer();
        if (! $offer || $regularPrice <= 0) {
            return null;
        }

        $line = $offer->relationLoaded('items')
            ? $offer->items->firstWhere('item_id', $itemId)
            : EcommerceFlashSaleItem::query()
                ->where('flash_sale_id', $offer->id)
                ->where('item_id', $itemId)
                ->first();

        if (! $line) {
            return null;
        }

        $offerPrice = null;
        if ($line->flash_price !== null && $line->flash_price !== '') {
            $offerPrice = (float) $line->flash_price;
        } else {
            $type = $offer->discount_type ?? 'custom';
            $value = (float) ($offer->discount_value ?? 0);
            if ($type === 'percent' && $value > 0) {
                $offerPrice = round($regularPrice * (1 - min(100, $value) / 100), 3);
            } elseif ($type === 'fixed' && $value > 0) {
                $offerPrice = max(0, round($regularPrice - $value, 3));
            }
        }

        if ($offerPrice === null || $offerPrice >= $regularPrice) {
            return null;
        }

        $discount = (int) round((($regularPrice - $offerPrice) / $regularPrice) * 100);

        return [
            'price' => $offerPrice,
            'old_price' => $regularPrice,
            'discount' => $discount,
            'offer_title' => $offer->title,
            'offer_id' => $offer->id,
        ];
    }

    public function applyToCard(array $card, ?EcommerceFlashSale $offer = null): array
    {
        $regular = (float) ($card['price'] ?? 0);
        if (($card['old_price'] ?? 0) > $regular) {
            $regular = (float) $card['old_price'];
        }

        $priced = $this->priceForItem((int) $card['id'], $regular, $offer);
        if (! $priced) {
            return $card;
        }

        $card['old_price'] = $priced['old_price'];
        $card['price'] = $priced['price'];
        $card['discount'] = $priced['discount'];
        $card['offer_title'] = $priced['offer_title'];

        return $card;
    }
}
