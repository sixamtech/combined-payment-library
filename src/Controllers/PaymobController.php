<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class PaymobController extends Controller
{
    use Processor;

    private $config_values;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('paymob_accept', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }
        $this->payment = $payment;
    }

    protected function cURL($url, $json)
    {
        // Create curl resource
        $ch = curl_init($url);

        // Request headers
        $headers = array();
        $headers[] = 'Content-Type: application/json';

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // $output contains the output string
        $output = curl_exec($ch);

        // Close curl resource to free up system resources
        curl_close($ch);
        return json_decode($output);
    }

    protected function GETcURL($url)
    {
        // Create curl resource
        $ch = curl_init($url);

        // Request headers
        $headers = array();
        $headers[] = 'Content-Type: application/json';

        // Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // $output contains the output string
        $output = curl_exec($ch);

        // Close curl resource to free up system resources
        curl_close($ch);
        return json_decode($output);
    }

    public function credit(Request $request)
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

        session()->put('payment_id', $data->id);

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
        } else {
            $business_name = "my_business";
        }
        $customer = DB::table('users')->where(['id' => $data['customer_id']])->first();

        try {
            $token = $this->getToken();
            $order = $this->createOrder($token, $data, $business_name);
            $paymentToken = $this->getPaymentToken($order, $token, $data, $customer);
        } catch (\Exception $exception) {
            return response()->json($this->response_formatter(DEFAULT_404), 200);
        }
        return Redirect::away('https://portal.weaccept.co/api/acceptance/iframes/' . $this->config_values->iframe_id . '?payment_token=' . $paymentToken);
    }

    public function getToken()
    {
        $response = $this->cURL(
            'https://accept.paymobsolutions.com/api/auth/tokens',
            ['api_key' => $this->config_values->api_key]
        );

        return $response->token;
    }

    public function createOrder($token, $payment_data, $business_name)
    {
        $items[] = [
            'name' => $business_name,
            'amount_cents' => round($payment_data->payment_amount, 2) * 100,
            'description' => 'payment ID :' . $payment_data->id,
            'quantity' => 1
        ];

        $data = [
            "auth_token" => $token,
            "delivery_needed" => "false",
            "amount_cents" => round($payment_data->payment_amount, 2) * 100,
            "currency" => $payment_data->currency_code,
            "items" => $items,

        ];
        $response = $this->cURL(
            'https://accept.paymob.com/api/ecommerce/orders',
            $data
        );

        return $response;
    }

    public function getPaymentToken($order, $token, $payment_data, $customer)
    {
        $value = $payment_data->payment_amount;
        $billingData = [
            "apartment" => "N/A",
            "email" => $customer->email,
            "floor" => "N/A",
            "first_name" => $customer->first_name,
            "street" => "N/A",
            "building" => "N/A",
            "phone_number" => $customer->phone ?? "N/A",
            "shipping_method" => "PKG",
            "postal_code" => "N/A",
            "city" => "N/A",
            "country" => "N/A",
            "last_name" => $customer->last_name,
            "state" => "N/A",
        ];

        $data = [
            "auth_token" => $token,
            "amount_cents" => round($value, 2) * 100,
            "expiration" => 3600,
            "order_id" => $order->id,
            "billing_data" => $billingData,
            "currency" => $payment_data->currency_code,
            "integration_id" => $this->config_values->integration_id
        ];

        $response = $this->cURL(
            'https://accept.paymob.com/api/acceptance/payment_keys',
            $data
        );

        return $response->token;
    }

    public function callback(Request $request)
    {
        $data = $request->all();
        ksort($data);
        $hmac = $data['hmac'];
        $array = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order',
            'owner',
            'pending',
            'source_data_pan',
            'source_data_sub_type',
            'source_data_type',
            'success',
        ];
        $connectedString = '';
        foreach ($data as $key => $element) {
            if (in_array($key, $array)) {
                $connectedString .= $element;
            }
        }
        $secret = $this->config_values->hmac;
        $hased = hash_hmac('sha512', $connectedString, $secret);

        if ($hased == $hmac) {
            $data = $this->payment::where(['id' => session('payment_id')])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'paymob_accept',
                    'transaction_id' => session('payment_id'),
                    'payment_id' => session('payment_id'),
                ]);
                $this->payment::where(['id' => session('payment_id')])->update([
                    'payment_method' => 'paymob_accept',
                    'is_paid' => 1,
                    'transaction_id' => session('payment_id'),
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
