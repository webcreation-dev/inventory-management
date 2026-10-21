<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceBanner extends Model
{
    protected $table = 'ecommerce_banners';

    protected $guarded = ['id'];

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
