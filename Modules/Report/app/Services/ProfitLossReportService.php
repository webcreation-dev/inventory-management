<?php

namespace Modules\Report\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Modules\Accounting\Models\Expense;
use Modules\Accounting\Models\Income;
use Modules\Administrator\Models\Salary;
use Modules\Sale\Models\InstallmentSale;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\SaleDetail;
use Modules\Sale\Models\SaleReturn;
use Modules\Sale\Models\SaleReturnDetail;
use Modules\Sale\Models\Servicing;

class ProfitLossReportService
{
    /**
     * Calculate profit/loss figures using the same formula as the Profit Loss Report.
     */
    public function calculate(
        int $companyId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $outletId = null,
        string $costingMethod = 'last_purchase_price'
    ): array {
        $totalSales = 0.0;
        $totalCostOfSale = 0.0;
        $totalTax = 0.0;
        $totalDeliveryService = 0.0;
        $totalDiscount = 0.0;
        $totalInstallmentSale = 0.0;
        $totalIncome = 0.0;
        $totalSaleReturn = 0.0;
        $totalCostOfSaleReturn = 0.0;
        $totalServicing = 0.0;
        $totalSalaries = 0.0;
        $totalExpense = 0.0;

        $salesQuery = Sale::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($outletId) {
            $salesQuery->where('outlet_id', $outletId);
        }
        if ($dateFrom) {
            $salesQuery->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salesQuery->whereDate('sale_date', '<=', $dateTo);
        }

        $totalSales = floatval($salesQuery->sum('total_payable'));

        $saleDetailsQuery = SaleDetail::with('item')
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereHas('sale', function ($q) use ($dateFrom, $dateTo, $outletId, $companyId) {
                $q->where('del_status', 'Live')
                    ->where('company_id', $companyId);

                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
                if ($dateFrom) {
                    $q->whereDate('sale_date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('sale_date', '<=', $dateTo);
                }
            });

        if ($outletId) {
            $saleDetailsQuery->where('outlet_id', $outletId);
        }

        foreach ($saleDetailsQuery->get() as $saleDetail) {
            $quantity = floatval($saleDetail->qty ?? 0);
            $costingPrice = $this->resolveCostingPrice($saleDetail->item, $costingMethod);
            $totalCostOfSale += $quantity * $costingPrice;
        }

        $totalTax = floatval($salesQuery->sum('vat'));
        $totalDeliveryService = floatval($salesQuery->sum('delivery_charge'));
        $totalDiscount = floatval($salesQuery->sum('total_discount_amount'));

        $installmentSalesQuery = InstallmentSale::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($outletId) {
            $installmentSalesQuery->where('outlet_id', $outletId);
        }
        if ($dateFrom) {
            $installmentSalesQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $installmentSalesQuery->whereDate('date', '<=', $dateTo);
        }

        foreach ($installmentSalesQuery->get() as $installmentSale) {
            $total = floatval($installmentSale->total ?? 0);
            $shippingOther = floatval($installmentSale->shipping_other ?? 0);
            $interestAmount = floatval($installmentSale->interest_amount ?? 0);
            $discountAmount = floatval($installmentSale->discount_amount ?? 0);

            $totalInstallmentSale += ($total + $shippingOther + $interestAmount - $discountAmount);
        }

        $incomeQuery = Income::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($dateFrom) {
            $incomeQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $incomeQuery->whereDate('date', '<=', $dateTo);
        }

        $totalIncome = floatval($incomeQuery->sum('amount'));

        $saleReturnQuery = SaleReturn::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($outletId) {
            $saleReturnQuery->where('outlet_id', $outletId);
        }
        if ($dateFrom) {
            $saleReturnQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $saleReturnQuery->whereDate('date', '<=', $dateTo);
        }

        $totalSaleReturn = floatval($saleReturnQuery->sum('total_return_amount'));

        $saleReturnDetailsQuery = SaleReturnDetail::with('item')
            ->where('del_status', 'Live')
            ->where('company_id', $companyId)
            ->whereHas('saleReturn', function ($q) use ($dateFrom, $dateTo, $outletId, $companyId) {
                $q->where('del_status', 'Live')
                    ->where('company_id', $companyId);

                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
                if ($dateFrom) {
                    $q->whereDate('date', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $q->whereDate('date', '<=', $dateTo);
                }
            });

        if ($outletId) {
            $saleReturnDetailsQuery->where('outlet_id', $outletId);
        }

        foreach ($saleReturnDetailsQuery->get() as $saleReturnDetail) {
            $quantity = floatval($saleReturnDetail->return_quantity_amount ?? 0);
            $costingPrice = $this->resolveCostingPrice($saleReturnDetail->item, $costingMethod);
            $totalCostOfSaleReturn += $quantity * $costingPrice;
        }

        $servicingQuery = Servicing::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($outletId) {
            $servicingQuery->where('outlet_id', $outletId);
        }
        if ($dateFrom) {
            $servicingQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $servicingQuery->whereDate('date', '<=', $dateTo);
        }

        $totalServicing = floatval($servicingQuery->sum('servicing_charge'));

        $grossProfit = ($totalSales + $totalDeliveryService + $totalInstallmentSale + $totalIncome + $totalServicing)
            - ($totalCostOfSale + $totalTax + $totalDiscount + $totalSaleReturn + $totalCostOfSaleReturn);

        $salaryQuery = Salary::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($dateFrom) {
            $salaryQuery->whereDate('generated_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $salaryQuery->whereDate('generated_date', '<=', $dateTo);
        }

        $totalSalaries = floatval($salaryQuery->sum('total_amount'));

        $expenseQuery = Expense::where('del_status', 'Live')
            ->where('company_id', $companyId);

        if ($dateFrom) {
            $expenseQuery->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expenseQuery->whereDate('date', '<=', $dateTo);
        }

        $totalExpense = floatval($expenseQuery->sum('amount'));

        $netProfit = $grossProfit - ($totalSalaries + $totalExpense);

        return [
            'total_sales' => $totalSales,
            'total_cost_of_sale' => $totalCostOfSale,
            'tax' => $totalTax,
            'delivery_service' => $totalDeliveryService,
            'discount' => $totalDiscount,
            'installment_sale' => $totalInstallmentSale,
            'income' => $totalIncome,
            'sale_return' => $totalSaleReturn,
            'cost_of_sale_return' => $totalCostOfSaleReturn,
            'servicing' => $totalServicing,
            'gross_profit' => $grossProfit,
            'total_salaries' => $totalSalaries,
            'expense' => $totalExpense,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Build trend points for the profit line chart.
     */
    public function calculateProfitTrend(
        int $companyId,
        string $dateFrom,
        string $dateTo,
        ?int $outletId = null,
        int $points = 6,
        string $costingMethod = 'last_purchase_price'
    ): array {
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->endOfDay();
        $totalDays = max(1, $start->diffInDays($end) + 1);
        $segmentDays = max(1, (int) ceil($totalDays / $points));
        $trend = [];

        $cursor = $start->copy();
        while ($cursor <= $end && count($trend) < $points) {
            $segmentEnd = $cursor->copy()->addDays($segmentDays - 1);
            if ($segmentEnd > $end) {
                $segmentEnd = $end->copy();
            }

            $result = $this->calculate(
                $companyId,
                $cursor->toDateString(),
                $segmentEnd->toDateString(),
                $outletId,
                $costingMethod
            );

            $trend[] = [
                'label' => $cursor->format('M d'),
                'value' => floatval($result['net_profit']),
            ];

            $cursor = $segmentEnd->copy()->addDay();
        }

        return $trend;
    }

    /**
     * Build daily net-profit values for the earning bar chart.
     */
    public function calculateDailyNetProfit(
        int $companyId,
        string $dateFrom,
        string $dateTo,
        ?int $outletId = null,
        int $days = 7,
        string $costingMethod = 'last_purchase_price'
    ): array {
        $end = Carbon::parse($dateTo)->startOfDay();
        $start = Carbon::parse($dateFrom)->startOfDay();
        $chartStart = $end->copy()->subDays($days - 1);

        if ($chartStart < $start) {
            $chartStart = $start->copy();
        }

        $daily = [];
        foreach (CarbonPeriod::create($chartStart, $end) as $date) {
            $result = $this->calculate(
                $companyId,
                $date->toDateString(),
                $date->toDateString(),
                $outletId,
                $costingMethod
            );

            $daily[] = [
                'day' => $date->format('D'),
                'value' => floatval($result['net_profit']),
            ];
        }

        return $daily;
    }

    private function resolveCostingPrice($item, string $costingMethod): float
    {
        if (!$item) {
            return 0.0;
        }

        if ($costingMethod === 'last_three_purchase_avg') {
            return floatval($item->last_three_purchase_avg ?? 0);
        }

        return floatval($item->last_purchase_price ?? 0);
    }
}
