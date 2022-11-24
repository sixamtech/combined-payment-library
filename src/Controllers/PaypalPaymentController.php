<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;
use Srmklive\PayPal\Services\ExpressCheckout;

class PaypalPaymentController extends Controller
{
    use Processor;

    private $config_values;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('paypal', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        $config = array(
            'mode' => env('PAYPAL_MODE', $config->mode == 'live' ? 'live' : 'sandbox'),
            'sandbox' => [
                'username' => env('PAYPAL_SANDBOX_API_USERNAME', $this->config_values->username),
                'password' => env('PAYPAL_SANDBOX_API_PASSWORD', $this->config_values->password),
                'secret' => env('PAYPAL_SANDBOX_API_SECRET', $this->config_values->secret),
                'certificate' => env('PAYPAL_SANDBOX_API_CERTIFICATE', $this->config_values->certificate),
                'app_id' => $this->config_values->app_id,
            ],

            'live' => [
                'username' => env('PAYPAL_LIVE_API_USERNAME', $this->config_values->username),
                'password' => env('PAYPAL_LIVE_API_PASSWORD', $this->config_values->password),
                'secret' => env('PAYPAL_LIVE_API_SECRET', $this->config_values->secret),
                'certificate' => env('PAYPAL_LIVE_API_CERTIFICATE', $this->config_values->certificate),
                'app_id' => $this->config_values->app_id,
            ],
            'payment_action' => 'Sale',
            'currency' => env('PAYPAL_CURRENCY', 'USD'),
            'billing_type' => 'MerchantInitiatedBilling',
            'notify_url' => '',
            'locale' => '',
            'validate_ssl' => false,
        );
        Config::set('paypal', $config);

        $this->payment = $payment;
    }

    /**
     * Responds with a welcome message with instructions
     *
     */
    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(DEFAULT_204), 200);
        }

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
        } else {
            $business_name = "my_business";
        }

        $payment_data = [];
        $payment_data['items'] = [
            [
                'name' => $business_name,
                'price' => round($data->payment_amount, 2),
                'desc' => 'payment ID :' . $data->id,
                'qty' => 1
            ]
        ];

        $payment_data['invoice_id'] = $data->id;
        $payment_data['invoice_description'] = "Order #{$payment_data['invoice_id']} Invoice";
        $payment_data['return_url'] = route('paypal.success', ['payment_id' => $data->id]);
        $payment_data['cancel_url'] = route('paypal.cancel', ['payment_id' => $data->id]);
        $payment_data['total'] = round($data->payment_amount, 2);

        $provider = new ExpressCheckout;
        $response = $provider->setExpressCheckout($payment_data);
        //$response = $provider->setExpressCheckout($data, true);

        return redirect($response['paypal_link']);
    }

    /**
     * Responds with a welcome message with instructions
     */
    public function cancel(Request $request)
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->first();
        if ($data['callback'] != null) {
            return redirect($data['callback'] . '?payment_status=cancel');
        }
        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }

    /**
     * Responds with a welcome message with instructions
     */
    public function success(Request $request)
    {
        $provider = new ExpressCheckout;
        $response = $provider->getExpressCheckoutDetails($request->token);

        if (in_array(strtoupper($response['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])) {
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'paypal',
                    'transaction_id' => $request->input('payment_id'),
                    'payment_id' => $request->input('payment_id'),
                ]);
                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'paypal',
                    'is_paid' => 1,
                    'transaction_id' => $request->input('payment_id'),
                ]);
            }
            if ($data['callback'] != null) {
                return redirect($data['callback'] . '?payment_status=success');
            }
            return response()->json($this->response_formatter(DEFAULT_200), 200);
        }

        return response()->json($this->response_formatter(DEFAULT_404), 200);
    }
}
