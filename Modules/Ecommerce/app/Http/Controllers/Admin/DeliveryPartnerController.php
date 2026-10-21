<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Models\EcommerceDeliveryPartner;

class DeliveryPartnerController extends Controller
{
    use BuildsEcommerceDataTable;

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('ecommerce::admin.delivery-partners.index');
    }

    protected function dataTable(): JsonResponse
    {
        $params = $this->dataTableParams();
        $base = EcommerceDeliveryPartner::query()->forCompany($this->companyId())->live();

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

        $data = $rows->map(function (EcommerceDeliveryPartner $partner, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $partner->id,
                'name' => $partner->name,
                'description' => $partner->description ? \Illuminate\Support\Str::limit(strip_tags($partner->description), 80) : '-',
                'status' => $partner->status,
            ];
        });

        return $this->dataTableResponse($params['draw'], $recordsTotal, $recordsFiltered, $data);
    }

    public function create(): View
    {
        return view('ecommerce::admin.delivery-partners.form', [
            'partner' => new EcommerceDeliveryPartner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['company_id'] = $this->companyId();
        $data['user_id'] = auth()->id();
        $data['del_status'] = 'Live';

        EcommerceDeliveryPartner::query()->create($data);

        return redirect()->route('ecommerce.admin.delivery-partners.index')
            ->with('success', __('Delivery partner created successfully.'));
    }

    public function edit(int $delivery_partner): View
    {
        return view('ecommerce::admin.delivery-partners.form', [
            'partner' => $this->findLive($delivery_partner),
        ]);
    }

    public function update(Request $request, int $delivery_partner): RedirectResponse
    {
        $model = $this->findLive($delivery_partner);
        $model->update($this->validated($request));

        return redirect()->route('ecommerce.admin.delivery-partners.index')
            ->with('success', __('Delivery partner updated successfully.'));
    }

    public function destroy(int $delivery_partner): RedirectResponse
    {
        $this->findLive($delivery_partner)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.delivery-partners.index')
            ->with('success', __('Delivery partner deleted successfully.'));
    }

    protected function findLive(int $id): EcommerceDeliveryPartner
    {
        return EcommerceDeliveryPartner::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:Active,Inactive'],
        ], [
            'name.required' => __('Please enter a name.'),
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
        ]);
    }
}
