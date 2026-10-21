<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\UpdatesEcommerceSortOrder;
use Modules\Ecommerce\Models\EcommerceHomeCategory;
use Modules\Ecommerce\Services\EcommerceMediaService;
use Modules\Stock\Models\ItemCategory;

class HomeCategoryController extends Controller
{
    use BuildsEcommerceDataTable;
    use UpdatesEcommerceSortOrder;

    public function __construct(
        protected EcommerceMediaService $media,
    ) {
    }

    public function index(): View
    {
        $rows = EcommerceHomeCategory::query()
            ->with('category')
            ->forCompany($this->companyId())
            ->live()
            ->orderBy('sort_id')
            ->orderBy('id')
            ->get();

        $items = $rows->map(function (EcommerceHomeCategory $row) {
            $title = $row->title ?: ($row->category->name ?? '-');

            return [
                'id' => $row->id,
                'title' => $title,
                'meta' => __('Category') . ': ' . ($row->category->name ?? '-'),
                'image' => $row->image ? $this->media->url($row->image) : null,
                'status' => $row->status,
                'edit_url' => route('ecommerce.admin.categories.edit', $row->id),
                'destroy_url' => route('ecommerce.admin.categories.destroy', $row->id),
            ];
        });

        return view('ecommerce::admin.categories.index', compact('items'));
    }

    public function updateSortOrder(Request $request): JsonResponse
    {
        return $this->updateSortOrderFor($request, EcommerceHomeCategory::class);
    }

    public function create(): View
    {
        return view('ecommerce::admin.categories.form', [
            'row' => new EcommerceHomeCategory(['sort_id' => $this->nextSortId(EcommerceHomeCategory::class)]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);

        if (EcommerceHomeCategory::query()->forCompany($this->companyId())->live()->where('category_id', $data['category_id'])->exists()) {
            return back()->withInput()->with('error', __('This category is already added to the home section.'));
        }

        $data['image'] = $this->media->upload($request->file('image'), 'categories');
        if (! isset($data['sort_id']) || (int) $data['sort_id'] === 0) {
            $data['sort_id'] = $this->nextSortId(EcommerceHomeCategory::class);
        }

        EcommerceHomeCategory::query()->create(array_merge($data, [
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'del_status' => 'Live',
        ]));

        return redirect()->route('ecommerce.admin.categories.index')
            ->with('success', __('Home category added successfully.'));
    }

    public function edit(int $category): View
    {
        return view('ecommerce::admin.categories.form', [
            'row' => $this->findLive($category),
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $row = $this->findLive($category);
        $data = $this->validated($request, ! (bool) $row->image);

        $exists = EcommerceHomeCategory::query()
            ->forCompany($this->companyId())
            ->live()
            ->where('category_id', $data['category_id'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('This category is already added to the home section.'));
        }

        if ($request->hasFile('image')) {
            $this->media->delete($row->image);
            $data['image'] = $this->media->upload($request->file('image'), 'categories');
        }

        $row->update($data);

        return redirect()->route('ecommerce.admin.categories.index')
            ->with('success', __('Home category updated successfully.'));
    }

    public function destroy(int $category): RedirectResponse
    {
        $this->findLive($category)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.categories.index')
            ->with('success', __('Home category removed successfully.'));
    }

    protected function findLive(int $id): EcommerceHomeCategory
    {
        return EcommerceHomeCategory::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function categories()
    {
        return ItemCategory::query()
            ->where('company_id', $this->companyId())
            ->where('del_status', 'Live')
            ->orderBy('sort_id')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function validated(Request $request, bool $imageRequired = true): array
    {
        return $request->validate([
            'category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'sort_id' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
            'image' => [($imageRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ], [
            'category_id.required' => __('Please select a category.'),
            'category_id.exists' => __('Selected category is invalid.'),
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
            'image.required' => __('Please upload a banner image.'),
            'image.image' => __('Banner must be an image file.'),
            'image.mimes' => __('Banner must be a JPG, PNG, WEBP, or GIF file.'),
            'image.max' => __('Banner image may not be greater than 2MB.'),
        ]);
    }
}
