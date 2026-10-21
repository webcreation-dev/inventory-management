<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stock\Models\Item;

class EcommerceNewTrend extends Model
{
    protected $table = 'ecommerce_new_trends';

    protected $guarded = ['id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
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
