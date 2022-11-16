<?php

namespace Mdalimrun\CombinedPaymentLibrary;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

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
            __DIR__ . '/views' => base_path('resources/views/combined-payment-views'),
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
        $this->app->make('Mdalimrun\CombinedPaymentLibrary\Controllers\SslCommerzPaymentController');
        // Views
        $this->loadViewsFrom(__DIR__ . '/views', 'combined-payment-client');
    }
}

