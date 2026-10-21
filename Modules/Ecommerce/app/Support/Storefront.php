<?php

namespace Modules\Ecommerce\Support;

use Modules\Configuration\Models\Company;
use Modules\Ecommerce\Services\EcommerceContentService;

class Storefront
{
    /**
     * Env EC flag — enables root + /web storefront routing.
     */
    public static function envEnabled(): bool
    {
        return (bool) config('app.ec', false);
    }

    /**
     * Public URL prefix for storefront pages (excluding home when EC is on).
     */
    public static function pathPrefix(): string
    {
        return self::envEnabled() ? 'web' : 'ecommerce';
    }

    /**
     * Company setting: Settings → All Setting → E-Commerce Checker = Yes.
     * Always disabled when LP=BD.
     */
    public static function checkerEnabled(): bool
    {
        if (self::lpIsBd()) {
            return false;
        }

        try {
            $companyId = app(EcommerceContentService::class)->companyId();
            if ($companyId <= 0) {
                return false;
            }

            $checker = Company::query()
                ->where('id', $companyId)
                ->where('del_status', 'Live')
                ->value('e_commerce_checker');

            return $checker === 'Yes';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * LP=BD package build — ecommerce storefront is unavailable.
     */
    public static function lpIsBd(): bool
    {
        if (defined('LP') && strtoupper((string) LP) === 'BD') {
            return true;
        }

        return strtoupper((string) env('LP', '')) === 'BD';
    }

    /**
     * Root URL should serve the ecommerce home.
     */
    public static function servesAtRoot(): bool
    {
        return self::envEnabled() && self::checkerEnabled();
    }
}
