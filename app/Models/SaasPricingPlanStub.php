<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fallback related model when Modules\SaaS is not installed; keeps Company::saasPricingPlan() loadable.
 */
class SaasPricingPlanStub extends Model
{
    protected $table = 'saas_pricing_plans';
}
