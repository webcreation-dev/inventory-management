<?php

namespace Modules\Ecommerce\Services;

use Modules\Ecommerce\Models\EcommerceProductRating;
use Modules\Sale\Models\SaleDetail;
use Modules\Stock\Models\Item;

class ProductRatingService
{
    /** @var array<int, array{rating: float, reviews: int}> */
    protected array $summaryCache = [];

    public function __construct(
        protected EcommerceContentService $content,
    ) {
    }

    /**
     * @param  list<int>  $itemIds
     */
    public function preloadSummaries(array $itemIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($ids === []) {
            return;
        }

        $missing = array_values(array_filter($ids, fn ($id) => ! isset($this->summaryCache[$id])));
        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->summaryCache[$id] = ['rating' => 0.0, 'reviews' => 0];
        }

        try {
            $rows = EcommerceProductRating::query()
                ->forCompany($this->content->companyId())
                ->live()
                ->whereIn('item_id', $missing)
                ->selectRaw('item_id, AVG(rating) as avg_rating, COUNT(*) as review_count')
                ->groupBy('item_id')
                ->get();

            foreach ($rows as $row) {
                $this->summaryCache[(int) $row->item_id] = [
                    'rating' => round((float) $row->avg_rating, 1),
                    'reviews' => (int) $row->review_count,
                ];
            }
        } catch (\Throwable) {
            // Table may not exist yet.
        }
    }

    /**
     * @return array{rating: float, reviews: int}
     */
    public function summary(int $itemId): array
    {
        if (! isset($this->summaryCache[$itemId])) {
            $this->preloadSummaries([$itemId]);
        }

        return $this->summaryCache[$itemId] ?? ['rating' => 0.0, 'reviews' => 0];
    }

    /**
     * @return list<int>
     */
    public function rateableItemIds(int $productId): array
    {
        $ids = [(int) $productId];
        try {
            $item = Item::query()->find($productId);
            if (! $item) {
                return $ids;
            }

            if ((string) ($item->type ?? '') === 'Variation_Product' || (int) ($item->parent_id ?? 0) === 0) {
                $children = Item::query()
                    ->where('parent_id', $productId)
                    ->where('del_status', 'Live')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $ids = array_values(array_unique(array_merge($ids, $children)));
            }

            if ((int) ($item->parent_id ?? 0) > 0) {
                $ids[] = (int) $item->parent_id;
                $siblings = Item::query()
                    ->where('parent_id', (int) $item->parent_id)
                    ->where('del_status', 'Live')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $ids = array_values(array_unique(array_merge($ids, $siblings)));
            }
        } catch (\Throwable) {
            // ignore
        }

        return $ids;
    }

    public function ratingTargetItemId(int $productId): int
    {
        try {
            $item = Item::query()->find($productId);
            if ($item && (int) ($item->parent_id ?? 0) > 0) {
                return (int) $item->parent_id;
            }
        } catch (\Throwable) {
            // ignore
        }

        return (int) $productId;
    }

    public function customerHasPurchased(int $customerId, int $productId): bool
    {
        if ($customerId <= 0 || $productId <= 0) {
            return false;
        }

        $itemIds = $this->rateableItemIds($productId);
        $companyId = $this->content->companyId();

        try {
            return SaleDetail::query()
                ->live()
                ->whereIn('item_id', $itemIds)
                ->whereHas('sale', function ($q) use ($customerId, $companyId) {
                    $q->where('customer_id', $customerId)
                        ->where('del_status', 'Live')
                        ->when($companyId > 0, fn ($sq) => $sq->where('company_id', $companyId));
                })
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function customerRating(int $customerId, int $productId): ?EcommerceProductRating
    {
        $targetId = $this->ratingTargetItemId($productId);

        try {
            return EcommerceProductRating::query()
                ->forCompany($this->content->companyId())
                ->live()
                ->where('item_id', $targetId)
                ->where('customer_id', $customerId)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   average: float,
     *   total: int,
     *   recommend_count: int,
     *   recommend_percent: int,
     *   snapshot: array<int, int>,
     *   reviews: list<array<string, mixed>>,
     *   can_rate: bool,
     *   has_purchased: bool,
     *   my_rating: array<string, mixed>|null
     * }
     */
    public function panel(int $productId, ?int $customerId = null): array
    {
        $targetId = $this->ratingTargetItemId($productId);
        $companyId = $this->content->companyId();
        $snapshot = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $reviews = [];
        $average = 0.0;
        $total = 0;
        $recommendCount = 0;

        try {
            $rows = EcommerceProductRating::query()
                ->with(['customer:id,name'])
                ->forCompany($companyId)
                ->live()
                ->where('item_id', $targetId)
                ->orderByDesc('id')
                ->get();

            $total = $rows->count();
            if ($total > 0) {
                $average = round((float) $rows->avg('rating'), 1);
                $recommendCount = (int) $rows->where('recommend', true)->count();
                foreach ($rows as $row) {
                    $star = max(1, min(5, (int) $row->rating));
                    $snapshot[$star]++;
                }
                $reviews = $rows->take(20)->map(function (EcommerceProductRating $row) {
                    $name = trim((string) ($row->customer->name ?? ''));
                    if ($name === '') {
                        $name = __('Customer');
                    }

                    return [
                        'id' => (int) $row->id,
                        'rating' => (int) $row->rating,
                        'comment' => $row->comment,
                        'recommend' => (bool) $row->recommend,
                        'customer_name' => $name,
                        'created_at' => optional($row->created_at)?->diffForHumans(),
                    ];
                })->values()->all();
            }
        } catch (\Throwable) {
            // Table may not exist yet.
        }

        $hasPurchased = $customerId ? $this->customerHasPurchased($customerId, $targetId) : false;
        $my = $customerId ? $this->customerRating($customerId, $targetId) : null;
        $canRate = $customerId && $hasPurchased;

        return [
            'average' => $average,
            'total' => $total,
            'recommend_count' => $recommendCount,
            'recommend_percent' => $total > 0 ? (int) round(($recommendCount / $total) * 100) : 0,
            'snapshot' => $snapshot,
            'reviews' => $reviews,
            'can_rate' => (bool) $canRate,
            'has_purchased' => (bool) $hasPurchased,
            'my_rating' => $my ? [
                'rating' => (int) $my->rating,
                'comment' => $my->comment,
                'recommend' => (bool) $my->recommend,
            ] : null,
        ];
    }

    /**
     * @param  array{rating:int,comment?:?string,recommend?:bool}  $data
     */
    public function upsert(int $customerId, int $productId, array $data): EcommerceProductRating
    {
        $targetId = $this->ratingTargetItemId($productId);
        $companyId = $this->content->companyId();

        if (! $this->customerHasPurchased($customerId, $targetId)) {
            throw new \InvalidArgumentException(__('You can only rate products you have purchased.'));
        }

        $rating = max(1, min(5, (int) ($data['rating'] ?? 0)));
        if ($rating < 1) {
            throw new \InvalidArgumentException(__('Please select a rating.'));
        }

        $row = EcommerceProductRating::query()
            ->forCompany($companyId)
            ->live()
            ->where('item_id', $targetId)
            ->where('customer_id', $customerId)
            ->first();

        $payload = [
            'company_id' => $companyId,
            'item_id' => $targetId,
            'customer_id' => $customerId,
            'rating' => $rating,
            'comment' => isset($data['comment']) ? trim((string) $data['comment']) ?: null : null,
            'recommend' => array_key_exists('recommend', $data) ? (bool) $data['recommend'] : ($rating >= 4),
            'del_status' => 'Live',
        ];

        if ($row) {
            $row->fill($payload)->save();
        } else {
            $row = EcommerceProductRating::query()->create($payload);
        }

        unset($this->summaryCache[$targetId]);

        return $row->fresh();
    }
}
