<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\UpdatesEcommerceSortOrder;
use Modules\Ecommerce\Models\EcommercePopularBrand;
use Modules\Ecommerce\Services\EcommerceMediaService;
use Modules\Stock\Models\Brand;

class PopularBrandController extends Controller
{
    use BuildsEcommerceDataTable;
    use UpdatesEcommerceSortOrder;

    public function __construct(
        protected EcommerceMediaService $media,
    ) {
    }

    public function index(): View
    {
        $rows = EcommercePopularBrand::query()
            ->with('brand')
            ->forCompany($this->companyId())
            ->live()
            ->orderBy('sort_id')
            ->orderBy('id')
            ->get();

        $items = $rows->map(function (EcommercePopularBrand $row) {
            return [
                'id' => $row->id,
                'title' => $row->brand->name ?? '-',
                'meta' => null,
                'image' => $row->image ? $this->media->url($row->image) : null,
                'status' => $row->status,
                'edit_url' => route('ecommerce.admin.brands.edit', $row->id),
                'destroy_url' => route('ecommerce.admin.brands.destroy', $row->id),
            ];
        });

        return view('ecommerce::admin.brands.index', compact('items'));
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        return $this->updateSortOrderFor($request, EcommercePopularBrand::class);
    }

    public function create(): View
    {
        return view('ecommerce::admin.brands.form', [
            'row' => new EcommercePopularBrand(['sort_id' => $this->nextSortId(EcommercePopularBrand::class)]),
            'brands' => $this->brands(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);

        if (EcommercePopularBrand::query()->forCompany($this->companyId())->live()->where('brand_id', $data['brand_id'])->exists()) {
            return back()->withInput()->with('error', __('This brand is already in popular brands.'));
        }

        $data['image'] = $this->media->upload($request->file('image'), 'brands');
        if (! isset($data['sort_id']) || (int) $data['sort_id'] === 0) {
            $data['sort_id'] = $this->nextSortId(EcommercePopularBrand::class);
        }

        EcommercePopularBrand::query()->create(array_merge($data, [
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'del_status' => 'Live',
        ]));

        return redirect()->route('ecommerce.admin.brands.index')
            ->with('success', __('Popular brand added successfully.'));
    }

    public function edit(int $brand): View
    {
        return view('ecommerce::admin.brands.form', [
            'row' => $this->findLive($brand),
            'brands' => $this->brands(),
        ]);
    }

    public function update(Request $request, int $brand): RedirectResponse
    {
        $row = $this->findLive($brand);
        $data = $this->validated($request, ! (bool) $row->image);

        $exists = EcommercePopularBrand::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('brand_id', $data['brand_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('This brand is already in popular brands.'));
        }

        if ($request->hasFile('image')) {
            $this->media->delete($row->image);
            $data['image'] = $this->media->upload($request->file('image'), 'brands');
        }

        $row->update($data);

        return redirect()->route('ecommerce.admin.brands.index')
            ->with('success', __('Popular brand updated successfully.'));
    }

    public function destroy(int $brand): RedirectResponse
    {
        $this->findLive($brand)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.brands.index')
            ->with('success', __('Popular brand removed successfully.'));
    }

    protected function findLive(int $id): EcommercePopularBrand
    {
        return EcommercePopularBrand::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function brands()
    {
        return Brand::query()
            ->where('company_id', $this->companyId())
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function validated(Request $request, bool $imageRequired = true): array
    {
        return $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'sort_id' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
            'image' => [($imageRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ], [
            'brand_id.required' => __('Please select a brand.'),
            'brand_id.exists' => __('Selected brand is invalid.'),
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
            'image.required' => __('Please upload a brand image.'),
            'image.image' => __('Brand image must be an image file.'),
            'image.mimes' => __('Brand image must be a JPG, PNG, WEBP, or GIF file.'),
            'image.max' => __('Brand image may not be greater than 2MB.'),
        ]);
    }
}
