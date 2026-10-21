<?php

namespace Modules\Sale\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sale\Models\Sale;
use Modules\Sale\Repositories\SaleRepository;

class SaleService
{
    protected $saleRepository;

    public function __construct(SaleRepository $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }

    /**
     * Get all sales for a company.
     */
    public function getAllSales(int $companyId, int $perPage = 15)
    {
        return $this->saleRepository->getAllForCompany($companyId, $perPage);
    }

    /**
     * Get a sale by ID with its details.
     */
    public function getSaleById(int $id)
    {
        return $this->saleRepository->getByIdWithDetails($id);
    }

    /**
     * Get a sale by encrypted ID with its details.
     */
    public function getSaleByEncryptedId(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
            return $this->saleRepository->getByIdWithDetails((int)$id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Compact signed token for SMS (15 chars). Resolves same invoice as encrypted link without long URLs.
     */
    public function makeShortPublicInvoiceToken(Sale $sale): string
    {
        $idPart = str_pad(strtolower(base_convert((string) $sale->id, 10, 36)), 7, '0', STR_PAD_LEFT);
        $check = substr(hash_hmac('sha256', $idPart, config('app.key')), 0, 8);

        return $idPart.$check;
    }

    /**
     * Load sale by short public invoice token; returns null if invalid or tampered.
     */
    public function getSaleByShortPublicToken(string $token): ?Sale
    {
        $token = strtolower(preg_replace('/[^0-9a-z]/', '', $token));
        if (strlen($token) !== 15) {
            return null;
        }
        $idPart = substr($token, 0, 7);
        $check = substr($token, 7, 8);
        $expected = substr(hash_hmac('sha256', $idPart, config('app.key')), 0, 8);
        if (! hash_equals($expected, $check)) {
            return null;
        }
        $id = (int) base_convert($idPart, 36, 10);
        if ($id < 1) {
            return null;
        }

        return $this->saleRepository->getByIdWithDetails($id);
    }

    /**
     * Delete a sale and cascade soft-delete to sale details and sale payments.
     */
    public function deleteSale(string $encryptedId)
    {
        try {
            $id = decrypt($encryptedId);
        } catch (\Exception $e) {
            throw new \Exception('Invalid sale ID');
        }
        
        $sale = Sale::find((int)$id);
        if (!$sale) {
            throw new \Exception('Sale not found');
        }
        
        DB::transaction(function () use ($sale) {
            // Soft delete - mark sale as deleted
            $sale->update(['del_status' => 'Deleted']);
            // Cascade: mark sale details as deleted
            $sale->saleDetails()->update(['del_status' => 'Deleted']);
            // Cascade: mark sale payments as deleted
            $sale->salePayments()->update(['del_status' => 'Deleted']);
        });
        
        return true;
    }

    /**
     * Get data for DataTables
     */
    public function getDataTableData(array $params): array
    {
        return $this->saleRepository->getDataTableData($params);
    }
}
