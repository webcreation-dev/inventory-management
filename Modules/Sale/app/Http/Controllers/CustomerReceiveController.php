<?php

namespace Modules\Sale\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sale\Http\Request\CustomerReceiveRequest;
use Modules\Sale\Models\CustomerReceive;
use Modules\Sale\Services\CustomerReceiveService;
use Modules\Sale\Services\CustomerService;

class CustomerReceiveController extends Controller
{
    protected $customerReceiveService;

    protected $customerService;

    public function __construct(CustomerReceiveService $customerReceiveService, CustomerService $customerService)
    {
        $this->customerReceiveService = $customerReceiveService;
        $this->customerService = $customerService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            $length = request()->length ?? 10;
            $start = request()->start ?? 0;
            $search = request()->search['value'] ?? '';
            $draw = request()->draw;

            return response()->json(
                $this->customerReceiveService->getDataTableData($start, $length, $search, $draw)
            );
        }

        return view('sale::customer-receive.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $data = $this->customerReceiveService->getFormData();
        return view('sale::customer-receive.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerReceiveRequest $request)
    {
        try {
            $this->customerReceiveService->createCustomerReceive($request->validated());

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Created Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.create')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try {
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $data = $this->customerReceiveService->getFormData($customerReceive);
            return view('sale::customer-receive.create', $data);
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.index')
                ->with('error', 'Customer Receive not found');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerReceiveRequest $request, string $id)
    {
        try {
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $this->customerReceiveService->updateCustomerReceive($customerReceive, $request->validated());

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Updated Successfully');
        } catch (\Exception $e) {
            $decryptedId = decrypt($id);
            return redirect()->route('customer-receive.edit', encrypt($decryptedId))
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($id);

            if (!$customerReceive) {
                return redirect()->route('customer-receive.index')
                    ->with('error', 'Customer Receive not found');
            }

            $this->customerReceiveService->deleteCustomerReceive($customerReceive);

            return redirect()->route('customer-receive.index')
                ->with('success', 'Customer Receive Deleted Successfully');
        } catch (\Exception $e) {
            return redirect()->route('customer-receive.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get the data for the data table.
     */
    public function printReceiveInvoice($invoice_id)
    {
        $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($invoice_id);

        if (!$customerReceive) {
            return redirect()->route('customer-receive.index')
                ->with('error', 'Customer Receive not found');
        }

        $customerCurrentDue = $this->balanceAfterPaymentForDisplay($customerReceive);

        return view('sale::customer-receive.receive-invoice', compact('customerReceive', 'customerCurrentDue'));
    }


    /**
     * Get the data for the data table.
     */
    public function downloadReceiveInvoice($invoice_id)
    {
        $customerReceive = $this->customerReceiveService->getCustomerReceiveByEncryptedId($invoice_id);

        if (!$customerReceive) {
            return redirect()->route('customer-receive.index')
                ->with('error', 'Customer Receive not found');
        }

        $customerReceive->loadMissing(['outlet', 'customer', 'payment', 'user']);

        $invoiceLogoPath = null;
        $invoiceLogo = session('company.invoice_logo');
        if (is_string($invoiceLogo) && $invoiceLogo !== '') {
            $absLogo = public_path('uploads/site_settings/' . $invoiceLogo);
            if (is_file($absLogo) && filesize($absLogo) > 0) {
                $invoiceLogoPath = str_replace('\\', '/', $absLogo);
            }
        }

        $mpdfTemp = storage_path('app/mpdf-tmp');
        if (! is_dir($mpdfTemp)) {
            mkdir($mpdfTemp, 0755, true);
        }

        $pdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_header' => 0,
            'margin_footer' => 0,
            'default_font_size' => 11,
            'tempDir' => $mpdfTemp,
        ]);
        $customerCurrentDue = $this->balanceAfterPaymentForDisplay($customerReceive);

        $pdf->WriteHTML(
            view('sale::customer-receive.receive-invoice-pdf', compact('customerReceive', 'invoiceLogoPath', 'customerCurrentDue'))->render()
        );
        $filename = 'Customer Receive Invoice - ' . $customerReceive->reference_no . '.pdf';
        $content = $pdf->Output('', 'S');
        return response($content, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($content));
    }

    /**
     * Stored balance-after-receipt when present; otherwise live ledger (legacy rows).
     */
    protected function balanceAfterPaymentForDisplay(CustomerReceive $customerReceive): ?float
    {
        if (! $customerReceive->customer_id) {
            return null;
        }

        if ($customerReceive->current_amount !== null) {
            return (float) $customerReceive->current_amount;
        }

        return $this->resolveCustomerCurrentDue($customerReceive);
    }

    /**
     * Remaining ledger balance after this receipt (scoped to the receive outlet).
     */
    protected function resolveCustomerCurrentDue(CustomerReceive $customerReceive): ?float
    {
        if (! $customerReceive->customer_id) {
            return null;
        }

        return $this->customerService->getCustomerDue(
            (int) $customerReceive->customer_id,
            $customerReceive->outlet_id ? (int) $customerReceive->outlet_id : null
        );
    }

}
