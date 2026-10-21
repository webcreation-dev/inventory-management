<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\PromotionRepository;
use Modules\Sale\Models\Promotion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class PromotionService
{
    protected $promotionRepository;

    public function __construct(PromotionRepository $promotionRepository)
    {
        $this->promotionRepository = $promotionRepository;
    }

    /**
     * Get all promotions for the current company
     */
    public function getAllPromotions(): Collection
    {
        return $this->promotionRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for promotions listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw): array
    {
        $result = $this->promotionRepository->getDataTableData(
            $this->getCompanyId(),
            $start,
            $length,
            $search
        );

        // Calculate the starting number for the current page
        $startingNumber = $result['filteredCount'] - $start;

        // Transform data for DataTable
        $transformedData = $result['data']->map(function ($promotion, $index) use ($startingNumber) {
            $type = $promotion->type == 1 ? 'Discount' : ($promotion->type == 2 ? 'Coupon Discount' : 'Free Item');
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $promotion->id,
                'title' => $promotion->title,
                'type' => $type,
                'start_date' => formatDate($promotion->start_date),
                'end_date' => formatDate($promotion->end_date),
                'status' => $promotion->status,
                'encrypted_id' => $promotion->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['filteredCount'],
            'data' => $transformedData,
        ];
    }

    /**
     * Get promotion by encrypted ID
     */
    public function getPromotionByEncryptedId(string $encryptedId): ?Promotion
    {
        return $this->promotionRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new promotion
     */
    public function createPromotion(array $data): Promotion
    {
        // Check for overlapping promotions
        $hasOverlap = $this->promotionRepository->checkOverlappingPromotions(
            $this->getCompanyId(),
            $this->getOutletId(),
            $data['start_date'],
            $data['end_date'],
            ($data['type'] == 1 || $data['type'] == 3) ? $data['item_id'] : null
        );

        if ($hasOverlap) {
            throw new \Exception('A promotion already exists for this date range and item');
        }

        $storeData = $this->preparePromotionData($data);
        return $this->promotionRepository->create($storeData);
    }

    /**
     * Update an existing promotion
     */
    public function updatePromotion(Promotion $promotion, array $data): bool
    {
        // Check for overlapping promotions (excluding current promotion)
        $hasOverlap = $this->promotionRepository->checkOverlappingPromotions(
            $this->getCompanyId(),
            $this->getOutletId(),
            $data['start_date'],
            $data['end_date'],
            ($data['type'] == 1 || $data['type'] == 3) ? $data['item_id'] : null,
            $promotion->id
        );

        if ($hasOverlap) {
            throw new \Exception('A promotion already exists for this date range and food menu');
        }

        $updateData = $this->preparePromotionData($data, true);
        return $this->promotionRepository->update($promotion, $updateData);
    }

    /**
     * Delete a promotion
     */
    public function deletePromotion(Promotion $promotion): bool
    {
        return $this->promotionRepository->delete($promotion);
    }

    /**
     * Prepare promotion data for store/update
     */
    protected function preparePromotionData(array $data, bool $isUpdate = false): array
    {
        $preparedData = [
            'type' => $data['type'],
            'title' => $data['title'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
            'outlet_id' => $this->getOutletId(),
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
        ];

        // Reset all type-specific fields to null first
        $preparedData['discount'] = null;
        $preparedData['coupon_code'] = null;
        $preparedData['item_id'] = null;
        $preparedData['qty'] = null;
        $preparedData['get_item_id'] = null;
        $preparedData['get_qty'] = null;

        // Then set only the fields needed for the current type
        if ($data['type'] == 1) {
            $preparedData['item_id'] = $data['item_id'];
            $preparedData['discount'] = $data['discount'];
        } elseif ($data['type'] == 2) {
            $preparedData['discount'] = $data['discount'];
            $preparedData['coupon_code'] = $data['coupon_code'];
        } elseif ($data['type'] == 3) {
            $preparedData['item_id'] = $data['item_id'];
            $preparedData['qty'] = $data['qty'];
            $preparedData['get_item_id'] = $data['get_item_id'];
            $preparedData['get_qty'] = $data['get_qty'];
        }

        if ($isUpdate) {
            $preparedData['updated_at'] = now();
        } else {
            $preparedData['del_status'] = 'Live';
        }

        return $preparedData;
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId(): int
    {
        return session('company.company_id');
    }

    /**
     * Get current outlet ID from session
     */
    protected function getOutletId(): int
    {
        return session('outlet.outlet_id');
    }
}

