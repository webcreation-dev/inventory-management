<?php

namespace Modules\Stock\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Models\ItemSubCategory;

class ItemSubCategoryController extends Controller
{
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('stock::item-sub-category.index');
    }

    protected function dataTable(): JsonResponse
    {
        $draw = (int) request()->input('draw', 1);
        $length = (int) request()->input('length', 10);
        $start = (int) request()->input('start', 0);
        $search = (string) (request()->input('search.value') ?? '');
        $companyId = (int) session('company.company_id');

        $base = ItemSubCategory::query()
            ->with('category')
            ->forCompany($companyId)
            ->live()
            ->orderByDesc('id');

        $recordsTotal = (clone $base)->count();
        $query = clone $base;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($start)->take($length)->get();
        $startingNumber = $recordsFiltered - $start;

        $data = $rows->map(function (ItemSubCategory $row, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $row->id,
                'name' => $row->name,
                'category' => $row->category->name ?? '-',
                'description' => function_exists('truncateText') ? truncateText($row->description) : $row->description,
                'encrypted_id' => $row->encrypted_id,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(): View
    {
        return view('stock::item-sub-category.create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        ItemSubCategory::query()->create([
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_id' => $data['sort_id'] ?? 0,
            'company_id' => (int) session('company.company_id'),
            'user_id' => Auth::id(),
            'del_status' => 'Live',
        ]);

        return redirect()->route('item-sub-category.index')
            ->with('success', __('Sub Category created successfully.'));
    }

    public function edit(string $item_sub_category): View|RedirectResponse
    {
        try {
            $itemSubCategory = ItemSubCategory::query()
                ->forCompany()
                ->live()
                ->where('id', decrypt($item_sub_category))
                ->firstOrFail();

            return view('stock::item-sub-category.create', [
                'itemSubCategory' => $itemSubCategory,
                'categories' => $this->categories(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('item-sub-category.index')
                ->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, string $item_sub_category): RedirectResponse
    {
        try {
            $row = ItemSubCategory::query()
                ->forCompany()
                ->live()
                ->where('id', decrypt($item_sub_category))
                ->firstOrFail();

            $data = $this->validated($request);
            $row->update([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'sort_id' => $data['sort_id'] ?? 0,
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('item-sub-category.index')
                ->with('success', __('Sub Category updated successfully.'));
        } catch (\Throwable $e) {
            return redirect()->route('item-sub-category.index')
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(string $item_sub_category): RedirectResponse
    {
        try {
            $row = ItemSubCategory::query()
                ->forCompany()
                ->live()
                ->where('id', decrypt($item_sub_category))
                ->firstOrFail();
            $row->update(['del_status' => 'Deleted']);

            return redirect()->route('item-sub-category.index')
                ->with('success', __('Sub Category deleted successfully.'));
        } catch (\Throwable $e) {
            return redirect()->route('item-sub-category.index')
                ->with('error', $e->getMessage());
        }
    }

    public function byCategory(Request $request): JsonResponse
    {
        $categoryId = (int) $request->query('category_id', 0);

        $rows = ItemSubCategory::query()
            ->forCompany()
            ->live()
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return response()->json([
            'results' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'text' => $row->name,
                'category_id' => $row->category_id,
            ])->values(),
        ]);
    }

    protected function categories()
    {
        return ItemCategory::query()
            ->where('company_id', (int) session('company.company_id'))
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'sort_id' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
