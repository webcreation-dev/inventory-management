<?php

namespace Modules\Accounting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
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


    // A recovered SQL schema can contain a nullable column named category.
    // Resolve the category relationship rather than that legacy placeholder.
    public function getCategoryAttribute()
    {
        return $this->getRelationValue('category');
    }

    public function getAccountAttribute()
    {
        return $this->getRelationValue('account');
    }

    public function getEmployeeAttribute()
    {
        return $this->getRelationValue('employee');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
    
    public function account()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }


    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }
}

