<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceContact extends Model
{
    protected $table = 'ecommerce_contacts';

    protected $guarded = ['id'];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? (int) session('company.company_id'));
    }

    public function getIsRepliedAttribute(): bool
    {
        return ! empty($this->replied_at);
    }
}
