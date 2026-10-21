<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stock\Models\ItemCategory;

class EcommerceHomeCategory extends Model
{
    protected $table = 'ecommerce_home_categories';

    protected $guarded = ['id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? (int) session('company.company_id'));
    }
}
