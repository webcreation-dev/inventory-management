<?php

namespace App\Providers;

use App\Payments\GuzzlePaystackGateway;
use App\Services\PaymentGatewayService;
use Illuminate\Support\ServiceProvider;
use Mdiqbal\LaravelPayments\Core\GatewayResolver;

/**
 * Payment Gateway Service Provider
 * 
 * Registers payment gateway services and facades
 */
class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register PaymentGatewayService as singleton
        $this->app->singleton(PaymentGatewayService::class, function ($app) {
            return new PaymentGatewayService();
        });

        // Alias for easier access
        $this->app->alias(PaymentGatewayService::class, 'payment.gateway');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (class_exists(GatewayResolver::class)) {
            $this->app->make(GatewayResolver::class)->registerGateway('paystack', GuzzlePaystackGateway::class);
        }
    }
}
