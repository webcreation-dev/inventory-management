<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sale\Services\SaleService;
use Illuminate\Http\Response;

/**
 * Public invoice view by sale (encrypted) ID.
 * No auth or session; all data loaded from DB by sale id for SMS/WhatsApp links.
 */
class PublicInvoiceController extends Controller
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * 
     */
    public function showShort(string $token): Response
    {
        $sale = $this->saleService->getSaleByShortPublicToken($token);

        return $this->renderPublicInvoice($sale);
    }

    /** @param  string  $token  Laravel encrypted sale id */
    public function show(string $token): Response
    {
        $sale = $this->saleService->getSaleByEncryptedId($token);

        return $this->renderPublicInvoice($sale);
    }

    protected function renderPublicInvoice(?\Modules\Sale\Models\Sale $sale): Response
    {
        if (! $sale || $sale->del_status !== 'Live') {
            abort(404, 'Invoice not found');
        }

        $sale->load([
            'customer',
            'customer.state',
            'employee',
            'user',
            'company',
            'outlet',
            'outlet.state',
            'saleDetails' => function ($q) {
                $q->where('del_status', 'Live');
            },
            'saleDetails.item.parent',
            'saleDetails.item.parent.brand',
            'saleDetails.item.brand',
            'saleDetails.comboSales' => fn ($q) => $q->where('del_status', 'Live'),
            'saleDetails.comboSales.comboItem.parent',
            'saleDetails.comboSales.comboItem.brand',
            'salePayments' => function ($q) {
                $q->where('del_status', 'Live');
            },
            'salePayments.paymentMethod',
        ]);

        $company = $sale->company;
        if (!$company) {
            abort(404, 'Invoice not found');
        }

        return response()
            ->view('sale::sale.public-80mm-invoice', compact('sale', 'company'))
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex');
    }
}
