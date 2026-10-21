<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'banner',
        'show_in_header',
        'header_sort_id',
        'sort_id',
        'company_id',
        'user_id',
        'del_status'
    ];

    protected $casts = [
        'sort_id' => 'integer',
        'header_sort_id' => 'integer',
        'show_in_header' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'encrypted_id',
        'banner_url',
    ];

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (! $this->banner) {
            return null;
        }

        return asset('public/uploads/categories/' . $this->banner);
    }

    /**
     * Get the user who created this item category
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Get the user who last updated this item category
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Scope for active item categories
     */
    public function scopeActive($query)
    {
        return $query->where('del_status', 'Live');
    }

    /**
     * Scope for inactive item categories
     */
    public function scopeInactive($query)
    {
        return $query->where('del_status', 'Deleted');
    }

    public function subCategories()
    {
        return $this->hasMany(ItemSubCategory::class, 'category_id');
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }
}
