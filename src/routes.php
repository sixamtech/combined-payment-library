<?php

use Illuminate\Support\Facades\Route;
use Mdalimrun\CombinedPaymentLibrary\Controllers\SslCommerzPaymentController;

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
        Route::get('pay', 'StripePaymentController@index');
        Route::get('token', 'StripePaymentController@payment_process_3d')->name('token')->WithoutMiddleware('detectUser');
        Route::any('success', 'StripePaymentController@success')->name('success')->WithoutMiddleware('detectUser');
    });

    //RAZOR-PAY
    Route::group(['prefix' => 'razor-pay', 'as' => 'razor-pay.'], function () {
        Route::get('pay', 'RazorPayController@index');
        Route::post('payment', 'RazorPayController@payment')->name('payment')->WithoutMiddleware('detectUser');
    });

    //PAYPAL
    Route::group(['prefix' => 'paypal', 'as' => 'paypal.'], function () {
        Route::get('pay', 'PaypalPaymentController@index');
        Route::any('callback', 'StripePaymentController@callback')->name('callback');
        Route::any('failed', 'StripePaymentController@failed')->name('failed');
    });

    //SENANG-PAY
    Route::group(['prefix' => 'senang-pay', 'as' => 'senang-pay.'], function () {
        Route::get('pay', 'SenangPayController@index');
        //
    });

    //PAYTM
    Route::group(['prefix' => 'paytm', 'as' => 'paytm.'], function () {
        Route::get('pay', 'PaytmController@payment');
        Route::any('response', 'PaytmController@response')->name('response');
    });

    //FLUTTERWAVE
    Route::group(['prefix' => 'flutterwave', 'as' => 'flutterwave.'], function () {
        Route::get('pay', 'FlutterwaveController@initialize')->name('pay');
        Route::get('callback', 'FlutterwaveController@callback')->name('callback')->WithoutMiddleware('detectUser');
    });

    //PAYSTACK
    Route::group(['prefix' => 'paystack', 'as' => 'paystack.'], function () {
        Route::get('pay', 'PaystackController@index')->name('pay');
        Route::post('payment', 'PaystackController@redirectToGateway')->name('payment')->WithoutMiddleware('detectUser');
        Route::get('callback', 'PaystackController@handleGatewayCallback')->name('callback');
    });

});
