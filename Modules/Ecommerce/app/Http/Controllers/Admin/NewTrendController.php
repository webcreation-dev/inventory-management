<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\UpdatesEcommerceSortOrder;
use Modules\Ecommerce\Models\EcommerceNewTrend;
use Modules\Stock\Models\Item;

class NewTrendController extends Controller
{
    use BuildsEcommerceDataTable;
    use UpdatesEcommerceSortOrder;

    public function index(): View
    {
        $rows = EcommerceNewTrend::query()
            ->with('item')
            ->forCompany($this->companyId())
            ->live()
            ->orderBy('sort_id')
            ->orderBy('id')
            ->get();

        $items = $rows->map(function (EcommerceNewTrend $row) {
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
                'edit_url' => route('ecommerce.admin.new-trends.edit', $row->id),
                'destroy_url' => route('ecommerce.admin.new-trends.destroy', $row->id),
            ];
        });

        return view('ecommerce::admin.new-trends.index', compact('items'));
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        return $this->updateSortOrderFor($request, EcommerceNewTrend::class);
    }

    public function create(): View
    {
        return view('ecommerce::admin.new-trends.form', [
            'row' => new EcommerceNewTrend(['sort_id' => $this->nextSortId(EcommerceNewTrend::class)]),
            'items' => $this->productOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (EcommerceNewTrend::query()->forCompany($this->companyId())->live()->where('item_id', $data['item_id'])->exists()) {
            return back()->withInput()->with('error', __('This product is already in New Trend.'));
        }

        if (! isset($data['sort_id']) || (int) $data['sort_id'] === 0) {
            $data['sort_id'] = $this->nextSortId(EcommerceNewTrend::class);
        }

        EcommerceNewTrend::query()->create(array_merge($data, [
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'del_status' => 'Live',
        ]));

        return redirect()->route('ecommerce.admin.new-trends.index')
            ->with('success', __('New Trend product added successfully.'));
    }

    public function edit(int $new_trend): View
    {
        return view('ecommerce::admin.new-trends.form', [
            'row' => $this->findLive($new_trend),
            'items' => $this->productOptions(),
        ]);
    }

    public function update(Request $request, int $new_trend): RedirectResponse
    {
        $row = $this->findLive($new_trend);
        $data = $this->validated($request);

        $exists = EcommerceNewTrend::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('item_id', $data['item_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('This product is already in New Trend.'));
        }

        $row->update($data);

        return redirect()->route('ecommerce.admin.new-trends.index')
            ->with('success', __('New Trend product updated successfully.'));
    }

    public function destroy(int $new_trend): RedirectResponse
    {
        $this->findLive($new_trend)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.new-trends.index')
            ->with('success', __('New Trend product removed successfully.'));
    }

    protected function findLive(int $id): EcommerceNewTrend
    {
        return EcommerceNewTrend::query()->forCompany($this->companyId())->live()->findOrFail($id);
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
