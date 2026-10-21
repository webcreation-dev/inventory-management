<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceDeliveryArea extends Model
{
    protected $table = 'ecommerce_delivery_areas';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
    ];

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
