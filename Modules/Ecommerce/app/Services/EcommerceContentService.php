<?php

namespace Modules\Ecommerce\Services;

use Modules\Configuration\Models\Company;
use Modules\Ecommerce\Models\EcommerceBanner;
use Modules\Ecommerce\Models\EcommerceFeaturedProduct;
use Modules\Ecommerce\Models\EcommerceFlashSale;
use Modules\Ecommerce\Models\EcommerceHomeCategory;
use Modules\Ecommerce\Models\EcommerceNewTrend;
use Modules\Ecommerce\Models\EcommercePopularBrand;
use Modules\Ecommerce\Models\EcommercePopularProduct;
use Modules\Ecommerce\Models\EcommerceSetting;
use Modules\Stock\Models\Item;
use Illuminate\Support\Facades\DB;

class EcommerceContentService
{
    public function __construct(
        protected EcommerceMediaService $media,
    ) {
    }

    public function companyId(): int
    {
        $sessionId = (int) session('company.company_id');
        if ($sessionId > 0) {
            return $sessionId;
        }

        $configured = (int) config('ecommerce.default_company_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        $fromSettings = (int) EcommerceSetting::query()
            ->where('del_status', 'Live')
            ->orderBy('id')
            ->value('company_id');
        if ($fromSettings > 0) {
            return $fromSettings;
        }

        try {
            return (int) Company::query()->where('del_status', 'Live')->orderBy('id')->value('id');
        } catch (\Throwable) {
            return 0;
        }
    }

    public function settings(): ?EcommerceSetting
    {
        $companyId = $this->companyId();
        if ($companyId <= 0) {
            return null;
        }

        return EcommerceSetting::query()
            ->forCompany($companyId)
            ->where('del_status', 'Live')
            ->first();
    }

    public function branding(): array
    {
        $setting = null;
        try {
            $setting = $this->settings();
        } catch (\Throwable) {
            $setting = null;
        }

        return [
            'siteName' => $setting?->site_name ?: 'NexaShop',
            'siteLogo' => $setting?->logo ? $this->media->url($setting->logo) : null,
            'footerLogo' => $setting?->footer_logo ? $this->media->url($setting->footer_logo) : null,
            'siteFavicon' => $setting?->favicon ? $this->media->url($setting->favicon) : null,
            'brandColor' => \Modules\Ecommerce\Support\BrandColor::normalize($setting?->brand_color ?? null),
            'brandPalette' => \Modules\Ecommerce\Support\BrandColor::palette($setting?->brand_color ?? null),
            'storefrontLanguages' => $this->storefrontLanguages($setting),
            'contactPhone' => $setting?->contact_phone,
            'contactEmail' => $setting?->contact_email,
            'contactAddress' => $setting?->contact_address,
            'footerDescription' => $setting?->footer_description,
            'appLink' => $setting?->app_link,
            'showAdminLoginUrl' => ($setting?->show_admin_login_url ?? 'No') === 'Yes',
            'socialFacebook' => $setting?->social_facebook,
            'socialInstagram' => $setting?->social_instagram,
            'socialTiktok' => $setting?->social_tiktok,
            'socialLinkedin' => $setting?->social_linkedin,
        ];
    }

    /**
     * @return list<string>
     */
    public function storefrontLanguages(?EcommerceSetting $setting = null): array
    {
        try {
            $setting ??= $this->settings();
        } catch (\Throwable) {
            $setting = null;
        }

        if ($setting) {
            return $setting->enabledStorefrontLanguages();
        }

        return ['en'];
    }

    public function banners()
    {
        return EcommerceBanner::query()
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->orderByDesc('id')
            ->get();
    }

    public function homeCategories()
    {
        return EcommerceHomeCategory::query()
            ->with('category')
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->get()
            ->filter(fn ($row) => $row->category && $row->category->del_status === 'Live')
            ->take(12)
            ->values();
    }

    public function popularBrands()
    {
        return EcommercePopularBrand::query()
            ->with('brand')
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->get()
            ->filter(fn ($row) => $row->brand && $row->brand->del_status === 'Live');
    }

    public function featuredProducts()
    {
        return EcommerceFeaturedProduct::query()
            ->with(['item.brand', 'item.category', 'item.images'])
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->get()
            ->filter(fn ($row) => $row->item
                && $row->item->del_status === 'Live'
                && ($row->item->enable_disable_status ?? 'Enable') === 'Enable'
                && (int) ($row->item->parent_id ?? 0) === 0);
    }

    public function newTrendProducts()
    {
        return EcommerceNewTrend::query()
            ->with(['item.brand', 'item.category', 'item.images'])
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->get()
            ->filter(fn ($row) => $row->item
                && $row->item->del_status === 'Live'
                && ($row->item->enable_disable_status ?? 'Enable') === 'Enable'
                && (int) ($row->item->parent_id ?? 0) === 0);
    }

    public function popularProducts()
    {
        return EcommercePopularProduct::query()
            ->with(['item.brand', 'item.category', 'item.images'])
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderBy('sort_id')
            ->get()
            ->filter(fn ($row) => $row->item
                && $row->item->del_status === 'Live'
                && ($row->item->enable_disable_status ?? 'Enable') === 'Enable'
                && (int) ($row->item->parent_id ?? 0) === 0);
    }

    /**
     * Newest live products when New Trend CMS is empty.
     */
    public function fallbackNewTrendItems(int $limit = 8)
    {
        $companyId = $this->companyId();

        return Item::query()
            ->with(['brand', 'category', 'images'])
            ->where('del_status', 'Live')
            ->where(function ($q) {
                $q->whereNull('enable_disable_status')->orWhere('enable_disable_status', 'Enable');
            })
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Top sold products when Most Popular CMS is empty.
     */
    public function fallbackPopularItems(int $limit = 8)
    {
        $companyId = $this->companyId();
        $ids = [];

        try {
            $ids = DB::table('sale_details')
                ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                ->when($companyId > 0, fn ($q) => $q->where('sales.company_id', $companyId))
                ->where('sales.del_status', 'Live')
                ->select('sale_details.item_id', DB::raw('SUM(sale_details.qty) as sold_qty'))
                ->groupBy('sale_details.item_id')
                ->orderByDesc('sold_qty')
                ->limit($limit)
                ->pluck('item_id')
                ->all();
        } catch (\Throwable) {
            $ids = [];
        }

        if ($ids) {
            $items = Item::query()
                ->with(['brand', 'category', 'images'])
                ->whereIn('id', $ids)
                ->where('del_status', 'Live')
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhere('parent_id', 0);
                })
                ->get()
                ->sortBy(fn ($item) => array_search($item->id, $ids))
                ->values();

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        return $this->fallbackNewTrendItems($limit);
    }

    public function activeFlashSale(): ?EcommerceFlashSale
    {
        $sales = EcommerceFlashSale::query()
            ->with(['items.item.brand', 'items.item.category', 'items.item.images'])
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->orderByDesc('id')
            ->get();

        return $sales->first(fn (EcommerceFlashSale $sale) => $sale->isRunning());
    }

    /**
     * Active offers that are running or not yet ended (for storefront listing).
     *
     * @return \Illuminate\Support\Collection<int, EcommerceFlashSale>
     */
    public function storefrontOffers()
    {
        return EcommerceFlashSale::query()
            ->withCount('items')
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', now()->toDateString());
            })
            ->orderByDesc('id')
            ->get();
    }

    public function findStorefrontOffer(int $id): ?EcommerceFlashSale
    {
        return EcommerceFlashSale::query()
            ->forCompany($this->companyId())
            ->live()
            ->active()
            ->where('id', $id)
            ->first();
    }

    public function media(): EcommerceMediaService
    {
        return $this->media;
    }
}
