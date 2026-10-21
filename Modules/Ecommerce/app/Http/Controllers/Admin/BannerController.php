<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Models\EcommerceBanner;
use Modules\Ecommerce\Services\EcommerceMediaService;

class BannerController extends Controller
{
    use BuildsEcommerceDataTable;

    public function __construct(
        protected EcommerceMediaService $media,
    ) {
    }

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('ecommerce::admin.banners.index');
    }

    protected function dataTable(): JsonResponse
    {
        $params = $this->dataTableParams();
        $base = EcommerceBanner::query()->forCompany($this->companyId())->live();

        $recordsTotal = (clone $base)->count();
        $query = (clone $base)->orderBy('sort_id')->orderByDesc('id');

        if ($params['search'] !== '') {
            $search = $params['search'];
            $query->where('link', 'like', "%{$search}%");
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($params['start'])->take($params['length'])->get();
        $startingNumber = $recordsFiltered - $params['start'];

        $data = $rows->map(function (EcommerceBanner $banner, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $banner->id,
                'image_url' => $this->media->url($banner->image),
                'link' => $banner->link ?: '-',
                'sort_id' => (int) $banner->sort_id,
                'status' => $banner->status,
            ];
        });

        return $this->dataTableResponse($params['draw'], $recordsTotal, $recordsFiltered, $data);
    }

    public function create(): View
    {
        return view('ecommerce::admin.banners.form', ['banner' => new EcommerceBanner()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $data['company_id'] = $this->companyId();
        $data['user_id'] = auth()->id();
        $data['image'] = $this->media->upload($request->file('image'), 'banners');
        $data['del_status'] = 'Live';

        EcommerceBanner::query()->create($data);

        return redirect()->route('ecommerce.admin.banners.index')
            ->with('success', __('Banner created successfully.'));
    }

    public function edit(int $banner): View
    {
        return view('ecommerce::admin.banners.form', ['banner' => $this->findLive($banner)]);
    }

    public function update(Request $request, int $banner): RedirectResponse
    {
        $model = $this->findLive($banner);
        $data = $this->validated($request, false);

        if ($request->hasFile('image')) {
            $this->media->delete($model->image);
            $data['image'] = $this->media->upload($request->file('image'), 'banners');
        }

        $model->update($data);

        return redirect()->route('ecommerce.admin.banners.index')
            ->with('success', __('Banner updated successfully.'));
    }

    public function destroy(int $banner): RedirectResponse
    {
        $model = $this->findLive($banner);
        $model->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.banners.index')
            ->with('success', __('Banner deleted successfully.'));
    }

    protected function findLive(int $id): EcommerceBanner
    {
        return EcommerceBanner::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function validated(Request $request, bool $imageRequired): array
    {
        return $request->validate([
            'link' => ['nullable', 'string', 'max:255'],
            'sort_id' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
            'image' => [($imageRequired ? 'required' : 'nullable'), 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ], [
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
            'image.required' => __('Please upload a banner image.'),
            'image.image' => __('Banner must be an image file.'),
            'image.mimes' => __('Banner must be a JPG, PNG, WEBP, or GIF file.'),
            'image.max' => __('Banner image may not be greater than 2MB.'),
        ]);
    }
}
