<?php

use Illuminate\Support\Facades\Route;
use Mdalimrun\CombinedPaymentLibrary\Controllers\BkashPaymentController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\FlutterwaveV3Controller;
use Mdalimrun\CombinedPaymentLibrary\Controllers\LiqPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\MercadoPagoController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaymobController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaypalPaymentController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaystackController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaytabsController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaytmController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\RazorPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\SenangPayController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\SslCommerzPaymentController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\StripePaymentController;

Route::group(['prefix' => 'payment'], function () {

    //SSLCOMMERZ
    Route::group(['prefix' => 'sslcommerz', 'as' => 'sslcommerz.'], function () {
        Route::get('pay', [SslCommerzPaymentController::class, 'index']);
        Route::post('success', [SslCommerzPaymentController::class, 'success']);
        Route::post('failed', [SslCommerzPaymentController::class, 'failed']);
        Route::post('canceled', [SslCommerzPaymentController::class, 'canceled']);
    });

    //STRIPE
    Route::group(['prefix' => 'stripe', 'as' => 'stripe.'], function () {
        Route::get('pay', [StripePaymentController::class, 'index']);
        Route::get('token', [StripePaymentController::class, 'payment_process_3d'])->name('token');
        Route::get('success', [StripePaymentController::class, 'success'])->name('success');
    });

    //RAZOR-PAY
    Route::group(['prefix' => 'razor-pay', 'as' => 'razor-pay.'], function () {
        Route::get('pay', [RazorPayController::class, 'index']);
        Route::post('payment', [RazorPayController::class, 'payment'])->name('payment');
    });

    //PAYPAL
    Route::group(['prefix' => 'paypal', 'as' => 'paypal.'], function () {
        Route::get('pay', [PaypalPaymentController::class, 'payment']);
        Route::any('callback', [PaypalPaymentController::class, 'callback'])->name('callback');
        Route::any('canceled',  [PaypalPaymentController::class,'canceled'])->name('canceled');
    });

    //SENANG-PAY
    Route::group(['prefix' => 'senang-pay', 'as' => 'senang-pay.'], function () {
        Route::get('pay', [SenangPayController::class, 'index']);
        Route::any('callback', [SenangPayController::class, 'return_senang_pay']);
    });

    //PAYTM
    Route::group(['prefix' => 'paytm', 'as' => 'paytm.'], function () {
        Route::get('pay', [PaytmController::class, 'payment']);
        Route::any('response', [PaytmController::class, 'response'])->name('response');
    });

    //FLUTTERWAVE
    Route::group(['prefix' => 'flutterwave-v3', 'as' => 'flutterwave-v3.'], function () {
        Route::get('pay', [FlutterwaveV3Controller::class, 'initialize'])->name('pay');
        Route::get('callback', [FlutterwaveV3Controller::class, 'callback'])->name('callback');
    });

    //PAYSTACK
    Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
        Route::get('pay', [PaystackController::class, 'index'])->name('pay');
        Route::post('payment', [PaystackController::class, 'redirectToGateway'])->name('payment');
        Route::get('callback', [PaystackController::class, 'handleGatewayCallback'])->name('callback');
    });

    //BKASH
    Route::group(['prefix' => 'bkash'], function () {
        // Payment Routes for bKash
        Route::post('get-token', [BkashPaymentController::class, 'getToken'])->name('bkash-get-token');
        Route::post('create-payment', [BkashPaymentController::class, 'createPayment'])->name('bkash-create-payment');
        Route::post('execute-payment', [BkashPaymentController::class, 'executePayment'])->name('bkash-execute-payment');
        Route::get('query-payment', [BkashPaymentController::class, 'queryPayment'])->name('bkash-query-payment');
        Route::post('success', [BkashPaymentController::class, 'bkashSuccess'])->name('bkash-success');
        Route::get('callback', [BkashPaymentController::class, 'callback'])->name('bkash-callback');
    });

    //PAYSTACK
    Route::group(['prefix' => 'liqpay', 'as' => 'liqpay.'], function () {
        Route::get('payment', [LiqPayController::class, 'payment'])->name('payment');
        Route::any('callback', [LiqPayController::class, 'callback'])->name('callback');
    });

    //MERCADOPAGO
    Route::group(['prefix' => 'mercadopago', 'as' => 'mercadopago.'], function () {
        Route::get('pay', [MercadoPagoController::class, 'index'])->name('index');
        Route::post('make-payment', [MercadoPagoController::class, 'make_payment'])->name('make_payment');
    });

    // paymob
    Route::group(['prefix' => 'paymob', 'as' => 'paymob.'], function () {
        Route::any('pay', [PaymobController::class, 'credit'])->name('pay');
        Route::any('callback', [PaymobController::class, 'callback'])->name('callback');
    });

    // paymob
    Route::group(['prefix' => 'paytabs', 'as' => 'paytabs.'], function () {
        Route::any('pay', [PaytabsController::class, 'payment'])->name('pay');
        Route::any('callback', [PaytabsController::class, 'callback'])->name('callback');
        Route::any('response', [PaytabsController::class, 'response'])->name('response');
    });

});
