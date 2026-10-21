<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrderItem extends Model
{
    protected $table = 'ecommerce_order_items';

    protected $guarded = ['id'];

    protected $casts = [
        'unit_price' => 'float',
        'qty' => 'integer',
        'line_total' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'order_id');
    }
}
