<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcommerceOrder extends Model
{
    protected $table = 'ecommerce_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'subtotal' => 'float',
        'tax' => 'float',
        'shipping' => 'float',
        'total' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class, 'order_id');
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
