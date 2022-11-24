<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class Paytabs
{
    use Processor;

    private $config_values;

    public function __construct()
    {
        $config = $this->payment_config('paypal', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }
    }

    function send_api_request($request_url, $data, $request_method = null)
    {
        $data['profile_id'] = $this->config_values->profile_id;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->config_values->base_url . '/' . $request_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => isset($request_method) ? $request_method : 'POST',
            CURLOPT_POSTFIELDS => json_encode($data, true),
            CURLOPT_HTTPHEADER => array(
                'authorization:' . $this->config_values->server_key,
                'Content-Type:application/json'
            ),
        ));

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        return $response;
    }

    function is_valid_redirect($post_values)
    {
        $serverKey = $this->config_values->server_key;
        $requestSignature = $post_values["signature"];
        unset($post_values["signature"]);
        $fields = array_filter($post_values);
        ksort($fields);
        $query = http_build_query($fields);
        $signature = hash_hmac('sha256', $query, $serverKey);
        if (hash_equals($signature, $requestSignature) === TRUE) {
            return true;
        } else {
            return false;
        }
    }
}

class PaytabsController extends Controller
{
    use Processor;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

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
        $customer = DB::table('users')->where(['id' => $data['customer_id']])->first();

        $plugin = new Paytabs();
        $request_url = 'payment/request';
        $data = [
            "tran_type" => "sale",
            "tran_class" => "ecom",
            "cart_id" => $data->id,
            "cart_currency" => $data->currency_code,
            "cart_amount" => round($data->payment_amount, 2),
            "cart_description" => "products",
            "paypage_lang" => "en",
            "callback" => route('paytabs.callback', ['payment_id' => $data->id]), // Nullable - Must be HTTPS, otherwise no post data from paytabs
            "return" => url('/') . "/paytabs/response", // Must be HTTPS, otherwise no post data from paytabs , must be relative to your site URL
            "customer_details" => [
                "name" => $customer->first_name,
                "email" => $customer->email,
                "phone" => $customer->phone ?? "000000",
                "street1" => "N/A",
                "city" => "N/A",
                "state" => "N/A",
                "country" => "N/A",
                "zip" => "00000"
            ],
            "shipping_details" => [
                "name" => "N/A",
                "email" => "N/A",
                "phone" => "N/A",
                "street1" => "N/A",
                "city" => "N/A",
                "state" => "N/A",
                "country" => "N/A",
                "zip" => "0000"
            ],
            "user_defined" => [
                "udf9" => "UDF9",
                "udf3" => "UDF3"
            ]
        ];
        $page = $plugin->send_api_request($request_url, $data);
        if (!isset($page['redirect_url'])) {
            return response()->json($this->response_formatter(DEFAULT_204), 200);
        }
        header('Location:' . $page['redirect_url']); /* Redirect browser */
        exit();
    }

    public function callback(Request $request)
    {
        $plugin = new Paytabs();
        $response_data = $_POST;
        $transRef = filter_input(INPUT_POST, 'tranRef');

        if (!$transRef) {
            return response()->json($this->response_formatter(DEFAULT_204), 200);
        }

        $is_valid = $plugin->is_valid_redirect($response_data);
        if (!$is_valid) {
            return response()->json($this->response_formatter(DEFAULT_204), 200);
        }

        $request_url = 'payment/query';
        $data = [
            "tran_ref" => $transRef
        ];
        $verify_result = $plugin->send_api_request($request_url, $data);
        $is_success = $verify_result['payment_result']['response_status'] === 'A';
        if ($is_success) {
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'paytabs',
                    'transaction_id' => $transRef,
                    'payment_id' => $request->input('payment_id'),
                ]);

                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'paytabs',
                    'is_paid' => 1,
                    'transaction_id' => $transRef,
                ]);
            }
            if ($data['callback'] != null) {
                return redirect($data['callback'] . '?payment_status=success');
            }
            return response()->json($this->response_formatter(DEFAULT_200), 200);
        }

        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }

    public function response(Request $request)
    {
        return response()->json($this->response_formatter(DEFAULT_200), 200);
    }
}
