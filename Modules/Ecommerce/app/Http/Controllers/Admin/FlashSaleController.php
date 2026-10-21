<?php

namespace Modules\Ecommerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Ecommerce\Http\Controllers\Admin\Concerns\BuildsEcommerceDataTable;
use Modules\Ecommerce\Models\EcommerceFlashSale;
use Modules\Ecommerce\Models\EcommerceFlashSaleItem;
use Modules\Stock\Models\Item;

class FlashSaleController extends Controller
{
    use BuildsEcommerceDataTable;

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return $this->dataTable();
        }

        return view('ecommerce::admin.flash-sales.index');
    }

    protected function dataTable(): JsonResponse
    {
        $params = $this->dataTableParams();
        $base = EcommerceFlashSale::query()->withCount('items')->forCompany($this->companyId())->live();

        $recordsTotal = (clone $base)->count();
        $query = (clone $base)->orderByDesc('id');

        if ($params['search'] !== '') {
            $search = $params['search'];
            $query->where('title', 'like', "%{$search}%");
        }

        $recordsFiltered = (clone $query)->count();
        $rows = $query->skip($params['start'])->take($params['length'])->get();
        $startingNumber = $recordsFiltered - $params['start'];

        $data = $rows->map(function (EcommerceFlashSale $sale, int $index) use ($startingNumber) {
            return [
                'id' => $startingNumber - $index,
                'actual_id' => $sale->id,
                'title' => $sale->title,
                'starts_at' => $sale->starts_at ? $sale->starts_at->format('Y-m-d H:i') : '-',
                'ends_at' => $sale->ends_at ? $sale->ends_at->format('Y-m-d H:i') : '-',
                'products' => (int) $sale->items_count,
                'status' => $sale->status,
                'is_running' => $sale->isRunning(),
            ];
        });

        return $this->dataTableResponse($params['draw'], $recordsTotal, $recordsFiltered, $data);
    }

    public function create(): View
    {
        return view('ecommerce::admin.flash-sales.form', [
            'sale' => new EcommerceFlashSale(),
            'cartItems' => $this->resolveCartItems(),
            'initialItems' => $this->searchableItems('', [], 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guardAgainstOversizedRequest($request);
        $this->assertUploadsValid($request, ['banner', 'thumbnail']);

        try {
            $data = $this->validated($request);

            DB::transaction(function () use ($data, $request) {
                $sale = EcommerceFlashSale::query()->create([
                    'company_id' => $this->companyId(),
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'discount_type' => $data['discount_type'] ?? 'custom',
                    'discount_value' => $data['discount_value'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'status' => $data['status'],
                    'user_id' => auth()->id(),
                    'del_status' => 'Live',
                ]);

                $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class);

                if ($request->hasFile('banner')) {
                    $sale->update([
                        'banner' => $media->upload($request->file('banner'), 'offers'),
                    ]);
                }

                if ($request->hasFile('thumbnail')) {
                    $sale->update([
                        'thumbnail' => $media->upload($request->file('thumbnail'), 'offers'),
                    ]);
                }

                $this->syncItems($sale, $request);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', __('Could not save the offer. :message', ['message' => $e->getMessage()]));
        }

        return redirect()->route('ecommerce.admin.flash-sales.index')
            ->with('success', __('Offer created successfully.'));
    }

    public function edit(int $flash_sale): View
    {
        $sale = $this->findLive($flash_sale);
        $sale->load(['items.item']);
        $cartItems = $this->resolveCartItems($sale);
        $exclude = array_map(fn ($row) => (int) $row['id'], $cartItems);

        return view('ecommerce::admin.flash-sales.form', [
            'sale' => $sale,
            'cartItems' => $cartItems,
            'initialItems' => $this->searchableItems('', $exclude, 10),
        ]);
    }

    public function update(Request $request, int $flash_sale): RedirectResponse
    {
        $this->guardAgainstOversizedRequest($request);
        $this->assertUploadsValid($request, ['banner', 'thumbnail']);

        $sale = $this->findLive($flash_sale);

        try {
            $data = $this->validated($request);

            DB::transaction(function () use ($sale, $data, $request) {
                $payload = [
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'discount_type' => $data['discount_type'] ?? 'custom',
                    'discount_value' => $data['discount_value'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'status' => $data['status'],
                ];

                $media = app(\Modules\Ecommerce\Services\EcommerceMediaService::class);

                if ($request->hasFile('banner')) {
                    $media->delete($sale->banner);
                    $payload['banner'] = $media->upload($request->file('banner'), 'offers');
                } elseif ($request->boolean('remove_banner')) {
                    $media->delete($sale->banner);
                    $payload['banner'] = null;
                }

                if ($request->hasFile('thumbnail')) {
                    $media->delete($sale->thumbnail);
                    $payload['thumbnail'] = $media->upload($request->file('thumbnail'), 'offers');
                } elseif ($request->boolean('remove_thumbnail')) {
                    $media->delete($sale->thumbnail);
                    $payload['thumbnail'] = null;
                }

                $sale->update($payload);
                $this->syncItems($sale, $request);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', __('Could not update the offer. :message', ['message' => $e->getMessage()]));
        }

        return redirect()->route('ecommerce.admin.flash-sales.index')
            ->with('success', __('Offer updated successfully.'));
    }

    public function destroy(int $flash_sale): RedirectResponse
    {
        $this->findLive($flash_sale)->update(['del_status' => 'Deleted']);

        return redirect()->route('ecommerce.admin.flash-sales.index')
            ->with('success', __('Offer deleted successfully.'));
    }

    public function searchItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', $request->query('q', '')));
        $excludeRaw = $request->input('exclude', $request->query('exclude', []));
        if (is_string($excludeRaw)) {
            $excludeRaw = $excludeRaw === '' ? [] : explode(',', $excludeRaw);
        }
        $exclude = array_values(array_filter(array_map('intval', (array) $excludeRaw)));

        return response()->json([
            'results' => $this->searchableItems($q, $exclude, 10),
        ]);
    }

    /**
     * @param  array<int, int>  $exclude
     * @return array<int, array<string, mixed>>
     */
    protected function searchableItems(string $q = '', array $exclude = [], int $limit = 10): array
    {
        $companyId = $this->companyId();

        $query = Item::query()
            ->where('del_status', 'Live')
            ->where(function ($builder) {
                $builder->where('parent_id', 0)->orWhereNull('parent_id');
            })
            ->orderBy('name');

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        // Prefer enabled items, but do not hard-fail if status is blank.
        $query->where(function ($builder) {
            $builder->where('enable_disable_status', 'Enable')
                ->orWhereNull('enable_disable_status')
                ->orWhere('enable_disable_status', '');
        });

        if ($exclude !== []) {
            $query->whereNotIn('id', $exclude);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        return $query->limit($limit)->get(['id', 'name', 'code', 'sale_price'])
            ->map(function (Item $item) {
                $label = $item->name . ($item->code ? ' (' . $item->code . ')' : '');

                return [
                    'id' => (string) $item->id,
                    'text' => $label,
                    'name' => $item->name,
                    'code' => $item->code,
                    'sale_price' => (float) $item->sale_price,
                    'sale_price_formatted' => function_exists('formatAmount')
                        ? formatAmount($item->sale_price)
                        : (string) $item->sale_price,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build cart rows from old input or existing flash sale items.
     */
    protected function resolveCartItems(?EcommerceFlashSale $sale = null): array
    {
        $oldIds = array_values(array_unique(array_map('intval', (array) old('item_ids', []))));
        $oldPrices = (array) old('flash_prices', []);

        if ($oldIds !== []) {
            $items = Item::query()
                ->where('company_id', $this->companyId())
                ->whereIn('id', $oldIds)
                ->get(['id', 'name', 'code', 'sale_price'])
                ->keyBy('id');

            $cart = [];
            foreach ($oldIds as $itemId) {
                $item = $items->get($itemId);
                if (! $item) {
                    continue;
                }
                $cart[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'sale_price' => (float) $item->sale_price,
                    'sale_price_formatted' => function_exists('formatAmount')
                        ? formatAmount($item->sale_price)
                        : (string) $item->sale_price,
                    'flash_price' => $oldPrices[$itemId] ?? '',
                ];
            }

            return $cart;
        }

        if (! $sale || ! $sale->exists) {
            return [];
        }

        return $sale->items
            ->filter(fn ($line) => $line->item)
            ->map(function ($line) {
                $item = $line->item;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'sale_price' => (float) $item->sale_price,
                    'sale_price_formatted' => function_exists('formatAmount')
                        ? formatAmount($item->sale_price)
                        : (string) $item->sale_price,
                    'flash_price' => $line->flash_price !== null ? (string) $line->flash_price : '',
                ];
            })
            ->values()
            ->all();
    }

    protected function syncItems(EcommerceFlashSale $sale, Request $request): void
    {
        $itemIds = array_values(array_unique(array_map('intval', (array) $request->input('item_ids', []))));
        $prices = (array) $request->input('flash_prices', []);

        EcommerceFlashSaleItem::query()->where('flash_sale_id', $sale->id)->delete();

        foreach ($itemIds as $index => $itemId) {
            if ($itemId <= 0) {
                continue;
            }
            EcommerceFlashSaleItem::query()->create([
                'flash_sale_id' => $sale->id,
                'item_id' => $itemId,
                'flash_price' => isset($prices[$itemId]) && $prices[$itemId] !== '' ? $prices[$itemId] : null,
                'sort_id' => $index,
            ]);
        }
    }

    protected function findLive(int $id): EcommerceFlashSale
    {
        return EcommerceFlashSale::query()->forCompany($this->companyId())->live()->findOrFail($id);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'banner' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:700'],
            'remove_banner' => ['nullable', 'in:1'],
            'thumbnail' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:500'],
            'remove_thumbnail' => ['nullable', 'in:1'],
            'discount_type' => ['required', 'in:custom,percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:Active,Inactive'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer', 'exists:items,id'],
            'flash_prices' => ['nullable', 'array'],
            'flash_prices.*' => ['nullable', 'numeric', 'min:0'],
        ], [
            'title.required' => __('Please enter the offer title.'),
            'discount_type.required' => __('Please select a discount type.'),
            'discount_type.in' => __('Please select a valid discount type.'),
            'starts_at.required' => __('Please select a start date.'),
            'starts_at.date' => __('Please enter a valid start date.'),
            'ends_at.required' => __('Please select an end date.'),
            'ends_at.date' => __('Please enter a valid end date.'),
            'ends_at.after_or_equal' => __('End date must be on or after the start date.'),
            'status.required' => __('Please select a status.'),
            'status.in' => __('Please select a valid status.'),
            'banner.file' => __('Please upload a valid offer banner image.'),
            'banner.image' => __('Offer banner must be an image file.'),
            'banner.mimes' => __('Offer banner must be a JPG, PNG, WEBP, or GIF file.'),
            'banner.max' => __('Offer banner may not be greater than 700KB.'),
            'thumbnail.file' => __('Please upload a valid offer thumbnail image.'),
            'thumbnail.image' => __('Offer thumbnail must be an image file.'),
            'thumbnail.mimes' => __('Offer thumbnail must be a JPG, PNG, WEBP, or GIF file.'),
            'thumbnail.max' => __('Offer thumbnail may not be greater than 500KB.'),
        ]);
    }

    /**
     * Detect requests emptied by PHP when post_max_size is exceeded.
     */
    protected function guardAgainstOversizedRequest(Request $request): void
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($contentLength <= 0) {
            return;
        }

        $postMax = $this->iniSizeToBytes((string) ini_get('post_max_size'));
        if ($postMax > 0 && $contentLength > $postMax && count($request->all()) === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'banner' => [__('The uploaded files are too large. Please keep total upload under :size.', [
                    'size' => ini_get('post_max_size') ?: '8M',
                ])],
            ]);
        }
    }

    /**
     * Surface PHP upload errors (e.g. upload_max_filesize) as field validation messages.
     *
     * @param  list<string>  $fields
     */
    protected function assertUploadsValid(Request $request, array $fields): void
    {
        $messages = [];
        $maxUpload = ini_get('upload_max_filesize') ?: '2M';

        foreach ($fields as $field) {
            $file = $request->file($field);
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            if ($file->isValid()) {
                continue;
            }

            $error = $file->getError();
            $label = $field === 'thumbnail' ? __('Offer Thumbnail') : __('Offer Banner');

            if (in_array($error, [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
                $messages[$field] = [__(':label is too large. Maximum allowed upload size is :size.', [
                    'label' => $label,
                    'size' => $maxUpload,
                ])];
            } else {
                $messages[$field] = [__(':label failed to upload. Please try again with a smaller image.', [
                    'label' => $label,
                ])];
            }
        }

        if ($messages !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($messages);
        }
    }

    protected function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (float) $value,
        };
    }
}
