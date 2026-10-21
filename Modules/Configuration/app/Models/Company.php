<?php

namespace Modules\Configuration\Models;

use App\Models\SaasPricingPlanStub;
use App\Models\SaasSubscriptionStub;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected $table = 'companies';

    protected $append = [
        'encrypted_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_usage' => 'array',
            'is_saas' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the encrypted ID attribute
     */
    public function getEncryptedIdAttribute()
    {
        return encrypt($this->id);
    }

    public function saasPricingPlan(): BelongsTo
    {
        $saasPlanClass = 'Modules\\SaaS\\Models\\PricingPlan';
        $related = class_exists($saasPlanClass) ? $saasPlanClass : SaasPricingPlanStub::class;

        return $this->belongsTo($related, 'plan_id');
    }

    public function primaryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptions(): HasMany
    {
        $subscriptionClass = 'Modules\\SaaS\\Models\\Subscription';
        $related = class_exists($subscriptionClass) ? $subscriptionClass : SaasSubscriptionStub::class;

        return $this->hasMany($related, 'company_id');
    }

    /**
     * Latest Live subscription row (for list UIs — avoids loading full history).
     */
    public function latestLiveSubscription(): HasOne
    {
        $subscriptionClass = 'Modules\\SaaS\\Models\\Subscription';
        $related = class_exists($subscriptionClass) ? $subscriptionClass : SaasSubscriptionStub::class;

        return $this->hasOne($related, 'company_id')
            ->ofMany(
                ['id' => 'max'],
                function ($query) {
                    $query->where('del_status', 'Live')
                        ->where('status', '!=', 'cancelled');
                }
            );
    }
}

