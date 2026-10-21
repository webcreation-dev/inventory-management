<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sale\Models\Customer;
use Modules\Stock\Models\Item;

class EcommerceProductRating extends Model
{
    protected $table = 'ecommerce_product_ratings';

    protected $guarded = ['id'];

    protected $casts = [
        'rating' => 'integer',
        'recommend' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? (int) session('company.company_id'));
    }
}
