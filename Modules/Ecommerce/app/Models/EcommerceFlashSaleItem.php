<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stock\Models\Item;

class EcommerceFlashSaleItem extends Model
{
    protected $table = 'ecommerce_flash_sale_items';

    protected $guarded = ['id'];

    protected $casts = [
        'flash_price' => 'decimal:3',
    ];

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(EcommerceFlashSale::class, 'flash_sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
