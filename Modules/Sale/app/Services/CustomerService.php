<?php

namespace Modules\Sale\Services;

use Modules\Sale\Repositories\CustomerRepository;
use Modules\Sale\Models\Customer;
use Modules\Sale\Models\Sale;
use Modules\Sale\Models\CustomerReceive;
use Modules\Sale\Models\SaleDetail;
use Modules\Sale\Models\SalePayment;
use Modules\Sale\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Get all customers for the current company
     */
    public function getAllCustomers(): Collection
    {
        return $this->customerRepository->all($this->getCompanyId());
    }

    /**
     * Get DataTable data for customers listing
     */
    public function getDataTableData(int $start, int $length, string $search, int $draw, ?string $typeFilter = null): array
    {
        // If type filter is applied, we need to get all customers first to calculate balance
        // Then filter and paginate based on balance type
        $companyId = $this->getCompanyId();
        $outletId = session('outlet.id');
        
        $baseQuery = $this->customerRepository->getBaseQuery($companyId);
        $query = clone $baseQuery;

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        // order by id desc
        $query->orderBy('id', 'desc');

        // Get all matching customers to calculate balance
        $allCustomers = $query->get();
        
        // Calculate balance for each customer and filter by type if needed
        $customersWithBalance = $allCustomers->map(function ($customer) use ($outletId) {
            $balance = $this->getCustomerDue($customer->id, $outletId, false);
            $balanceType = $balance >= 0 ? 'Debit' : 'Credit';
            $balanceAmount = abs($balance);
            
            return [
                'customer' => $customer,
                'balance' => $balance,
                'balanceType' => $balanceType,
                'balanceAmount' => $balanceAmount,
            ];
        });

        // Apply type filter if specified
        if ($typeFilter && in_array($typeFilter, ['Debit', 'Credit'])) {
            $customersWithBalance = $customersWithBalance->filter(function ($item) use ($typeFilter) {
                return $item['balanceType'] === $typeFilter;
            });
        }

        $recordsTotal = $baseQuery->count();
        $filteredCount = $customersWithBalance->count();

        // Apply pagination
        $paginatedCustomers = $customersWithBalance->slice($start, $length);

        // Calculate the starting number for the current page
        $startingNumber = $filteredCount - $start;

        // Transform data for DataTable
        $transformedData = $paginatedCustomers->map(function ($item, $index) use ($startingNumber) {
            $customer = $item['customer'];
            
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'current_balance' => formatAmount($item['balanceAmount']) . ' (' . $item['balanceType'] . ')',
                'balance_amount' => $item['balanceAmount'],
                'balance_type' => $item['balanceType'],
                'encrypted_id' => $customer->encrypted_id,
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData->values(),
        ];
    }

    /**
     * DataTables: customers with amount due (positive balance — customer owes you).
     *
     * @param  bool  $aggregateAllOutletsWhenOutletNull  When true and $dashboardOutletId is null, do not fall back to session outlet (sum all outlets).
     */
    public function getDashboardDueCustomersDataTableData(
        int $start,
        int $length,
        string $search,
        int $draw,
        ?int $dashboardOutletId,
        bool $aggregateAllOutletsWhenOutletNull
    ): array {
        $companyId = $this->getCompanyId();

        $baseQuery = $this->customerRepository->getBaseQuery($companyId)
            ->where('name', '!=', 'Walk-in Customer');

        $recordsTotal = (clone $baseQuery)->count();

        $filteredQuery = clone $baseQuery;
        if ($search !== '') {
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $allCustomers = $filteredQuery->orderBy('id', 'desc')->get();

        $useAggregateOutlets = $aggregateAllOutletsWhenOutletNull && $dashboardOutletId === null;

        $withDue = $allCustomers->map(function ($customer) use ($dashboardOutletId, $useAggregateOutlets) {
            $balance = $this->getCustomerDue($customer->id, $dashboardOutletId, $useAggregateOutlets);

            return [
                'customer' => $customer,
                'balance' => $balance,
            ];
        })->filter(function (array $item) {
            return $item['balance'] > 0;
        })->sortByDesc(function (array $item) {
            return $item['balance'];
        })->values();

        $filteredCount = $withDue->count();
        $pageSlice = $withDue->slice($start, $length)->values();
        $startingNumber = $filteredCount > 0 ? $filteredCount - $start : 0;

        $transformedData = $pageSlice->map(function (array $item, int $index) use ($startingNumber) {
            $customer = $item['customer'];
            $balance = (float) $item['balance'];
            $nameDisplay = $customer->name ?? '';
            if (! empty($customer->phone)) {
                $nameDisplay .= ' (' . $customer->phone . ')';
            }

            return [
                'id' => $startingNumber - $index,
                'name' => $nameDisplay,
                'total_due_amount' => formatAmount($balance),
            ];
        });

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $transformedData->values(),
        ];
    }

    /**
     * Calculate customer current balance based on CodeIgniter logic
     * 
     * @param int $customerId
     * @param int|null $outletId
     * @return float
     */
    public function getCustomerDue(int $customerId, ?int $outletId = null, bool $aggregateAllOutletsWhenOutletNull = false): float
    {
        $companyId = $this->getCompanyId();
        
        // Get customer
        $customer = Customer::where('id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->first();

        if (!$customer) {
            return 0;
        }

        // If customer is "Walk-in Customer", return 0
        if ($customer->name === 'Walk-in Customer') {
            return 0;
        }

        // Get outlet ID from session if not provided (unless aggregating all outlets for dashboard)
        if ($outletId === null && ! $aggregateAllOutletsWhenOutletNull) {
            $outletId = session('outlet.id');
        }

        // Calculate total sale due (from sales)
        // Note: CodeIgniter version filters by delivery_status = 'Cash Received'
        // In Laravel, we sum all due_amount for the customer
        $totalSaleDue = Sale::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        // Check if delivery_status column exists and filter if it does
        // if (DB::getSchemaBuilder()->hasColumn('sales', 'delivery_status')) {
        //     $totalSaleDue->where('delivery_status', 'Cash Received');
        // }
        
        if ($outletId) {
            $totalSaleDue->where('outlet_id', $outletId);
        }
        
        $totalSaleDueAmount = $totalSaleDue->sum('due_amount') ?? 0;

        // Calculate total due received
        $totalDueReceived = CustomerReceive::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $totalDueReceived->where('outlet_id', $outletId);
        }
        
        $totalDueReceivedAmount = $totalDueReceived->sum('amount') ?? 0;

        // Calculate total sale return
        $totalSaleReturn = SaleReturn::where('customer_id', $customerId)
            ->where('company_id', $companyId)
            ->where('del_status', 'Live');
        
        if ($outletId) {
            $totalSaleReturn->where('outlet_id', $outletId);
        }
        $totalSaleReturnDueAmount = $totalSaleReturn->sum('due') ?? 0;

        // Calculate opening balance based on type
        $openingBalance = floatval($customer->opening_balance ?? 0);
        $openingBalanceType = $customer->opening_balance_type ?? 'Debit';

        if ($openingBalanceType == 'Credit') {
            $balance = -$openingBalance - $totalDueReceivedAmount + $totalSaleDueAmount - $totalSaleReturnDueAmount;
        } else {
            $balance = $openingBalance - $totalDueReceivedAmount + $totalSaleDueAmount - $totalSaleReturnDueAmount;
        }

        return $balance;
    }

    /**
     * Get customer by encrypted ID
     */
    public function getCustomerByEncryptedId(string $encryptedId): ?Customer
    {
        return $this->customerRepository->findByEncryptedId($encryptedId);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(string $id): ?Customer
    {
        return $this->customerRepository->findById($id);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        // Check if trying to create "Walk-in Customer" and one already exists
        if (isset($data['name']) && $data['name'] === 'Walk-in Customer') {
            $existingWalkIn = Customer::where('name', 'Walk-in Customer')
                ->where('company_id', $this->getCompanyId())
                ->where('del_status', 'Live')
                ->first();
            
            if ($existingWalkIn) {
                throw new \Exception('A "Walk-in Customer" already exists for this company. Only one "Walk-in Customer" is allowed per company.');
            }
        }
        
        $storeData = $this->prepareCustomerData($data);
        return $this->customerRepository->create($storeData);
    }

    /**
     * Update an existing customer
     */
    public function updateCustomer(Customer $customer, array $data): bool
    {
        // Prevent changing name if customer is "Walk-in Customer"
        if ($customer->name === 'Walk-in Customer' && isset($data['name']) && $data['name'] !== 'Walk-in Customer') {
            throw new \Exception('The name "Walk-in Customer" cannot be changed.');
        }
        
        $updateData = $this->prepareCustomerData($data, true, $customer);
        return $this->customerRepository->update($customer, $updateData);
    }

    /**
     * Delete a customer
     */
    public function deleteCustomer(Customer $customer): bool
    {
        // Prevent deletion of "Walk-in Customer"
        if ($customer->name === 'Walk-in Customer') {
            throw new \Exception('"Walk-in Customer" cannot be deleted.');
        }
        
        return $this->customerRepository->delete($customer);
    }

    /**
     * Get active customers for dropdown
     */
    public function getActiveCustomers(): Collection
    {
        return $this->customerRepository->getActiveCustomers($this->getCompanyId());
    }

    /**
     * Search customers by name
     */
    public function searchCustomers(string $name): Collection
    {
        return $this->customerRepository->searchByName($this->getCompanyId(), $name);
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStatistics(): array
    {
        $companyId = $this->getCompanyId();
        return [
            'total' => $this->customerRepository->getTotalCount($companyId),
            'with_balance' => $this->customerRepository->getWithOutstandingBalance($companyId)->count(),
        ];
    }

    /**
     * Available loyalty points for a customer: sum(loyalty_point_earn on sale lines) − sum(usage_point on loyalty redemptions).
     * Matches Reports → Available Loyalty Point (scoped by outlet when provided).
     */
    public function getAvailableLoyaltyPoints(int $customerId, ?int $outletId, int $companyId): float
    {
        $earnedQuery = SaleDetail::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->whereHas('sale', function ($q) use ($customerId, $outletId, $companyId) {
                $q->where('customer_id', $customerId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live');
                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
            });

        $totalEarned = (float) $earnedQuery->sum('loyalty_point_earn');

        $usedQuery = SalePayment::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('usage_point', '>', 0)
            ->whereHas('sale', function ($q) use ($customerId, $outletId, $companyId) {
                $q->where('customer_id', $customerId)
                    ->where('company_id', $companyId)
                    ->where('del_status', 'Live');
                if ($outletId) {
                    $q->where('outlet_id', $outletId);
                }
            });

        $totalUsed = (float) $usedQuery->sum('usage_point');

        return round(max(0, $totalEarned - $totalUsed), 3);
    }

    /**
     * Prepare customer data for store/update
     */
    protected function prepareCustomerData(array $data, bool $isUpdate = false, ?Customer $customer = null): array
    {
        $preparedData = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'discount' => isset($data['discount']) && $data['discount'] !== '' ? $data['discount'] : null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'date_of_anniversary' => $data['date_of_anniversary'] ?? null,
            'gst_number' => ! empty($data['gst_number'])
                ? \App\Helpers\GstinValidator::normalize($data['gst_number'])
                : null,
            'customer_type' => isset($data['customer_type']) && $data['customer_type'] !== '' ? $data['customer_type'] : null,
            'same_or_diff_state' => $data['same_or_diff_state'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'business_type' => $data['business_type'] ?? 'B2C',
            'user_id' => Auth::id(),
            'company_id' => $this->getCompanyId(),
        ];

        if ($isUpdate && $customer) {
            $preparedData['opening_balance'] = !empty($data['opening_balance']) 
                ? $data['opening_balance'] 
                : $customer->opening_balance;
            $preparedData['opening_balance_type'] = !empty($data['opening_balance_type']) 
                ? $data['opening_balance_type'] 
                : $customer->opening_balance_type;
            $preparedData['updated_at'] = now();
        } else {
            $preparedData['opening_balance'] = !empty($data['opening_balance']) 
                ? $data['opening_balance'] 
                : 0;
            $preparedData['opening_balance_type'] = !empty($data['opening_balance_type']) 
                ? $data['opening_balance_type'] 
                : 'Debit';
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
}

