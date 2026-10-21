<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceSetting extends Model
{
    protected $table = 'ecommerce_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'storefront_languages' => 'array',
    ];

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? (int) session('company.company_id'));
    }

    public static function forCurrentCompany(): self
    {
        $companyId = (int) session('company.company_id');

        return static::query()->firstOrCreate(
            ['company_id' => $companyId],
            ['site_name' => session('company.business_name'), 'del_status' => 'Live']
        );
    }

    /**
     * Locales enabled for the public storefront.
     *
     * @return list<string>
     */
    public function enabledStorefrontLanguages(): array
    {
        $available = config('app.available_locales', ['en']);
        $selected = $this->storefront_languages;

        if (! is_array($selected) || $selected === []) {
            return array_values(array_intersect(['fr'], $available)) ?: ['fr'];
        }

        $enabled = array_values(array_intersect($available, $selected));

        return $enabled !== [] ? $enabled : ['fr'];
    }
}
