<?php

namespace Modules\Stock\Services;

use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Repositories\ItemCategoryRepository;
use Illuminate\Support\Facades\Auth;

class ItemCategoryService
{
    protected $itemCategoryRepository;

    /**
     * ItemCategoryService constructor.
     *
     * @param ItemCategoryRepository $itemCategoryRepository
     */
    public function __construct(ItemCategoryRepository $itemCategoryRepository)
    {
        $this->itemCategoryRepository = $itemCategoryRepository;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->itemCategoryRepository->getDataTableData($params);
    }

    /**
     * Get all item categories with pagination
     */
    public function getAllItemCategories($perPage = 10)
    {
        return $this->itemCategoryRepository->paginate($perPage);
    }

    /**
     * Get active item categories
     */
    public function getActiveItemCategories()
    {
        return $this->itemCategoryRepository->getActiveItemCategories();
    }

    /**
     * Get item category by encrypted ID
     */
    public function getItemCategoryByEncryptedId(string $encryptedId): ?ItemCategory
    {
        return $this->itemCategoryRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Create a new item category
     */
    public function createItemCategory(array $data)
    {
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');
        $data['del_status'] = 'Live';

        if (isset($data['banner']) && $data['banner']) {
            $data['banner'] = $this->storeBanner($data['banner']);
        } else {
            unset($data['banner']);
        }
        unset($data['remove_banner']);

        return $this->itemCategoryRepository->create($data);
    }

    /**
     * Update an existing item category
     */
    public function updateItemCategory(string $encryptedId, array $data)
    {
        $itemCategory = $this->itemCategoryRepository->findByEncryptedId($encryptedId);
        if (!$itemCategory) {
            throw new \Exception('Item Category not found');
        }
        $data['user_id'] = Auth::id();
        $data['company_id'] = session('company.company_id');

        if (! empty($data['remove_banner'])) {
            if ($itemCategory->banner) {
                $this->deleteBanner($itemCategory->banner);
            }
            $data['banner'] = null;
        } elseif (isset($data['banner']) && $data['banner']) {
            if ($itemCategory->banner) {
                $this->deleteBanner($itemCategory->banner);
            }
            $data['banner'] = $this->storeBanner($data['banner']);
        } else {
            unset($data['banner']);
        }
        unset($data['remove_banner']);

        return $this->itemCategoryRepository->update($itemCategory, $data);
    }

    /**
     * Delete a item category
     */
    public function deleteItemCategory(string $encryptedId)
    {
        $itemCategory = $this->itemCategoryRepository->findByEncryptedId($encryptedId);
        if (!$itemCategory) {
            throw new \Exception('Item Category not found');
        }
        if ($itemCategory->banner) {
            $this->deleteBanner($itemCategory->banner);
        }
        return $this->itemCategoryRepository->delete($itemCategory);
    }

    /**
     * Toggle item category status
     */
    public function toggleItemCategoryStatus(string $encryptedId)
    {
        $itemCategory = $this->itemCategoryRepository->findByEncryptedId($encryptedId);
        if (!$itemCategory) {
            throw new \Exception('Item Category not found');
        }
        return $this->itemCategoryRepository->update($itemCategory, [
            'status' => !$itemCategory->status,
            'user_id' => Auth::id()
        ]);
    }

    /**
     * Store optional category banner.
     */
    private function storeBanner($banner): string
    {
        $dir = public_path('uploads/categories');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $bannerName = time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $banner->getClientOriginalName());
        $banner->move($dir, $bannerName);

        return $bannerName;
    }

    /**
     * Delete category banner file.
     */
    private function deleteBanner(?string $bannerName): void
    {
        if (! $bannerName) {
            return;
        }

        $path = public_path('uploads/categories/' . $bannerName);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Get item category statistics
     */
    public function getItemCategoryStatistics()
    {
        return [
            'total' => $this->itemCategoryRepository->getTotalCount(),
        ];
    }

    /**
     * Get categories for sorting (ordered by sort_id, then by id)
     */
    public function getCategoriesForSorting()
    {
        try {
            return $this->itemCategoryRepository->getCategoriesForSorting();
        } catch (\Exception $e) {
            \Log::error('Error getting categories for sorting: ' . $e->getMessage());
            return collect([]);
        }
    }

    /**
     * Update sort order for categories
     */
    public function updateSortOrder(array $sortedIds)
    {
        return $this->itemCategoryRepository->updateSortOrder($sortedIds);
    }
}
