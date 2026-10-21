<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Stock\Models\ItemCategory;

class HeaderCategoryController extends Controller
{
    public function edit(): View
    {
        $companyId = (int) session('company.company_id');

        $categories = ItemCategory::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->where('del_status', 'Live')
            ->withCount([
                'subCategories as sub_categories_count' => fn ($q) => $q->live()
                    ->when($companyId > 0, fn ($sq) => $sq->where('company_id', $companyId)),
            ])
            ->orderByRaw('COALESCE(header_sort_id, 999999) ASC')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get(['id', 'name', 'show_in_header', 'header_sort_id', 'sort_id']);

        return view('ecommerce::admin.header-categories.edit', compact('categories'));
    }

    public function update(Request $request): RedirectResponse
    {
        $companyId = (int) session('company.company_id');

        $validated = $request->validate([
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:item_categories,id'],
            'sorted_ids' => ['nullable', 'array'],
            'sorted_ids.*' => ['integer', 'exists:item_categories,id'],
        ]);

        $selectedIds = collect($validated['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sortedIds = collect($validated['sorted_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $base = ItemCategory::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->where('del_status', 'Live');

        DB::transaction(function () use ($base, $selectedIds, $sortedIds) {
            (clone $base)->update(['show_in_header' => false]);

            if ($selectedIds !== []) {
                (clone $base)->whereIn('id', $selectedIds)->update(['show_in_header' => true]);
            }

            if ($sortedIds !== []) {
                foreach ($sortedIds as $index => $categoryId) {
                    (clone $base)->where('id', $categoryId)->update([
                        'header_sort_id' => $index + 1,
                    ]);
                }
            }
        });

        return redirect()
            ->route('ecommerce.admin.header-categories.edit')
            ->with('success', __('Header categories updated successfully.'));
    }
}
