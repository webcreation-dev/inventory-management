<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Models\EcommerceDeliveryArea;

class DeliveryAreaController extends Controller
{
    use BuildsEcommerceDataTable;

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('ecommerce::admin.delivery-areas.index');
    }

    protected function dataTable(): JsonResponse
    {
        $params = $this->dataTableParams();
        $base = EcommerceDeliveryArea::query()->forCompany($this->companyId())->live();

        $recordsTotal = (clone $base)->count();
        $query = (clone $base)->orderByDesc('id');

        if ($params['search'] !== '') {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($params['start'])->take($params['length'])->get();
        $startingNumber = $recordsFiltered - $params['start'];

        $data = $rows->map(function (EcommerceDeliveryArea $area, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $area->id,
                'name' => $area->name,
                'amount' => number_format((float) $area->amount, 2),
                'description' => $area->description ? \Illuminate\Support\Str::limit(strip_tags($area->description), 80) : '-',
                'status' => $area->status,
            ];
        });

        return $this->dataTableResponse($params['draw'], $recordsTotal, $recordsFiltered, $data);
    }

    public function create(): View
    {
        return view('ecommerce::admin.delivery-areas.form', [
            'area' => new EcommerceDeliveryArea(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['company_id'] = $this->companyId();
        $data['user_id'] = auth()->id();
        $data['del_status'] = 'Live';

        EcommerceDeliveryArea::query()->create($data);

        return redirect()->route('ecommerce.admin.delivery-areas.index')
            ->with('success', __('Delivery area created successfully.'));
    }

    public function edit(int $delivery_area): View
    {
        return view('ecommerce::admin.delivery-areas.form', [
            'area' => $this->findLive($delivery_area),
        ]);
    }

    public function update(Request $request, int $delivery_area): RedirectResponse
    {
        $model = $this->findLive($delivery_area);
        $model->update($this->validated($request));

        return redirect()->route('ecommerce.admin.delivery-areas.index')
            ->with('success', __('Delivery area updated successfully.'));
    }

    public function destroy(int $delivery_area): RedirectResponse
    {
        $this->findLive($delivery_area)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.delivery-areas.index')
            ->with('success', __('Delivery area deleted successfully.'));
    }

    protected function findLive(int $id): EcommerceDeliveryArea
    {
        return EcommerceDeliveryArea::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:Active,Inactive'],
        ], [
            'name.required' => __('Please enter a name.'),
            'amount.required' => __('Please enter an amount.'),
            'amount.numeric' => __('Amount must be a number.'),
            'amount.min' => __('Amount cannot be negative.'),
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
        ]);
    }
}
