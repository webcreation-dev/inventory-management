<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemImage extends Model
{
    protected $table = 'item_images';

    protected $fillable = [
        'item_id',
        'image',
        'sort_id',
        'company_id',
    ];

    protected $appends = [
        'url',
        'encrypted_id',
    ];

    public function getUrlAttribute(): string
    {
        return asset('public/uploads/items/' . $this->image);
    }

    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
