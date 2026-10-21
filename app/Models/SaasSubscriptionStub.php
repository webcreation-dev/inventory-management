<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fallback related model when Modules\SaaS is not installed; keeps Company subscription relations definable.
 */
class SaasSubscriptionStub extends Model
{
    protected $table = 'subscriptions';
}
