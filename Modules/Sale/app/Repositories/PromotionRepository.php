<?php

namespace Modules\Sale\Repositories;

use Modules\Sale\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

class PromotionRepository
{
    protected $model;

    public function __construct(Promotion $model)
    {
        $this->model = $model;
    }

    /**
     * Get all promotions for a company
     */
    public function all(int $companyId): Collection
    {
        return $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get promotions with pagination and search for DataTable
     */
    public function getDataTableData(int $companyId, int $start = 0, int $length = 10, string $search = ''): array
    {
        $baseQuery = $this->model
            ->where('del_status', 'Live')
            ->where('company_id', $companyId);

        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%");
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $query->count();

        $promotions = $query
            ->orderBy('id', 'desc')
            ->skip($start)
            ->take($length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'filteredCount' => $filteredCount,
            'data' => $promotions,
        ];
    }

    /**
     * Find promotion by ID
     */
    public function find(int $id): ?Promotion
    {
        return $this->model
            ->where('del_status', 'Live')
            ->find($id);
    }

    /**
     * Find promotion by encrypted ID
     */
    public function findByEncryptedId(string $encryptedId): ?Promotion
    {
        $decryptedId = decrypt($encryptedId);
        return $this->find($decryptedId);
    }

    /**
     * Create a new promotion
     */
    public function create(array $data): Promotion
    {
        return $this->model->create($data);
    }

    /**
     * Update a promotion
     */
    public function update(Promotion $promotion, array $data): bool
    {
        return $promotion->update($data);
    }

    /**
     * Soft delete a promotion (mark as deleted)
     */
    public function delete(Promotion $promotion): bool
    {
        return $promotion->update(['del_status' => 'Deleted']);
    }

    /**
     * Check for overlapping promotions
     */
    public function checkOverlappingPromotions(int $companyId, int $outletId, string $startDate, string $endDate, ?int $foodMenuId = null, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('status', '1')
            ->where('del_status', 'Live')
            ->where('outlet_id', $outletId)
            ->where('company_id', $companyId)
            ->where(function($q) use ($startDate, $endDate) {
                // Check if start date falls within existing promotion
                $q->where(function($subQ) use ($startDate, $endDate) {
                    $subQ->where('start_date', '>=', $startDate)
                        ->where('start_date', '<=', $endDate);
                })
                // Or check if end date falls within existing promotion
                ->orWhere(function($subQ) use ($startDate, $endDate) {
                    $subQ->where('end_date', '>=', $startDate)
                        ->where('end_date', '<=', $endDate);
                });
            });

        // Add food menu check if applicable
        if ($foodMenuId !== null) {
            $query->where('item_id', $foodMenuId);
        }

        // Exclude current promotion if updating
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}

