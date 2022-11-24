<?php

namespace Mdalimrun\CombinedPaymentLibrary;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Mdalimrun\CombinedPaymentLibrary\Controllers\BkashPaymentController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\LiqPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\MercadoPagoController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaystackController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaytmController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\RazorPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\SenangPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\SslCommerzPaymentController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\StripePaymentController;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        include __DIR__ . '/routes.php';
        // To publish views & migrations
        $this->publishes([
            __DIR__ . '/views' => base_path('resources/views/payments'),
            __DIR__ . '/migrations' => base_path('database/migrations'),
        ]);
    }

    /**
     * Register the application services.
     * @return void
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        // Controllers
        $this->app->make(SslCommerzPaymentController::class);
        $this->app->make(RazorPayController::class);
        $this->app->make(StripePaymentController::class);
        $this->app->make(PaytmController::class);
        $this->app->make(SenangPayController::class);
        $this->app->make(PaystackController::class);
        $this->app->make(BkashPaymentController::class);
        $this->app->make(LiqPayController::class);
        $this->app->make(MercadoPagoController::class);

        // Models
        $this->app->make(Payment::class);

        // Views
        $this->loadViewsFrom(__DIR__ . '/views', 'payments');
    }
}

