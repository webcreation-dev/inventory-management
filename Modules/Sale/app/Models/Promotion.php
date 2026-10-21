<?php

namespace Modules\Sale\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $append = [
        'encrypted_id',
    ];

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

