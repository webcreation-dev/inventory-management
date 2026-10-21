<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\UpdatesEcommerceSortOrder;
use Modules\Ecommerce\Models\EcommerceFeaturedProduct;
use Modules\Stock\Models\Item;

class FeaturedProductController extends Controller
{
    use BuildsEcommerceDataTable;
    use UpdatesEcommerceSortOrder;

    public function index(): View
    {
        $rows = EcommerceFeaturedProduct::query()
            ->with('item')
            ->forCompany($this->companyId())
            ->live()
            ->orderBy('sort_id')
            ->orderBy('id')
            ->get();

        $items = $rows->map(function (EcommerceFeaturedProduct $row) {
            $price = $row->item?->sale_price;
            $priceText = $price !== null
                ? (function_exists('formatAmount') ? formatAmount($price) : $price)
                : '-';

            return [
                'id' => $row->id,
                'title' => $row->item->name ?? '-',
                'meta' => __('Price') . ': ' . $priceText,
                'image' => null,
                'status' => $row->status,
                'edit_url' => route('ecommerce.admin.featured.edit', $row->id),
                'destroy_url' => route('ecommerce.admin.featured.destroy', $row->id),
            ];
        });

        return view('ecommerce::admin.featured.index', compact('items'));
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        return $this->updateSortOrderFor($request, EcommerceFeaturedProduct::class);
    }

    public function create(): View
    {
        return view('ecommerce::admin.featured.form', [
            'row' => new EcommerceFeaturedProduct(['sort_id' => $this->nextSortId(EcommerceFeaturedProduct::class)]),
            'items' => $this->productOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (EcommerceFeaturedProduct::query()->forCompany($this->companyId())->live()->where('item_id', $data['item_id'])->exists()) {
            return back()->withInput()->with('error', __('This product is already featured.'));
        }

        if (! isset($data['sort_id']) || (int) $data['sort_id'] === 0) {
            $data['sort_id'] = $this->nextSortId(EcommerceFeaturedProduct::class);
        }

        EcommerceFeaturedProduct::query()->create(array_merge($data, [
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'del_status' => 'Live',
        ]));

        return redirect()->route('ecommerce.admin.featured.index')
            ->with('success', __('Featured product added successfully.'));
    }

    public function edit(int $featured): View
    {
        return view('ecommerce::admin.featured.form', [
            'row' => $this->findLive($featured),
            'items' => $this->productOptions(),
        ]);
    }

    public function update(Request $request, int $featured): RedirectResponse
    {
        $row = $this->findLive($featured);
        $data = $this->validated($request);

        $exists = EcommerceFeaturedProduct::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('item_id', $data['item_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('This product is already featured.'));
        }

        $row->update($data);

        return redirect()->route('ecommerce.admin.featured.index')
            ->with('success', __('Featured product updated successfully.'));
    }

    public function destroy(int $featured): RedirectResponse
    {
        $this->findLive($featured)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.featured.index')
            ->with('success', __('Featured product removed successfully.'));
    }

    protected function findLive(int $id): EcommerceFeaturedProduct
    {
        return EcommerceFeaturedProduct::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function productOptions()
    {
        return Item::query()
            ->where('company_id', $this->companyId())
            ->where('del_status', 'Live')
            ->where('enable_disable_status', 'Enable')
            ->where('parent_id', 0)
            ->where('type', '!=', '0')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'code', 'sale_price']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'sort_id' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);
    }
}
