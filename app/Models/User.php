<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Modules\Configuration\Models\Company;
use Modules\Configuration\Models\Outlet;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'zk_user_id',
        'salary',
        'commission',
        'outlet_id',
        'will_login',
        'del_status',
        'photo',
        'discount_permission_code',
        'discount_amt',
        'start_date',
        'end_date',
        'company_id',
        'two_factor_enabled',
        'session_timeout',
        'login_notifications',
        'question',
        'answer',
        'language',
    ];

    protected $append = [
        'encrypted_id',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_delatable' => 'boolean',
        ];
    }

    /**
     * Platform / SaaS operator (can manage users marked non-deletable, except the protected main account).
     */
    public function isSaasPlatformOperator(): bool
    {
        if (strtolower((string) ($this->is_saas ?? '')) === 'check') {
            return true;
        }

        if (! $this->company_id) {
            return false;
        }

        $company = Company::query()->find($this->company_id);

        return $company
            && $company->is_saas
            && (int) $company->user_id === (int) $this->id;
    }

    /**
     * Landlord / platform account (users.is_saas = "check") — SaaS admin: plans, all companies.
     */
    public function isSaasLandlordOperator(): bool
    {
        return strtolower((string) ($this->is_saas ?? '')) === 'check';
    }

    /**
     * SaaS module menu and routes: main system admin only (users.id = 1 and users.role = 1).
     */
    public function isSaaSModuleAdmin(): bool
    {
        return (int) $this->id === 1 && (int) ($this->role ?? 0) === 1;
    }

    /**
     * Root account for the SaaS landlord company (or legacy seeded id 1 / company 1) — never delete when not delatable.
     */
    public function isProtectedMainSaaSUser(): bool
    {
        if ($this->is_delatable) {
            return false;
        }

        if (! $this->company_id) {
            return false;
        }

        $company = Company::query()->find($this->company_id);

        if ($company && $company->is_saas && (int) $company->user_id === (int) $this->id) {
            return true;
        }

        if ((int) $this->id === 1 && (int) $this->company_id === 1) {
            return true;
        }

        return false;
    }

    /**
     * Whether the given actor may delete this user (UI + server must match).
     */
    public function canBeDeletedBy(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($this->isProtectedMainSaaSUser()) {
            return false;
        }

        if ($this->is_delatable) {
            return true;
        }

        return $actor->isSaasPlatformOperator();
    }


    /**
     * Get the role associated with the user
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the outlets associated with the user
     * Note: outlet_id is stored as comma-separated IDs
     */
    public function getOutletsAttribute()
    {
        if (!$this->outlet_id) {
            return collect([]);
        }
        $outletIds = explode(',', $this->outlet_id);
        return Outlet::whereIn('id', $outletIds)->get();
    }

    /**
     * Get the medicine count
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    public function expense()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Resolve route binding using encrypted ID
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', decrypt($value))->first();
    }

}

