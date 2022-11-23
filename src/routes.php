<?php

use Illuminate\Support\Facades\Route;
use Mdalimrun\CombinedPaymentLibrary\Controllers\FlutterwaveController;
use Mdalimrun\CombinedPaymentLibrary\Controllers\PaystackController;
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
        Route::get('pay', 'PaypalPaymentController@index');
        Route::any('callback', 'PaypalPaymentController@callback')->name('callback');
        Route::any('failed', 'PaypalPaymentController@failed')->name('failed');
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
    Route::group(['prefix' => 'flutterwave', 'as' => 'flutterwave.'], function () {
        Route::get('pay', [FlutterwaveController::class,'initialize'])->name('pay');
        Route::get('callback', [FlutterwaveController::class,'callback'])->name('callback');
    });

    //PAYSTACK
    Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
        Route::get('pay', [PaystackController::class, 'index'])->name('pay');
        Route::post('payment', [PaystackController::class, 'redirectToGateway'])->name('payment');
        Route::get('callback', [PaystackController::class, 'handleGatewayCallback'])->name('callback');
    });

    //BKASH
    Route::group(['prefix' => 'bkash', 'as' => 'bkash.'], function () {
        // Payment Routes for bKash
        Route::post('get-token', 'BkashController@getToken')->name('bkash-get-token');
        Route::post('create-payment', 'BkashController@createPayment')->name('bkash-create-payment');
        Route::post('execute-payment', 'BkashController@executePayment')->name('bkash-execute-payment');
        Route::get('query-payment', 'BkashController@queryPayment')->name('bkash-query-payment');
        Route::post('success', 'BkashController@bkashSuccess')->name('bkash-success');
        Route::get('callback', 'BkashController@callback')->name('bkash-callback');

        // Refund Routes for bKash
        Route::get('refund', 'BkashRefundController@index')->name('bkash-refund');
        Route::post('refund', 'BkashRefundController@refund')->name('bkash-refund');
    });

});
