<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Facades\Session;
use Modules\Stock\Models\Item;

class WishlistService
{
    public const SESSION_KEY = 'ecommerce_wishlist';

    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', Session::get(self::SESSION_KEY, []))));
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    public function toggle(int $productId): array
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException(__('Product not found.'));
        }

        $ids = $this->ids();
        if (in_array($productId, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($id) => $id !== $productId));
            $added = false;
        } else {
            // Ensure product exists before adding.
            $exists = Item::query()
                ->where('id', $productId)
                ->where('del_status', 'Live')
                ->exists();

            if (! $exists) {
                throw new \InvalidArgumentException(__('Product not found.'));
            }

            $ids[] = $productId;
            $added = true;
        }

        Session::put(self::SESSION_KEY, $ids);

        return [
            'added' => $added,
            'in_wishlist' => $added,
            'count' => count($ids),
            'ids' => $ids,
            'message' => $added ? __('Added to wishlist.') : __('Removed from wishlist.'),
        ];
    }

    public function remove(int $productId): array
    {
        $ids = array_values(array_filter($this->ids(), fn ($id) => $id !== $productId));
        Session::put(self::SESSION_KEY, $ids);

        return [
            'added' => false,
            'in_wishlist' => false,
            'count' => count($ids),
            'ids' => $ids,
            'message' => __('Removed from wishlist.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $ids = $this->ids();
        if ($ids === []) {
            return [];
        }

        $rows = Item::query()
            ->with(['category', 'brand', 'images'])
            ->whereIn('id', $ids)
            ->where('del_status', 'Live')
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($ids as $id) {
            $item = $rows->get($id);
            if (! $item) {
                continue;
            }
            $card = $this->mapItem($item);
            if ($card) {
                $items[] = $card;
            }
        }

        return $items;
    }

    protected function mapItem(Item $item): ?array
    {
        $price = (float) ($item->sale_price ?? 0);
        $photo = $item->photo
            ? asset('public/uploads/items/' . $item->photo)
            : asset('public/uploads/dummy_images/default-picture.png');

        $gallery = [$photo];
        foreach ($item->images as $image) {
            $gallery[] = $image->url;
        }

        $card = [
            'id' => (int) $item->id,
            'name' => $item->name,
            'price' => $price,
            'old_price' => $price,
            'discount' => 0,
            'category' => $item->category->name ?? '',
            'brand' => $item->brand->name ?? '',
            'rating' => 4.5,
            'reviews' => 0,
            'image' => $photo,
            'image_is_url' => true,
            'gallery' => array_values(array_unique(array_filter($gallery))),
            'description' => $item->description ?? '',
            'spec' => $item->code ?: '',
        ];

        try {
            return app(OfferService::class)->applyToCard($card);
        } catch (\Throwable) {
            return $card;
        }
    }
}
