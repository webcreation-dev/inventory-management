<?php

namespace Modules\PrintManager\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $table = 'print_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
    ];
}
