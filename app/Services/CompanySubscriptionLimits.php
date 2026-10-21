<?php

namespace App\Services;

/**
 * Delegates to Modules\SaaS\Services\CompanySubscriptionLimitService when that class exists.
 * When the SaaS module is removed, all limits are treated as disabled (always allow).
 */
class CompanySubscriptionLimits
{
    private const SAAS_SERVICE = 'Modules\\SaaS\\Services\\CompanySubscriptionLimitService';

    public static function isActive(): bool
    {
        return class_exists(self::SAAS_SERVICE);
    }

    public static function canAddUsers(int $addCount = 1, ?int $companyId = null): bool
    {
        if (! self::isActive()) {
            return true;
        }

        return (bool) \call_user_func([self::SAAS_SERVICE, 'canAddUsers'], $addCount, $companyId);
    }

    public static function canAddOutlets(int $addCount = 1, ?int $companyId = null): bool
    {
        if (! self::isActive()) {
            return true;
        }

        return (bool) \call_user_func([self::SAAS_SERVICE, 'canAddOutlets'], $addCount, $companyId);
    }

    public static function canCreateInvoices(int $addCount = 1, ?int $companyId = null): bool
    {
        if (! self::isActive()) {
            return true;
        }

        return (bool) \call_user_func([self::SAAS_SERVICE, 'canCreateInvoices'], $addCount, $companyId);
    }

    public static function userLimitMessage(): string
    {
        return self::isActive() ? (string) \call_user_func([self::SAAS_SERVICE, 'userLimitMessage']) : '';
    }

    public static function outletLimitMessage(): string
    {
        return self::isActive() ? (string) \call_user_func([self::SAAS_SERVICE, 'outletLimitMessage']) : '';
    }

    public static function invoiceLimitMessage(): string
    {
        return self::isActive() ? (string) \call_user_func([self::SAAS_SERVICE, 'invoiceLimitMessage']) : '';
    }
}
