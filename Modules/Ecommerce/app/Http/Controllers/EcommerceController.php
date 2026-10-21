<?php

namespace Modules\Ecommerce\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\PaymentMethod;
use Modules\Configuration\Models\Outlet;
use Modules\Ecommerce\Models\EcommerceDeliveryArea;
use Modules\Ecommerce\Models\EcommerceHomeCategory;
use Modules\Ecommerce\Services\CartService;
use Modules\Ecommerce\Services\CustomerAuthService;
use Modules\Ecommerce\Services\EcommerceContentService;
use Modules\Ecommerce\Services\OrderService;
use Modules\Ecommerce\Services\WishlistService;
use Modules\Ecommerce\Services\ProductRatingService;
use Modules\Sale\Models\Sale;
use Modules\Stock\Models\Brand;
use Modules\Stock\Models\Item;
use Modules\Stock\Models\ItemCategory;
use Modules\Stock\Models\ItemSubCategory;
use App\Facades\PaymentGateway;
use Illuminate\Support\Str;

class EcommerceController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CustomerAuthService $customerAuth,
        protected OrderService $orders,
        protected WishlistService $wishlist,
    ) {
    }

    public function home()
    {
        $content = app(\Modules\Ecommerce\Services\EcommerceContentService::class);
        $media = $content->media();

        $cmsBanners = collect();
        $cmsCategories = collect();
        $cmsBrands = collect();
        $cmsFeatured = collect();
        $cmsNewTrend = collect();
        $cmsPopular = collect();
        $cmsFlashSale = null;

        try {
            $cmsBanners = $content->banners();
            $cmsCategories = $content->homeCategories();
            $cmsBrands = $content->popularBrands();
            $cmsFeatured = $content->featuredProducts();
            $cmsNewTrend = $content->newTrendProducts();
            $cmsPopular = $content->popularProducts();
            $cmsFlashSale = $content->activeFlashSale();
        } catch (\Throwable) {
            // Tables may not be migrated yet â€” fall back to demo data.
        }

        $hasCmsContent = $cmsBanners->isNotEmpty()
            || $cmsCategories->isNotEmpty()
            || $cmsBrands->isNotEmpty()
            || $cmsFeatured->isNotEmpty()
            || $cmsNewTrend->isNotEmpty()
            || $cmsPopular->isNotEmpty()
            || $cmsFlashSale;

        $heroSlides = $cmsBanners->isNotEmpty()
            ? $cmsBanners->map(fn ($b) => [
                'image' => $media->url($b->image),
                'alt' => 'Banner',
                'link' => $b->link,
                'is_url' => true,
            ])->values()->all()
            : ($hasCmsContent ? [] : collect($this->heroSlides())->map(fn ($s) => array_merge($s, ['is_url' => false]))->all());

        $browseCategories = $cmsCategories->isNotEmpty()
            ? $cmsCategories->map(fn ($row) => [
                'slug' => \Illuminate\Support\Str::slug($row->category->name ?? 'category'),
                'name' => $row->title ?: ($row->category->name ?? __('Category')),
                'item_count' => __('Shop now'),
                'image' => $row->image ? $media->url($row->image) : null,
                'is_url' => (bool) $row->image,
            ])->values()->all()
            : ($hasCmsContent ? [] : $this->browseCategories());

        $popularBrands = $cmsBrands->isNotEmpty()
            ? $cmsBrands->map(fn ($row) => [
                'name' => $row->brand->name ?? __('Brand'),
                'image' => $row->image ? $media->url($row->image) : null,
            ])->values()->all()
            : ($hasCmsContent
                ? []
                : collect($this->popularBrands())->map(fn ($name) => ['name' => $name, 'image' => null])->all());

        $featuredProducts = $cmsFeatured->isNotEmpty()
            ? $cmsFeatured->map(fn ($row) => $this->mapStockItemToCard($row->item))->filter()->values()->all()
            : [];

        $newTrendProducts = $cmsNewTrend->isNotEmpty()
            ? $cmsNewTrend->map(fn ($row) => $this->mapStockItemToCard($row->item))->filter()->values()->all()
            : [];
        if (empty($newTrendProducts)) {
            try {
                $newTrendProducts = $content->fallbackNewTrendItems(8)
                    ->map(fn ($item) => $this->mapStockItemToCard($item))
                    ->filter()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                $newTrendProducts = [];
            }
        }

        $mostPopularProducts = $cmsPopular->isNotEmpty()
            ? $cmsPopular->map(fn ($row) => $this->mapStockItemToCard($row->item))->filter()->values()->all()
            : [];
        if (empty($mostPopularProducts)) {
            try {
                $mostPopularProducts = $content->fallbackPopularItems(8)
                    ->map(fn ($item) => $this->mapStockItemToCard($item))
                    ->filter()
                    ->values()
                    ->all();
            } catch (\Throwable) {
                $mostPopularProducts = [];
            }
        }

        $offerProducts = [];
        $offerTitle = __('Special Offer');
        $offerDescription = null;
        $offerBanner = null;
        $offerEndsAt = null;
        if ($cmsFlashSale) {
            $offerTitle = $cmsFlashSale->title;
            $offerDescription = $cmsFlashSale->description;
            $offerBanner = $cmsFlashSale->banner ? $media->url($cmsFlashSale->banner) : null;
            $endsAt = $cmsFlashSale->ends_at;
            if ($endsAt && $endsAt->format('H:i:s') === '00:00:00') {
                $endsAt = $endsAt->copy()->endOfDay();
            }
            $offerEndsAt = optional($endsAt)->toIso8601String();
            $offerProducts = $cmsFlashSale->items
                ->map(fn ($line) => $this->mapStockItemToCard($line->item))
                ->filter()
                ->values()
                ->all();
        }

        // Keep legacy flash sale vars for older templates / scripts.
        $flashSaleProducts = $offerProducts;
        $flashSaleTitle = $offerTitle;
        $flashSaleEndsAt = $offerEndsAt;

        return view('ecommerce::pages.home', compact(
            'offerProducts',
            'offerTitle',
            'offerDescription',
            'offerBanner',
            'offerEndsAt',
            'flashSaleProducts',
            'flashSaleTitle',
            'flashSaleEndsAt',
            'featuredProducts',
            'newTrendProducts',
            'mostPopularProducts',
            'heroSlides',
            'popularBrands',
            'browseCategories',
            'hasCmsContent'
        ));
    }

    protected function mapStockItemToCard($item): ?array
    {
        if (! $item) {
            return null;
        }

        $imageSource = $item;
        if ((int) ($item->parent_id ?? 0) > 0) {
            try {
                $parent = $item->relationLoaded('parent')
                    ? $item->parent
                    : Item::query()->with('images')->find((int) $item->parent_id);
                if ($parent) {
                    $imageSource = $parent;
                }
            } catch (\Throwable) {
                $imageSource = $item;
            }
        }

        $price = (float) ($item->sale_price ?? 0);
        $photo = $imageSource->photo
            ? asset('public/uploads/items/' . $imageSource->photo)
            : asset('public/uploads/dummy_images/default-picture.png');

        $gallery = [$photo];
        if ($imageSource->relationLoaded('images')) {
            foreach ($imageSource->images as $image) {
                $gallery[] = $image->url;
            }
        } elseif (method_exists($imageSource, 'images')) {
            foreach ($imageSource->images as $image) {
                $gallery[] = $image->url;
            }
        }
        $gallery = array_values(array_unique(array_filter($gallery)));

        $ratingSummary = ['rating' => 0.0, 'reviews' => 0];
        try {
            $ratingSummary = app(ProductRatingService::class)->summary((int) $item->id);
        } catch (\Throwable) {
            $ratingSummary = ['rating' => 0.0, 'reviews' => 0];
        }

        $card = [
            'id' => (int) $item->id,
            'encrypted_id' => $item->encrypted_id ?? encrypt((int) $item->id),
            'name' => $item->name,
            'price' => $price,
            'old_price' => $price,
            'discount' => 0,
            'category' => $item->category->name ?? '',
            'category_slug' => ! empty($item->category?->name) ? Str::slug($item->category->name) : null,
            'sub_category' => null,
            'sub_category_slug' => null,
            'brand' => $item->brand->name ?? '',
            'rating' => (float) ($ratingSummary['rating'] ?? 0),
            'reviews' => (int) ($ratingSummary['reviews'] ?? 0),
            'image' => $photo,
            'image_is_url' => true,
            'gallery' => $gallery,
            'description' => $item->description ?? '',
            'spec' => $item->code ?: '',
            'type' => (string) ($item->type ?? ''),
            'parent_id' => (int) ($item->parent_id ?? 0),
            'is_variation' => $this->isVariationParentItem($item),
        ];

        try {
            $sub = $item->relationLoaded('subCategory') ? $item->subCategory : $item->subCategory()->first();
            if ($sub) {
                $card['sub_category'] = $sub->name;
                $card['sub_category_slug'] = Str::slug($sub->name);
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            return app(\Modules\Ecommerce\Services\OfferService::class)->applyToCard($card);
        } catch (\Throwable) {
            return $card;
        }
    }

    protected function isVariationParentItem($item): bool
    {
        return (string) ($item->type ?? '') === 'Variation_Product';
    }

    /**
     * @return list<array{id:int,name:string,label:string,cart_name:string,price:float,old_price:float,image:?string,image_is_url:bool,stock:float,in_stock:bool}>
     */
    protected function variationOptionsForParent(int $parentId, string $parentName): array
    {
        $children = Item::query()
            ->with(['images', 'parent.images'])
            ->where('parent_id', $parentId)
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('enable_disable_status')
                    ->orWhere('enable_disable_status', 'Enable');
            })
            ->orderBy('id')
            ->get();

        $stockMap = $this->cart->stockMapForIds($children->pluck('id')->map(fn ($id) => (int) $id)->all());

        $options = [];
        foreach ($children as $child) {
            $card = $this->mapStockItemToCard($child);
            if (! $card) {
                continue;
            }

            $label = $this->cart->variationAttributeLabel($child, $parentName);
            $stock = (float) ($stockMap[(int) $child->id] ?? 0);

            $options[] = [
                'id' => (int) $card['id'],
                'name' => (string) $card['name'],
                'label' => $label,
                'cart_name' => $this->cart->variationCartName($child, $parentName),
                'price' => (float) $card['price'],
                'old_price' => (float) ($card['old_price'] ?? $card['price']),
                'image' => $card['image'] ?? null,
                'image_is_url' => (bool) ($card['image_is_url'] ?? true),
                'gallery' => array_values(array_filter((array) ($card['gallery'] ?? []))),
                'stock' => $stock,
                'in_stock' => $stock > 0,
            ];
        }

        return $options;
    }

    public function image(string $file)
    {
        $allowed = ['banner.png', 'iphone.png', 'iphone-17.png', 'cat1.png', 'cat2.png', 'cat3.png', 'cat4.png'];
        abort_unless(in_array($file, $allowed, true), 404);

        $path = module_path('Ecommerce', 'resources/images/' . $file);
        abort_unless(file_exists($path), 404);

        return response()->file($path);
    }

    public function contact()
    {
        $branding = app(EcommerceContentService::class)->branding();

        return view('ecommerce::pages.contact', [
            'contactPhone' => $branding['contactPhone'] ?? null,
            'contactEmail' => $branding['contactEmail'] ?? null,
            'contactAddress' => $branding['contactAddress'] ?? null,
            'siteName' => $branding['siteName'] ?? null,
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $content = app(EcommerceContentService::class);
        $companyId = $content->companyId();
        $branding = $content->branding();
        $notifyEmail = trim((string) ($branding['contactEmail'] ?? ''));

        $contact = \Modules\Ecommerce\Models\EcommerceContact::query()->create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'del_status' => 'Live',
        ]);

        if ($notifyEmail !== '') {
            $palette = \Modules\Ecommerce\Support\BrandColor::palette($branding['brandColor'] ?? null);
            $siteLogo = $branding['siteLogo'] ?? null;
            if (is_string($siteLogo) && $siteLogo !== '' && ! str_starts_with($siteLogo, 'http')) {
                $siteLogo = url($siteLogo);
            }

            try {
                app(\Modules\Configuration\Services\EmailService::class)->sendEmail(
                    $notifyEmail,
                    __('New contact message: :subject', ['subject' => $contact->subject]),
                    $contact->message,
                    [],
                    'Ecommerce Contact',
                    null,
                    [
                        'company_id' => $companyId,
                        'siteName' => $branding['siteName'] ?? null,
                        'siteLogo' => $siteLogo,
                        'brandColor' => $palette['hex'],
                        'brandAccent' => $palette['accent'],
                        'brandStrong' => $palette['strong'],
                        'brandSoft' => $palette['soft'],
                        'brandGlow' => $palette['glow'],
                        'contactName' => $contact->name,
                        'contactEmail' => $contact->email,
                        'contactPhone' => $contact->phone,
                        'contactSubject' => $contact->subject,
                        'contactMessage' => $contact->message,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Ecommerce contact notify failed', [
                    'contact_id' => $contact->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('ecommerce.contact')
            ->with('success', __('Thank you! Your message has been sent. We will get back to you soon.'));
    }

    public function privacy()
    {
        $content = app(EcommerceContentService::class);
        $branding = $content->branding();
        $setting = null;
        try {
            $setting = $content->settings();
        } catch (\Throwable) {
            $setting = null;
        }

        return view('ecommerce::pages.privacy', [
            'siteName' => $branding['siteName'] ?? config('app.name'),
            'contactEmail' => $branding['contactEmail'] ?? null,
            'content' => $setting?->privacy_policy,
        ]);
    }

    public function terms()
    {
        $content = app(EcommerceContentService::class);
        $branding = $content->branding();
        $setting = null;
        try {
            $setting = $content->settings();
        } catch (\Throwable) {
            $setting = null;
        }

        return view('ecommerce::pages.terms', [
            'siteName' => $branding['siteName'] ?? config('app.name'),
            'contactEmail' => $branding['contactEmail'] ?? null,
            'content' => $setting?->terms_and_conditions,
        ]);
    }

    public function offers()
    {
        $content = app(EcommerceContentService::class);
        $media = $content->media();

        $offers = $content->storefrontOffers()->map(function ($sale) use ($media) {
            $endsAt = $sale->ends_at;
            if ($endsAt && $endsAt->format('H:i:s') === '00:00:00') {
                $endsAt = $endsAt->copy()->endOfDay();
            }

            return [
                'id' => (int) $sale->id,
                'title' => $sale->title,
                'description' => $sale->description,
                'thumbnail' => $sale->thumbnail
                    ? $media->url($sale->thumbnail)
                    : ($sale->banner ? $media->url($sale->banner) : null),
                'ends_at' => optional($endsAt)->toIso8601String(),
                'is_running' => $sale->isRunning(),
                'products_count' => (int) ($sale->items_count ?? 0),
            ];
        })->values();

        return view('ecommerce::pages.offers', compact('offers'));
    }

    public function offerShow(int $offer)
    {
        $content = app(EcommerceContentService::class);
        $media = $content->media();
        $sale = $content->findStorefrontOffer($offer);
        abort_unless($sale, 404);

        $endsAt = $sale->ends_at;
        if ($endsAt && $endsAt->format('H:i:s') === '00:00:00') {
            $endsAt = $endsAt->copy()->endOfDay();
        }

        $page = 1;
        $perPage = 8;
        $productsPayload = $this->paginateOfferProducts($sale, $page, $perPage);

        return view('ecommerce::pages.offer-details', [
            'offer' => [
                'id' => (int) $sale->id,
                'title' => $sale->title,
                'description' => $sale->description,
                'banner' => $sale->banner ? $media->url($sale->banner) : null,
                'ends_at' => optional($endsAt)->toIso8601String(),
                'is_running' => $sale->isRunning(),
            ],
            'products' => $productsPayload['products'],
            'hasMore' => $productsPayload['has_more'],
            'nextPage' => $productsPayload['next_page'],
            'productsUrl' => route('ecommerce.offers.products', $sale->id),
        ]);
    }

    public function offerProducts(Request $request, int $offer): JsonResponse
    {
        $content = app(EcommerceContentService::class);
        $sale = $content->findStorefrontOffer($offer);
        abort_unless($sale, 404);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 8;
        $payload = $this->paginateOfferProducts($sale, $page, $perPage);

        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return ! empty($product['image_is_url'])
                ? $product['image']
                : route('ecommerce.image', ['file' => $product['image'] ?? 'banner.png']);
        };

        $html = view('ecommerce::partials.offer-products-chunk', [
            'products' => $payload['products'],
            'money' => $money,
            'productImage' => $productImage,
            'wishlistIds' => app(WishlistService::class)->ids(),
        ])->render();

        return response()->json([
            'html' => $html,
            'has_more' => $payload['has_more'],
            'next_page' => $payload['next_page'],
        ]);
    }

    /**
     * @return array{products: list<array>, has_more: bool, next_page: int|null}
     */
    protected function paginateOfferProducts(\Modules\Ecommerce\Models\EcommerceFlashSale $sale, int $page, int $perPage): array
    {
        $sale->loadMissing(['items.item.brand', 'items.item.category', 'items.item.images']);

        $offerService = app(\Modules\Ecommerce\Services\OfferService::class);

        $all = $sale->items
            ->map(function ($line) use ($offerService, $sale) {
                $card = $this->mapStockItemToCard($line->item);
                if (! $card) {
                    return null;
                }

                return $offerService->applyToCard($card, $sale);
            })
            ->filter()
            ->values();

        $total = $all->count();
        $offset = ($page - 1) * $perPage;
        $slice = $all->slice($offset, $perPage)->values()->all();
        $hasMore = ($offset + $perPage) < $total;

        return [
            'products' => $slice,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
        ];
    }

    public function category(Request $request, string $slug, ?string $sub = null)
    {
        $pageRoute = $sub ? 'ecommerce.category.sub' : 'ecommerce.category';
        $productsRoute = $sub ? 'ecommerce.category.sub.products' : 'ecommerce.category.products';
        $routeParams = $sub
            ? ['slug' => $slug, 'sub' => $sub]
            : ['slug' => $slug];

        return $this->renderCatalogPage($request, [
            'mode' => 'category',
            'slug' => $slug,
            'sub' => $sub,
            'productsUrl' => route($productsRoute, $routeParams),
            'formAction' => route($pageRoute, $routeParams),
            'resetUrl' => route($pageRoute, $routeParams),
        ]);
    }

    public function categoryProducts(Request $request, string $slug, ?string $sub = null): JsonResponse
    {
        return $this->catalogProductsJson($request, [
            'mode' => 'category',
            'slug' => $slug,
            'sub' => $sub,
        ]);
    }

    public function collection(Request $request, string $type)
    {
        abort_unless(in_array($type, ['new-trend', 'popular'], true), 404);

        return $this->renderCatalogPage($request, [
            'mode' => 'collection',
            'collection' => $type,
            'slug' => $type,
            'productsUrl' => route('ecommerce.collection.products', ['type' => $type]),
            'formAction' => route('ecommerce.collection', ['type' => $type]),
            'resetUrl' => route('ecommerce.collection', ['type' => $type]),
        ]);
    }

    public function collectionProducts(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, ['new-trend', 'popular'], true), 404);

        return $this->catalogProductsJson($request, [
            'mode' => 'collection',
            'collection' => $type,
            'slug' => $type,
        ]);
    }

    /**
     * @param  array{mode:string,slug?:string,collection?:string,productsUrl:string,formAction:string,resetUrl:string}  $meta
     */
    protected function renderCatalogPage(Request $request, array $meta)
    {
        $payload = $this->buildCatalogPayload($request, $meta, max(1, (int) $request->query('page', 1)), 15);

        return view('ecommerce::pages.category', [
            'products' => $payload['products'],
            'brands' => $payload['brands'],
            'slug' => $meta['slug'] ?? 'all',
            'categoryTitle' => $payload['title'],
            'categoryImage' => $payload['banner_url'],
            'categoryImageIsUrl' => $payload['banner_is_url'],
            'selectedBrands' => $payload['selected_brands'],
            'minPrice' => $payload['min_price'],
            'maxPrice' => $payload['max_price'],
            'priceMinBound' => $payload['price_min_bound'],
            'priceMaxBound' => $payload['price_max_bound'],
            'sort' => $payload['sort'],
            'q' => $payload['q'],
            'totalProducts' => $payload['total'],
            'hasMore' => $payload['has_more'],
            'nextPage' => $payload['next_page'],
            'productsUrl' => $meta['productsUrl'],
            'formAction' => $meta['formAction'],
            'resetUrl' => $meta['resetUrl'],
            'productBadge' => $payload['badge'],
            'productBadgeClass' => $payload['badge_class'],
        ]);
    }

    /**
     * @param  array{mode:string,slug?:string,collection?:string}  $meta
     */
    protected function catalogProductsJson(Request $request, array $meta): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $payload = $this->buildCatalogPayload($request, $meta, $page, 15);

        $money = function ($amount) {
            return function_exists('formatAmount') ? formatAmount($amount) : ('$' . number_format((float) $amount, 2));
        };
        $productImage = function ($product) {
            return ! empty($product['image_is_url'])
                ? $product['image']
                : (str_starts_with((string) ($product['image'] ?? ''), 'http')
                    ? $product['image']
                    : route('ecommerce.image', ['file' => $product['image'] ?? 'banner.png']));
        };

        $html = view('ecommerce::partials.product-cards-chunk', [
            'products' => $payload['products'],
            'money' => $money,
            'productImage' => $productImage,
            'badge' => $payload['badge'],
            'badgeClass' => $payload['badge_class'],
            'wishlistIds' => app(WishlistService::class)->ids(),
        ])->render();

        return response()->json([
            'html' => $html,
            'total' => $payload['total'],
            'has_more' => $payload['has_more'],
            'next_page' => $payload['next_page'],
            'brands' => $payload['brands'],
            'selected_brands' => $payload['selected_brands'],
            'min_price' => $payload['min_price'],
            'max_price' => $payload['max_price'],
            'price_min_bound' => $payload['price_min_bound'],
            'price_max_bound' => $payload['price_max_bound'],
            'sort' => $payload['sort'],
            'title' => $payload['title'],
        ]);
    }

    /**
     * @param  array{mode:string,slug?:string,collection?:string}  $meta
     * @return array<string, mixed>
     */
    protected function buildCatalogPayload(Request $request, array $meta, int $page, int $perPage): array
    {
        $companyId = app(EcommerceContentService::class)->companyId();
        $content = app(EcommerceContentService::class);
        $mode = $meta['mode'] ?? 'category';
        $collection = $meta['collection'] ?? null;
        $slug = (string) ($meta['slug'] ?? 'all');
        $subSlug = isset($meta['sub']) && $meta['sub'] !== null && $meta['sub'] !== ''
            ? (string) $meta['sub']
            : null;

        $resolved = [
            'title' => __('All Categories'),
            'category_id' => null,
            'sub_category_id' => null,
            'banner_url' => route('ecommerce.image', ['file' => 'banner.png']),
            'banner_is_url' => true,
        ];
        $badge = null;
        $badgeClass = 'ec-badge-sale';
        $collectionIds = null;

        if ($mode === 'collection') {
            if ($collection === 'new-trend') {
                $resolved['title'] = __('New Trend');
                $badge = __('New');
                $badgeClass = 'ec-badge-new';
                $collectionIds = $content->newTrendProducts()->pluck('item_id')->map(fn ($id) => (int) $id)->filter()->values()->all();
            } elseif ($collection === 'popular') {
                $resolved['title'] = __('Most Popular Products');
                $badge = __('Popular');
                $badgeClass = 'ec-badge-hot';
                $collectionIds = $content->popularProducts()->pluck('item_id')->map(fn ($id) => (int) $id)->filter()->values()->all();
            }
        } else {
            $resolved = $this->resolveCategorySlug($slug, $companyId, $subSlug);
        }

        $query = Item::query()
            ->with(['category', 'brand', 'images'])
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('enable_disable_status')
                    ->orWhere('enable_disable_status', '')
                    ->orWhere('enable_disable_status', 'Enable');
            })
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId));

        if ($mode === 'collection') {
            if (is_array($collectionIds) && $collectionIds !== []) {
                $query->whereIn('id', $collectionIds);
            } elseif ($collection === 'popular') {
                $popularIds = $content->fallbackPopularItems(200)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($popularIds) {
                    $query->whereIn('id', $popularIds);
                    $collectionIds = $popularIds;
                }
            }
            // new-trend with empty CMS: no ID constraint — newest catalog items
        } else {
            $this->applyCatalogCategoryFilters($query, $resolved);
            if (($slug !== 'all' || $subSlug) && ! $resolved['category_id'] && ! $resolved['sub_category_id']) {
                $query->whereRaw('0 = 1');
            }
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('alternative_name', 'like', '%' . $search . '%');
            });
        }

        $priceBoundsQuery = (clone $query);
        $catalogMin = (float) ($priceBoundsQuery->min('sale_price') ?? 0);
        $catalogMax = (float) ((clone $query)->max('sale_price') ?? 0);
        if ($catalogMax <= $catalogMin) {
            $catalogMin = 0;
            $catalogMax = max(100, (float) ceil($catalogMax ?: 5000));
        }
        $priceMinBound = (int) max(0, floor($catalogMin));
        $priceMaxBound = (int) max($priceMinBound + 10, ceil($catalogMax));

        $minPrice = (float) $request->query('min_price', $priceMinBound);
        $maxPrice = (float) $request->query('max_price', $priceMaxBound);
        $minPrice = max($priceMinBound, min($priceMaxBound, $minPrice));
        $maxPrice = max($priceMinBound, min($priceMaxBound, $maxPrice));
        if ($minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        $query->where(function ($q) use ($minPrice, $maxPrice) {
            $q->whereBetween('sale_price', [$minPrice, $maxPrice])
                ->orWhereNull('sale_price');
        });

        $selectedBrands = array_values(array_filter(array_map('strval', (array) $request->query('brands', []))));
        if ($selectedBrands) {
            $query->whereHas('brand', function ($q) use ($selectedBrands) {
                $q->whereIn('name', $selectedBrands);
            });
        }

        $sort = (string) $request->query('sort', 'newest');
        $orderedIds = is_array($collectionIds) ? $collectionIds : [];
        if ($sort === 'price_low') {
            $query->orderBy('sale_price')->orderByDesc('id');
        } elseif ($sort === 'price_high') {
            $query->orderByDesc('sale_price')->orderByDesc('id');
        } elseif ($sort === 'name_az') {
            $query->orderBy('name')->orderByDesc('id');
        } elseif ($sort === 'name_za') {
            $query->orderByDesc('name')->orderByDesc('id');
        } elseif ($orderedIds !== [] && ($mode === 'collection')) {
            $sort = 'newest';
            $idList = implode(',', array_map('intval', $orderedIds));
            $query->orderByRaw('FIELD(id, ' . $idList . ')');
        } else {
            $sort = 'newest';
            $query->orderByDesc('id');
        }

        $brandQuery = Item::query()
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('enable_disable_status')
                    ->orWhere('enable_disable_status', '')
                    ->orWhere('enable_disable_status', 'Enable');
            })
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId));

        if ($mode === 'collection') {
            if (is_array($collectionIds) && $collectionIds !== []) {
                $brandQuery->whereIn('id', $collectionIds);
            }
        } else {
            $this->applyCatalogCategoryFilters($brandQuery, $resolved);
        }

        if ($search !== '') {
            $brandQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('alternative_name', 'like', '%' . $search . '%');
            });
        }

        $brandIds = (clone $brandQuery)->whereNotNull('brand_id')->distinct()->pluck('brand_id');
        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->where('del_status', 'Live')
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values()
            ->all();

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $items = collect($paginator->items());
        try {
            app(ProductRatingService::class)->preloadSummaries(
                $items->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        } catch (\Throwable) {
            // ignore
        }
        $products = $items
            ->map(fn (Item $item) => $this->mapStockItemToCard($item))
            ->filter()
            ->values()
            ->all();

        return [
            'products' => $products,
            'brands' => $brands,
            'title' => $resolved['title'],
            'banner_url' => $resolved['banner_url'],
            'banner_is_url' => $resolved['banner_is_url'],
            'selected_brands' => $selectedBrands,
            'min_price' => (int) $minPrice,
            'max_price' => (int) $maxPrice,
            'price_min_bound' => $priceMinBound,
            'price_max_bound' => $priceMaxBound,
            'sort' => $sort,
            'q' => $search,
            'total' => (int) $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
            'next_page' => $paginator->hasMorePages() ? ($page + 1) : null,
            'badge' => $badge,
            'badge_class' => $badgeClass,
        ];
    }

    public function searchSuggest(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['items' => []]);
        }

        $companyId = app(EcommerceContentService::class)->companyId();
        $items = Item::query()
            ->with(['category', 'brand'])
            ->where('del_status', 'Live')
            ->where(function ($query) {
                $query->whereNull('enable_disable_status')
                    ->orWhere('enable_disable_status', 'Enable');
            })
            ->where(function ($query) {
                $query->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('code', 'like', '%' . $q . '%')
                    ->orWhere('alternative_name', 'like', '%' . $q . '%');
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        $mapped = $items->map(fn (Item $item) => $this->mapStockItemToCard($item))->filter()->values()
            ->map(fn ($product) => [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'url' => route('ecommerce.item-details', ['id' => $product['encrypted_id']]),
            ])
            ->values();

        return response()->json(['items' => $mapped]);
    }

    protected function resolveCategorySlug(string $slug, int $companyId, ?string $subSlug = null): array
    {
        $defaults = [
            'title' => __('All Categories'),
            'category_id' => null,
            'sub_category_id' => null,
            'banner_url' => route('ecommerce.image', ['file' => 'banner.png']),
            'banner_is_url' => true,
        ];

        if (($slug === 'all' || $slug === '') && ! $subSlug) {
            return $defaults;
        }

        $categories = ItemCategory::query()
            ->where('del_status', 'Live')
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'name', 'banner']);

        // Nested URL: /category/{parent}/{sub} — avoid slug collisions with top-level categories.
        if ($subSlug) {
            $parent = $categories->first(fn (ItemCategory $row) => Str::slug($row->name) === $slug);
            if (! $parent) {
                return array_merge($defaults, [
                    'title' => Str::title(str_replace('-', ' ', $subSlug)),
                ]);
            }

            $sub = ItemSubCategory::query()
                ->live()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->where('category_id', $parent->id)
                ->get(['id', 'name', 'category_id'])
                ->first(fn (ItemSubCategory $row) => Str::slug($row->name) === $subSlug);

            if (! $sub) {
                return array_merge($defaults, [
                    'title' => Str::title(str_replace('-', ' ', $subSlug)),
                ]);
            }

            $parentBanner = $parent->banner_url;

            return [
                'title' => $sub->name,
                'category_id' => (int) $parent->id,
                'sub_category_id' => (int) $sub->id,
                'banner_url' => $parentBanner ?: $defaults['banner_url'],
                'banner_is_url' => true,
            ];
        }

        $category = $categories->first(fn (ItemCategory $row) => Str::slug($row->name) === $slug);
        if ($category) {
            $bannerUrl = $category->banner_url;
            $bannerIsUrl = (bool) $bannerUrl;

            if (! $bannerUrl) {
                $home = EcommerceHomeCategory::query()
                    ->forCompany($companyId > 0 ? $companyId : null)
                    ->live()
                    ->where('category_id', $category->id)
                    ->first();
                if ($home?->image) {
                    $bannerUrl = app(EcommerceContentService::class)->media()->url($home->image);
                    $bannerIsUrl = true;
                }
            }

            return [
                'title' => $category->name,
                'category_id' => (int) $category->id,
                'sub_category_id' => null,
                'banner_url' => $bannerUrl ?: $defaults['banner_url'],
                'banner_is_url' => $bannerIsUrl || true,
            ];
        }

        // Legacy single-slug subcategory URLs (only when no parent category shares the slug).
        $subCategories = ItemSubCategory::query()
            ->live()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->with('category')
            ->get(['id', 'name', 'category_id']);

        $sub = $subCategories->first(fn (ItemSubCategory $row) => Str::slug($row->name) === $slug);
        if ($sub) {
            $parentBanner = $sub->category?->banner_url;
            return [
                'title' => $sub->name,
                'category_id' => (int) $sub->category_id,
                'sub_category_id' => (int) $sub->id,
                'banner_url' => $parentBanner ?: $defaults['banner_url'],
                'banner_is_url' => true,
            ];
        }

        $demo = collect($this->browseCategories())->firstWhere('slug', $slug);
        if ($demo) {
            return [
                'title' => $demo['name'],
                'category_id' => null,
                'sub_category_id' => null,
                'banner_url' => route('ecommerce.image', ['file' => $demo['image'] ?? 'banner.png']),
                'banner_is_url' => true,
            ];
        }

        return array_merge($defaults, ['title' => Str::title(str_replace('-', ' ', $slug))]);
    }

    /**
     * Filter catalog by category and/or subcategory.
     * Parent category includes items assigned directly or via a subcategory of that category.
     * Subcategory pages filter only by sub_category_id (avoids missing items with mismatched category_id).
     */
    protected function applyCatalogCategoryFilters($query, array $resolved): void
    {
        $categoryId = (int) ($resolved['category_id'] ?? 0);
        $subCategoryId = (int) ($resolved['sub_category_id'] ?? 0);

        if ($subCategoryId > 0) {
            $query->where('sub_category_id', $subCategoryId);

            return;
        }

        if ($categoryId > 0) {
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId)
                    ->orWhereIn('sub_category_id', function ($sub) use ($categoryId) {
                        $sub->select('id')
                            ->from('item_sub_categories')
                            ->where('category_id', $categoryId)
                            ->where('del_status', 'Live');
                    });
            });
        }
    }

    public function itemDetails(?string $id = null)
    {
        $productId = $this->resolveProductRouteId($id);
        if (! $productId) {
            abort(404);
        }

        $product = $this->findProductById($productId);
        if (! $product) {
            abort(404);
        }

        $relatedProducts = Item::query()
            ->with(['category', 'subCategory', 'brand', 'images'])
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('enable_disable_status')
                    ->orWhere('enable_disable_status', 'Enable');
            })
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->where('id', '!=', $product['id'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        try {
            app(ProductRatingService::class)->preloadSummaries(
                $relatedProducts->pluck('id')->map(fn ($rid) => (int) $rid)->all()
            );
        } catch (\Throwable) {
            // ignore
        }

        $relatedProducts = $relatedProducts
            ->map(fn ($item) => $this->mapStockItemToCard($item))
            ->filter()
            ->values()
            ->all();

        if (! empty($product['is_variation'])) {
            $variations = $this->variationOptionsForParent((int) $product['id'], (string) $product['name']);
            $product['stock'] = null;
            $product['in_stock'] = null;
        } else {
            $variations = [];
            $stock = $this->cart->availableStock((int) $product['id']);
            $product['stock'] = $stock;
            $product['in_stock'] = $stock > 0;
        }

        $customerId = $this->customerAuth->id();
        $ratingPanel = app(ProductRatingService::class)->panel((int) $product['id'], $customerId);
        $product['rating'] = $ratingPanel['average'];
        $product['reviews'] = $ratingPanel['total'];

        $breadcrumbs = $this->productBreadcrumbs($product);

        return view('ecommerce::pages.item-details', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'inWishlist' => $this->wishlist->has((int) $product['id']),
            'variations' => $variations,
            'ratingPanel' => $ratingPanel,
            'breadcrumbs' => $breadcrumbs,
            'isLoggedIn' => (bool) $customerId,
        ]);
    }

    public function storeProductRating(Request $request, string $id): JsonResponse
    {
        $productId = $this->resolveProductRouteId($id);
        if (! $productId) {
            return response()->json(['message' => __('Product not found.')], 404);
        }

        $customerId = $this->customerAuth->id();
        if (! $customerId) {
            return response()->json([
                'message' => __("You're not logged in yet, login first."),
                'login_required' => true,
            ], 401);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'recommend' => ['nullable', 'boolean'],
        ]);

        try {
            $service = app(ProductRatingService::class);
            $service->upsert($customerId, $productId, [
                'rating' => (int) $data['rating'],
                'comment' => $data['comment'] ?? null,
                'recommend' => array_key_exists('recommend', $data)
                    ? (bool) $data['recommend']
                    : ((int) $data['rating'] >= 4),
            ]);
            $panel = $service->panel($productId, $customerId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Unable to save rating right now.')], 500);
        }

        return response()->json([
            'message' => __('Thanks for your review!'),
            'panel' => $panel,
        ]);
    }

    /**
     * Resolve encrypted (or legacy plain) product id from a route/query value.
     */
    protected function resolveProductRouteId(string|int|null $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $raw = rawurldecode((string) $id);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        try {
            $decoded = decrypt($raw);

            return is_numeric($decoded) ? (int) $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $product
     * @return list<array{label:string,url:?string,active?:bool}>
     */
    protected function productBreadcrumbs(array $product): array
    {
        $crumbs = [
            ['label' => __('Home'), 'url' => route('ecommerce.home')],
        ];

        if (! empty($product['category']) && ! empty($product['category_slug'])) {
            $crumbs[] = [
                'label' => $product['category'],
                'url' => route('ecommerce.category', ['slug' => $product['category_slug']]),
            ];
        }

        if (! empty($product['sub_category']) && ! empty($product['sub_category_slug'])) {
            $crumbs[] = [
                'label' => $product['sub_category'],
                'url' => ! empty($product['category_slug'])
                    ? route('ecommerce.category.sub', [
                        'slug' => $product['category_slug'],
                        'sub' => $product['sub_category_slug'],
                    ])
                    : route('ecommerce.category', ['slug' => $product['sub_category_slug']]),
            ];
        }

        $crumbs[] = [
            'label' => $product['name'] ?? __('Product'),
            'url' => null,
            'active' => true,
        ];

        return $crumbs;
    }

    public function cart(): JsonResponse
    {
        return response()->json($this->cart->payload());
    }

    public function addToCart(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $payload = $this->cart->add(
                (int) $data['product_id'],
                (int) ($data['qty'] ?? 1),
                isset($data['price']) ? (float) $data['price'] : null
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge($payload, [
                'message' => __('Added to cart.'),
            ]));
        }

        return redirect()->route('ecommerce.checkout')
            ->with('success', __('Added to cart.'));
    }

    public function updateCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        try {
            return response()->json($this->cart->update((int) $data['product_id'], (int) $data['qty']));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function removeFromCart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        return response()->json($this->cart->remove((int) $data['product_id']));
    }

    public function clearCart(): JsonResponse
    {
        return response()->json($this->cart->clear());
    }

    public function checkout()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('ecommerce.home')
                ->with('open_admin_checkout_block', true)
                ->with('error', __('You are logged in as admin. Please login as a customer to checkout.'));
        }

        if (! $this->customerAuth->check()) {
            return redirect()->route('ecommerce.home')
                ->with('open_customer_login', true)
                ->with('error', __('Please login to continue checkout'));
        }

        $cart = $this->cart->payload();
        $cartItems = $cart['items'];
        $subtotal = $cart['subtotal'];
        $tax = $cart['tax'];
        $shipping = 0.0;
        $total = round((float) $subtotal + (float) $tax, 2);
        $customer = $this->customerAuth->customer();

        $companyId = app(EcommerceContentService::class)->companyId();
        $outlets = collect();
        $paymentMethods = collect();
        $deliveryAreas = collect();
        try {
            $outlets = Outlet::query()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->where('del_status', 'Live')
                ->where('active_status', 'Active')
                ->orderBy('outlet_name')
                ->get(['id', 'outlet_name', 'address', 'phone']);

            $paymentMethods = $this->orders->onlinePaymentMethods($companyId);

            $deliveryAreas = EcommerceDeliveryArea::query()
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->live()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'amount', 'description']);
        } catch (\Throwable) {
            $outlets = collect();
            $paymentMethods = collect();
            $deliveryAreas = collect();
        }

        return view('ecommerce::pages.checkout', compact(
            'cartItems',
            'subtotal',
            'tax',
            'shipping',
            'total',
            'customer',
            'outlets',
            'paymentMethods',
            'deliveryAreas'
        ));
    }

    public function placeOrder(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse|\Illuminate\View\View
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('ecommerce.home')
                ->with('open_admin_checkout_block', true)
                ->with('error', __('You are logged in as admin. Please login as a customer to checkout.'));
        }

        if (! $this->customerAuth->check()) {
            return redirect()->route('ecommerce.home')
                ->with('error', __('Please login to continue checkout'));
        }

        $cart = $this->cart->payload();

        if (empty($cart['items'])) {
            return redirect()->route('ecommerce.home')
                ->with('error', __('Your cart is empty'));
        }

        $companyId = app(EcommerceContentService::class)->companyId();

        $data = $request->validate([
            'fulfillment_type' => ['required', 'in:delivery,pickup'],
            'outlet_id' => ['nullable', 'integer'],
            'delivery_area_id' => ['nullable', 'integer'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:50'],
            'address' => ['required_if:fulfillment_type,delivery', 'nullable', 'string', 'max:255'],
            'payment_method_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($companyId) {
                    $ok = PaymentMethod::query()
                        ->where('id', $value)
                        ->where('company_id', $companyId)
                        ->where('del_status', 'Live')
                        ->where('status', 'Enable')
                        ->where('enable_in_online', 'Yes')
                        ->exists();
                    if (! $ok) {
                        $fail(__('Please select a valid online payment method.'));
                    }
                },
            ],
        ], [
            'first_name.required' => __('First name is required.'),
            'phone.required_if' => __('Phone number is required for delivery.'),
            'address.required_if' => __('Delivery address is required.'),
            'payment_method_id.required' => __('Please select a payment method.'),
        ]);

        if ($data['fulfillment_type'] === 'pickup' && empty($data['outlet_id'])) {
            return back()->withInput()->withErrors([
                'outlet_id' => __('Please select a pickup branch.'),
            ]);
        }

        $deliveryArea = null;
        if (empty($data['delivery_area_id'])) {
            return back()->withInput()->withErrors([
                'delivery_area_id' => __('Please select a delivery area.'),
            ]);
        }

        $deliveryArea = EcommerceDeliveryArea::query()
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->live()
            ->active()
            ->find((int) $data['delivery_area_id']);

        if (! $deliveryArea) {
            return back()->withInput()->withErrors([
                'delivery_area_id' => __('Please select a valid delivery area.'),
            ]);
        }

        $paymentMethod = PaymentMethod::query()->findOrFail((int) $data['payment_method_id']);

        $outlet = null;
        if (! empty($data['outlet_id'])) {
            $outlet = Outlet::query()
                ->where('id', (int) $data['outlet_id'])
                ->where('del_status', 'Live')
                ->first(['id', 'outlet_name', 'address', 'phone']);
        }

        $sessionCustomer = $this->customerAuth->customer();
        $data['email'] = $sessionCustomer['email'] ?? $data['email'];

        $shipping = $data['fulfillment_type'] === 'pickup'
            ? 0.0
            : round((float) $deliveryArea->amount, 2);
        $total = $data['fulfillment_type'] === 'pickup'
            ? round((float) $cart['subtotal'] + (float) $cart['tax'], 2)
            : round((float) $cart['subtotal'] + (float) $cart['tax'] + $shipping, 2);

        $isGateway = $this->orders->isGatewayMethod($paymentMethod);

        // Immediate paid for Cash/Card; COD stays due; Stripe/PayPal paid after callback.
        $accountType = strtolower((string) $paymentMethod->account_type);
        $markPaid = in_array($accountType, ['cash', 'card', 'bank_account'], true);

        $sale = $this->orders->createSale([
            'customer_id' => $sessionCustomer['id'] ?? null,
            'company_id' => $companyId,
            'outlet_id' => $outlet?->id,
            'fulfillment_type' => $data['fulfillment_type'],
            'payment_method' => $paymentMethod,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'subtotal' => $cart['subtotal'],
            'tax' => $cart['tax'],
            'shipping' => $shipping,
            'total' => $total,
            'items' => $cart['items'],
            'mark_paid' => $markPaid,
        ]);

        if ($isGateway) {
            try {
                $gatewayPayload = $this->orders->startGatewayPayment($sale, $paymentMethod, $data);
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors([
                    'payment_method_id' => $e->getMessage() ?: __('Unable to start payment gateway.'),
                ]);
            }

            Session::put('ecommerce_pending_sale_id', $sale->id);
            Session::put('ecommerce_pending_payment_method_id', $paymentMethod->id);

            // PayPal usually returns redirect_url
            if (! empty($gatewayPayload['redirect_url']) && empty($gatewayPayload['client_secret'])) {
                // Keep cart until payment is confirmed on return URL.
                return redirect()->away($gatewayPayload['redirect_url']);
            }

            // Stripe Payment Element / client secret flow
            if (! empty($gatewayPayload['client_secret'])) {
                // Keep cart until payment is confirmed on return URL.
                return view('ecommerce::pages.payment-stripe', [
                    'sale' => $sale,
                    'paymentMethod' => $paymentMethod,
                    'clientSecret' => $gatewayPayload['client_secret'],
                    'publishableKey' => $gatewayPayload['publishable_key'],
                    'returnUrl' => route('ecommerce.payment.stripe.return', [
                        'sale' => $sale->id,
                        'payment_method_id' => $paymentMethod->id,
                    ]),
                ]);
            }

            return back()->withInput()->withErrors([
                'payment_method_id' => __('Payment gateway did not return a valid checkout URL.'),
            ]);
        }

        $order = $this->saleToOrderPayload($sale, $data, $outlet, $cart['items'], $paymentMethod);
        Session::put('ecommerce_last_order', $order);
        $this->cart->clear();

        return redirect()->route('ecommerce.order-success')
            ->with('success', __('Order placed successfully.'));
    }

    public function paypalReturn(Request $request, int $sale)
    {
        try {
            $saleModel = $this->findPendingOnlineSale($sale);
            $paymentMethod = $this->resolvePendingPaymentMethod($request, $saleModel, 'Paypal');

            if ($request->query('token')) {
                $response = PaymentGateway::retrievePayment(
                    'paypal',
                    (string) $request->query('token'),
                    (int) $paymentMethod->id
                );
            } else {
                throw new \RuntimeException(__('Missing PayPal token.'));
            }

            if (! $response->isSuccess() && ! in_array((string) $response->getStatus(), ['completed', 'succeeded', 'success'], true)) {
                throw new \RuntimeException($response->getMessage() ?: __('PayPal payment was not completed.'));
            }

            $this->orders->markSalePaid($saleModel, $paymentMethod, [
                'gateway' => 'paypal',
                'transaction_id' => $response->getTransactionId(),
                'raw' => $response->getData(),
            ]);

            return $this->finishGatewayCheckout($saleModel->fresh(['saleDetails', 'customer']), $paymentMethod);
        } catch (\Throwable $e) {
            return redirect()->route('ecommerce.checkout')
                ->with('error', $e->getMessage() ?: __('PayPal payment verification failed.'));
        }
    }

    public function paypalCancel(int $sale)
    {
        return redirect()->route('ecommerce.checkout')
            ->with('error', __('PayPal payment was cancelled.'));
    }

    public function stripeReturn(Request $request, int $sale)
    {
        try {
            $saleModel = $this->findPendingOnlineSale($sale);
            $paymentMethod = $this->resolvePendingPaymentMethod($request, $saleModel, 'Stripe');

            $paymentIntentId = $request->query('payment_intent')
                ?: $request->input('payment_intent')
                ?: $request->query('payment_intent_id');

            $redirectStatus = strtolower((string) ($request->query('redirect_status') ?? ''));

            if (! $paymentIntentId) {
                throw new \RuntimeException(__('Missing Stripe payment intent.'));
            }

            if ($redirectStatus !== '' && ! in_array($redirectStatus, ['succeeded', 'processing'], true)) {
                throw new \RuntimeException(__('Stripe payment was not completed.'));
            }

            $response = PaymentGateway::retrievePayment(
                'stripe',
                (string) $paymentIntentId,
                (int) $paymentMethod->id
            );

            $status = strtolower((string) ($response->getStatus() ?? ''));
            $paid = $response->isSuccess()
                || in_array($status, ['completed', 'succeeded', 'success', 'processing'], true)
                || in_array($redirectStatus, ['succeeded', 'processing'], true);

            if (! $paid) {
                throw new \RuntimeException($response->getMessage() ?: __('Stripe payment was not completed.'));
            }

            $this->orders->markSalePaid($saleModel, $paymentMethod, [
                'gateway' => 'stripe',
                'transaction_id' => $response->getTransactionId() ?: $paymentIntentId,
                'raw' => $response->getData(),
            ]);

            return $this->finishGatewayCheckout($saleModel->fresh(['saleDetails', 'customer']), $paymentMethod);
        } catch (\Throwable $e) {
            return redirect()->route('ecommerce.checkout')
                ->with('error', $e->getMessage() ?: __('Stripe payment verification failed.'));
        }
    }

    public function stripeCancel(int $sale)
    {
        return redirect()->route('ecommerce.checkout')
            ->with('error', __('Stripe payment was cancelled.'));
    }

    protected function findPendingOnlineSale(int $saleId): Sale
    {
        return Sale::query()
            ->with(['saleDetails', 'customer'])
            ->where('id', $saleId)
            ->where('online_yes_no', 'Yes')
            ->firstOrFail();
    }

    protected function resolvePendingPaymentMethod(Request $request, Sale $sale, string $accountType): PaymentMethod
    {
        $paymentMethodId = (int) (
            $request->query('payment_method_id')
            ?: Session::get('ecommerce_pending_payment_method_id')
            ?: 0
        );

        if ($paymentMethodId > 0) {
            $method = PaymentMethod::query()->find($paymentMethodId);
            if ($method) {
                return $method;
            }
        }

        $method = PaymentMethod::query()
            ->where('company_id', $sale->company_id)
            ->where('account_type', $accountType)
            ->where('del_status', 'Live')
            ->orderByDesc('id')
            ->first();

        if (! $method) {
            throw new \RuntimeException(__('Payment method not found for this order.'));
        }

        return $method;
    }

    protected function finishGatewayCheckout(Sale $sale, PaymentMethod $paymentMethod)
    {
        $items = $sale->saleDetails->map(fn ($d) => [
            'id' => $d->item_id,
            'name' => $d->menu_note ?: ('Item #' . $d->item_id),
            'price' => (float) $d->menu_unit_price,
            'qty' => (float) $d->qty,
        ])->all();

        $order = $this->saleToOrderPayload($sale, [
            'first_name' => $sale->customer?->name ?: __('Customer'),
            'email' => $sale->customer?->email ?: '',
            'phone' => $sale->customer?->phone ?: '',
            'payment' => $paymentMethod->name,
        ], null, $items, $paymentMethod);

        Session::put('ecommerce_last_order', $order);
        Session::forget(['ecommerce_pending_sale_id', 'ecommerce_pending_payment_method_id']);
        $this->cart->clear();

        return redirect()->route('ecommerce.order-success')
            ->with('success', __('Payment successful. Order placed.'));
    }

    protected function saleToOrderPayload(Sale $sale, array $data, $outlet, array $items, ?PaymentMethod $paymentMethod = null): array
    {
        return [
            'id' => $sale->id,
            'invoice' => $sale->sale_no,
            'customer' => $data,
            'fulfillment_type' => $sale->charge_type === 'Pickup' ? 'pickup' : 'delivery',
            'outlet' => $outlet ? [
                'id' => $outlet->id,
                'name' => $outlet->outlet_name,
                'address' => $outlet->address,
                'phone' => $outlet->phone,
            ] : null,
            'payment_method' => $paymentMethod?->name ?? $sale->account_note,
            'items' => $items,
            'subtotal' => (float) $sale->sub_total,
            'tax' => (float) $sale->vat,
            'shipping' => (float) $sale->delivery_charge,
            'total' => (float) $sale->total_payable,
            'placed_at' => optional($sale->created_at)->toDateTimeString(),
            'online_yes_no' => $sale->online_yes_no,
        ];
    }

    public function orderSuccess()
    {
        $order = Session::get('ecommerce_last_order');

        if (! $order) {
            return redirect()->route('ecommerce.home');
        }

        return view('ecommerce::pages.order-success', compact('order'));
    }

    public function wishlist()
    {
        $wishlistItems = $this->wishlist->items();

        return view('ecommerce::pages.wishlist', compact('wishlistItems'));
    }

    public function toggleWishlist(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->wishlist->toggle((int) $data['product_id']);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return back()->with('success', $result['message']);
    }

    public function removeFromWishlist(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->wishlist->remove((int) $data['product_id']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result);
        }

        return back()->with('success', $result['message']);
    }

    public function customerPanel(Request $request)
    {
        if (! $this->customerAuth->check()) {
            return redirect()->route('ecommerce.login');
        }

        $tab = (string) $request->query('tab', 'dashboard');
        if (! in_array($tab, ['dashboard', 'orders', 'profile', 'password'], true)) {
            $tab = 'dashboard';
        }

        $customer = $this->customerAuth->customer();
        $customerId = (int) ($customer['id'] ?? 0);
        $companyId = app(EcommerceContentService::class)->companyId();

        $ordersQuery = Sale::query()
            ->where('customer_id', $customerId)
            ->where('del_status', 'Live')
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id');

        $totalOrders = (clone $ordersQuery)->count();
        $pendingOrders = (clone $ordersQuery)
            ->where(function ($q) {
                $q->whereNull('close_time')
                    ->orWhere('due_amount', '>', 0);
            })
            ->count();

        $mapOrder = function (Sale $sale) {
            $due = (float) ($sale->due_amount ?? 0);
            $paid = (float) ($sale->paid_amount ?? 0);

            if ($due <= 0) {
                $paymentStatus = 'Paid';
            } elseif ($paid > 0) {
                $paymentStatus = 'Partial';
            } else {
                $paymentStatus = 'Unpaid';
            }

            if (! empty($sale->order_status) && $sale->order_status !== 'Pending') {
                $orderStatus = (string) $sale->order_status;
            } elseif (! empty($sale->delivery_status)) {
                $orderStatus = (string) $sale->delivery_status;
            } elseif (! empty($sale->close_time) && $due <= 0) {
                $orderStatus = 'Completed';
            } else {
                $orderStatus = (string) ($sale->order_status ?: 'Processing');
            }

            $lockedStatuses = ['Delivered', 'Cancelled', 'Rejected', 'Returned'];
            $canCancel = in_array($orderStatus, ['Pending', 'Confirmed'], true)
                && $paymentStatus === 'Unpaid';

            return [
                'id' => (int) $sale->id,
                'encrypted_id' => $sale->encrypted_id,
                'invoice' => $sale->sale_no ?: ('#' . $sale->id),
                'date' => optional($sale->sale_date)->format('Y-m-d') ?: '-',
                'payment_status' => $paymentStatus,
                'order_status' => $orderStatus,
                'amount' => (float) ($sale->total_payable ?? $sale->grand_total ?? 0),
                'can_cancel' => $canCancel && ! in_array($orderStatus, $lockedStatuses, true),
            ];
        };

        $ordersPaginator = null;
        if ($tab === 'orders') {
            $ordersPaginator = (clone $ordersQuery)
                ->paginate(10, ['id', 'sale_no', 'sale_date', 'total_payable', 'grand_total', 'paid_amount', 'due_amount', 'close_time', 'delivery_status', 'order_status', 'payment_status'])
                ->appends(['tab' => 'orders']);
            $ordersPaginator->setCollection(
                $ordersPaginator->getCollection()->map($mapOrder)
            );
            $recentOrders = $ordersPaginator->items();
        } else {
            $recentOrders = (clone $ordersQuery)
                ->limit(5)
                ->get(['id', 'sale_no', 'sale_date', 'total_payable', 'grand_total', 'paid_amount', 'due_amount', 'close_time', 'delivery_status', 'order_status'])
                ->map($mapOrder)
                ->all();
        }

        $wishlistCount = $this->wishlist->count();

        return view('ecommerce::pages.customer-panel', compact(
            'tab',
            'customer',
            'recentOrders',
            'ordersPaginator',
            'totalOrders',
            'pendingOrders',
            'wishlistCount'
        ));
    }

    public function updateCustomerProfile(Request $request): RedirectResponse
    {
        if (! $this->customerAuth->check()) {
            return redirect()->route('ecommerce.login');
        }

        $tab = (string) $request->input('tab', 'profile');

        try {
            if ($tab === 'password') {
                $data = $request->validate([
                    'password' => ['required', 'string', 'min:6', 'confirmed'],
                ]);
                $this->customerAuth->updatePassword($data['password']);
                $message = __('Password updated successfully.');
            } else {
                $data = $request->validate([
                    'first_name' => ['required', 'string', 'max:55'],
                    'last_name' => ['nullable', 'string', 'max:55'],
                    'email' => ['required', 'email', 'max:55'],
                    'phone' => ['required', 'string', 'max:15'],
                    'address' => ['nullable', 'string', 'max:500'],
                    'photo' => ['nullable', 'string'],
                ]);

                if (! empty($data['photo'])) {
                    $data['photo_path'] = $this->customerAuth->storeCroppedPhoto($data['photo']);
                }
                unset($data['photo']);

                $this->customerAuth->updateProfile($data);
                $message = __('Profile updated successfully.');
                $tab = 'profile';
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('ecommerce.customer-panel', ['tab' => $tab])
            ->with('success', $message);
    }

    public function cancelCustomerOrder(string $sale): RedirectResponse
    {
        if (! $this->customerAuth->check()) {
            return redirect()->route('ecommerce.login');
        }

        $customerId = (int) ($this->customerAuth->id() ?? 0);
        $companyId = app(EcommerceContentService::class)->companyId();

        try {
            $saleId = (int) decrypt($sale);
        } catch (\Throwable) {
            return redirect()
                ->route('ecommerce.customer-panel', ['tab' => 'orders'])
                ->with('error', __('Invalid order.'));
        }

        $order = Sale::query()
            ->where('id', $saleId)
            ->where('customer_id', $customerId)
            ->where('del_status', 'Live')
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->first();

        if (! $order) {
            return redirect()
                ->route('ecommerce.customer-panel', ['tab' => 'orders'])
                ->with('error', __('Order not found.'));
        }

        $status = (string) ($order->order_status ?: $order->delivery_status ?: 'Pending');
        $due = (float) ($order->due_amount ?? 0);
        $paid = (float) ($order->paid_amount ?? 0);
        if ($due <= 0) {
            $paymentStatus = 'Paid';
        } elseif ($paid > 0) {
            $paymentStatus = 'Partial';
        } else {
            $paymentStatus = 'Unpaid';
        }

        $canCancel = in_array($status, ['Pending', 'Confirmed'], true) && $paymentStatus === 'Unpaid';
        if (! $canCancel) {
            return redirect()
                ->route('ecommerce.customer-panel', ['tab' => 'orders'])
                ->with('error', __('This order can no longer be cancelled.'));
        }

        $order->update([
            'order_status' => 'Cancelled',
            'delivery_status' => 'Cancelled',
        ]);

        return redirect()
            ->route('ecommerce.customer-panel', ['tab' => 'orders'])
            ->with('success', __('Order cancelled successfully.'));
    }

    public function login()
    {
        if ($this->customerAuth->check()) {
            return redirect()->route('ecommerce.customer-panel');
        }

        return view('ecommerce::pages.login');
    }

    public function customerLogin(Request $request): JsonResponse|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $message = __('You are logged in as admin. Please switch to customer login first.');
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('ecommerce.home')
                ->with('open_admin_checkout_block', true)
                ->with('error', $message);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => __('Please enter your email address.'),
            'email.email' => __('Please enter a valid email address.'),
            'password.required' => __('Please enter your password.'),
        ]);

        try {
            $companyId = app(EcommerceContentService::class)->companyId();
            $customer = $this->customerAuth->login($data['email'], $data['password'], $companyId);
        } catch (\InvalidArgumentException $e) {
            $needsVerify = str_contains(strtolower($e->getMessage()), 'verify');

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'needs_verification' => $needsVerify,
                ], 422);
            }

            $redirect = back()->withInput()->withErrors(['email' => $e->getMessage()]);
            if ($needsVerify) {
                $redirect->with('show_resend_verification', true)
                    ->with('resend_email', $data['email']);
            }

            return $redirect;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => __('Login successful.'),
                'customer' => $customer,
                'redirect' => route('ecommerce.checkout'),
            ]);
        }

        $redirect = $request->session()->pull('url.intended', route('ecommerce.customer-panel'));

        return redirect()->to($redirect)
            ->with('success', __('Login successful.'));
    }

    public function customerLogout(Request $request): RedirectResponse
    {
        $this->customerAuth->logout();

        return redirect()->route('ecommerce.home')
            ->with('success', __('Logged out successfully.'));
    }

    /**
     * End the backend admin session so the visitor can login as a storefront customer.
     * Keeps the current session (cart) intact — only clears the web Auth user.
     */
    public function logoutAdminForCustomer(Request $request): JsonResponse
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        // Force a fresh customer login after leaving the admin session.
        $this->customerAuth->logout();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'csrf_token' => csrf_token(),
            'message' => __('Admin session ended. Please login as a customer.'),
        ]);
    }

    public function register()
    {
        if ($this->customerAuth->check()) {
            return redirect()->route('ecommerce.customer-panel');
        }

        return view('ecommerce::pages.register');
    }

    public function customerRegister(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:55'],
            'last_name' => ['nullable', 'string', 'max:55'],
            'email' => ['required', 'email', 'max:55'],
            'phone' => ['required', 'string', 'max:15'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'first_name.required' => __('Please enter your first name.'),
            'email.required' => __('Please enter your email address.'),
            'email.email' => __('Please enter a valid email address.'),
            'phone.required' => __('Please enter your phone number.'),
            'password.required' => __('Please enter a password.'),
            'password.min' => __('Password must be at least 6 characters.'),
            'password.confirmed' => __('Password confirmation does not match.'),
            'terms.accepted' => __('You must agree with terms and privacy policy.'),
        ]);

        $companyId = app(EcommerceContentService::class)->companyId();

        $result = $this->customerAuth->register($data, $companyId);

        $message = ! empty($result['email_sent'])
            ? __('Account created. Please check your email and click Activate Account to verify your address before logging in.')
            : __('Account created, but we could not send the verification email. Please use Resend verification on the login page.');

        return redirect()->route('ecommerce.login')
            ->with('success', $message)
            ->with('show_resend_verification', true)
            ->with('resend_email', $data['email']);
    }

    public function verifyEmail(Request $request, int $customer): RedirectResponse
    {
        try {
            $this->customerAuth->verifyEmailById($customer);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('ecommerce.login')
                ->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('ecommerce.login')
            ->with('success', __('Your email has been verified. You can log in now.'));
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $companyId = app(EcommerceContentService::class)->companyId();

        try {
            $this->customerAuth->resendVerification($data['email'], $companyId);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('ecommerce.login')
            ->with('success', __('A new verification link has been sent to your email.'))
            ->with('show_resend_verification', true)
            ->with('resend_email', $data['email']);
    }

    public function forgotPassword()
    {
        if ($this->customerAuth->check()) {
            return redirect()->route('ecommerce.customer-panel');
        }

        return view('ecommerce::pages.forgot-password');
    }

    public function forgotPasswordSubmit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => __('Please enter your email address.'),
            'email.email' => __('Please enter a valid email address.'),
        ]);

        $companyId = app(EcommerceContentService::class)->companyId();

        try {
            $this->customerAuth->sendPasswordResetEmail($data['email'], $companyId);
        } catch (ValidationException $e) {
            throw $e;
        }

        return redirect()->route('ecommerce.forgot-password')
            ->with('success', __('We sent a password reset link to your email.'));
    }

    public function resetPassword(Request $request, int $customer)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('ecommerce.forgot-password')
                ->with('error', __('Invalid or expired password reset link.'));
        }

        return view('ecommerce::pages.reset-password', [
            'customerId' => $customer,
            'signatureQuery' => $request->getQueryString(),
        ]);
    }

    public function resetPasswordSubmit(Request $request, int $customer): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('ecommerce.forgot-password')
                ->with('error', __('Invalid or expired password reset link.'));
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.required' => __('Please enter a new password.'),
            'password.min' => __('Password must be at least 6 characters.'),
            'password.confirmed' => __('Password confirmation does not match.'),
        ]);

        try {
            $this->customerAuth->resetPasswordById($customer, $data['password']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('ecommerce.forgot-password')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('ecommerce.login')
            ->with('success', __('Password updated successfully. You can log in now.'));
    }

    public function compare(Request $request)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->query('ids', [])))));
        $compareNotice = null;

        if (count($ids) > 3) {
            $compareNotice = 'Maximum 3 products can be compared at a time. Showing first 3 selected items.';
            $ids = array_slice($ids, 0, 3);
        }

        $compareProducts = [];
        foreach ($ids as $id) {
            $product = $this->findProductById($id);
            if ($product) {
                $compareProducts[] = $product;
            }
        }

        return view('ecommerce::pages.compare', compact('compareProducts', 'compareNotice'));
    }


    private function heroSlides(): array
    {
        return [
            [
                'image' => 'banner.png',
                'alt' => 'Mobile flagship sale banner',
            ],
            [
                'image' => 'banner.png',
                'alt' => 'Fashion week special banner',
            ],
            [
                'image' => 'banner.png',
                'alt' => 'Combo deals banner',
            ],
        ];
    }

    private function popularBrands(): array
    {
        return [
            'Apple',
            'Samsung',
            'Xiaomi',
            'OnePlus',
            'Realme',
            'Nike',
            'Adidas',
            'Levis',
            'Puma',
            'Zara',
        ];
    }

    private function browseCategories(): array
    {
        return [
            [
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'item_count' => '120+ Items',
                'image' => 'cat1.png',
            ],
            [
                'name' => 'Mobile Accessories',
                'slug' => 'mobile-accessories',
                'item_count' => '90+ Items',
                'image' => 'cat2.png',
            ],
            [
                'name' => 'Men Fashion',
                'slug' => 'men-fashion',
                'item_count' => '75+ Items',
                'image' => 'cat3.png',
            ],
            [
                'name' => 'Women Fashion',
                'slug' => 'women-fashion',
                'item_count' => '95+ Items',
                'image' => 'cat4.png',
            ],
            [
                'name' => 'Smartwatches',
                'slug' => 'smartwatches',
                'item_count' => '58+ Items',
                'image' => 'iphone-17.png',
            ],
            [
                'name' => 'Bags & Lifestyle',
                'slug' => 'bags-lifestyle',
                'item_count' => '64+ Items',
                'image' => 'banner.png',
            ],
        ];
    }

    private function findProductById(int $id): ?array
    {
        try {
            $item = Item::query()
                ->with(['category', 'subCategory', 'brand', 'images'])
                ->where('id', $id)
                ->where('del_status', 'Live')
                ->where(function ($q) {
                    $q->whereNull('enable_disable_status')
                        ->orWhere('enable_disable_status', 'Enable');
                })
                ->first();

            if ($item) {
                return $this->mapStockItemToCard($item);
            }
        } catch (\Throwable) {
            // Stock item lookup failed.
        }

        return $this->cart->resolveProduct($id);
    }

    private function paginateProducts(array $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $currentPage = (int) $request->query('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $currentItems = array_slice($items, $offset, $perPage);

        return new LengthAwarePaginator(
            $currentItems,
            count($items),
            $perPage,
            $currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }
}
