<?php

namespace Modules\PrintManager\Models;

use Illuminate\Database\Eloquent\Model;

class PrintSetting extends Model
{
    protected $table = 'print_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'copies' => 'integer',
        'margins' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isCashDrawerEnabled(): bool
    {
        return $this->cash_drawer === 'ON';
    }
}
