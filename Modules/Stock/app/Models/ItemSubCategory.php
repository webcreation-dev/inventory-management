<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemSubCategory extends Model
{
    protected $table = 'item_sub_categories';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'sort_id',
        'company_id',
        'user_id',
        'del_status',
    ];

    protected $casts = [
        'sort_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'encrypted_id',
    ];

    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'sub_category_id');
    }

    public function scopeLive($query)
    {
        return $query->where('del_status', 'Live');
    }

    public function scopeForCompany($query, ?int $companyId = null)
    {
        return $query->where('company_id', $companyId ?? (int) session('company.company_id'));
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
