<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\UpdatesEcommerceSortOrder;
use Modules\Ecommerce\Models\EcommercePopularProduct;
use Modules\Stock\Models\Item;

class PopularProductController extends Controller
{
    use BuildsEcommerceDataTable;
    use UpdatesEcommerceSortOrder;

    public function index(): View
    {
        $rows = EcommercePopularProduct::query()
            ->with('item')
            ->forCompany($this->companyId())
            ->live()
            ->orderBy('sort_id')
            ->orderBy('id')
            ->get();

        $items = $rows->map(function (EcommercePopularProduct $row) {
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
                'edit_url' => route('ecommerce.admin.popular-products.edit', $row->id),
                'destroy_url' => route('ecommerce.admin.popular-products.destroy', $row->id),
            ];
        });

        return view('ecommerce::admin.popular-products.index', compact('items'));
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        return $this->updateSortOrderFor($request, EcommercePopularProduct::class);
    }

    public function create(): View
    {
        return view('ecommerce::admin.popular-products.form', [
            'row' => new EcommercePopularProduct(['sort_id' => $this->nextSortId(EcommercePopularProduct::class)]),
            'items' => $this->productOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (EcommercePopularProduct::query()->forCompany($this->companyId())->live()->where('item_id', $data['item_id'])->exists()) {
            return back()->withInput()->with('error', __('This product is already in Most Popular.'));
        }

        if (! isset($data['sort_id']) || (int) $data['sort_id'] === 0) {
            $data['sort_id'] = $this->nextSortId(EcommercePopularProduct::class);
        }

        EcommercePopularProduct::query()->create(array_merge($data, [
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'del_status' => 'Live',
        ]));

        return redirect()->route('ecommerce.admin.popular-products.index')
            ->with('success', __('Most Popular product added successfully.'));
    }

    public function edit(int $popular_product): View
    {
        return view('ecommerce::admin.popular-products.form', [
            'row' => $this->findLive($popular_product),
            'items' => $this->productOptions(),
        ]);
    }

    public function update(Request $request, int $popular_product): RedirectResponse
    {
        $row = $this->findLive($popular_product);
        $data = $this->validated($request);

        $exists = EcommercePopularProduct::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('item_id', $data['item_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('This product is already in Most Popular.'));
        }

        $row->update($data);

        return redirect()->route('ecommerce.admin.popular-products.index')
            ->with('success', __('Most Popular product updated successfully.'));
    }

    public function destroy(int $popular_product): RedirectResponse
    {
        $this->findLive($popular_product)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.popular-products.index')
            ->with('success', __('Most Popular product removed successfully.'));
    }

    protected function findLive(int $id): EcommercePopularProduct
    {
        return EcommercePopularProduct::query()->forCompany($this->companyId())->live()->findOrFail($id);
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
