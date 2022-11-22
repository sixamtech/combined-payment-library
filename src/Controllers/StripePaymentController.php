<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class StripePaymentController extends Controller
{
    use Processor;

    private $store_id;
    private $store_password;
    private bool $host;
    private string $direct_api_url;
    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('sslcommerz', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }
        $this->store_id = $values->store_id;
        $this->store_password = $values->store_password;

        # REQUEST SEND TO SSLCOMMERZ
        if ($config->mode == 'live') {
            $this->direct_api_url = "https://securepay.sslcommerz.com/gwprocess/v4/api.php";
            $this->host = false;
        } else {
            $this->direct_api_url = "https://sandbox.sslcommerz.com/gwprocess/v4/api.php";
            $this->host = true;
        }

        $this->payment = $payment;
    }

    public function index(Request $request): View|Factory|JsonResponse|Application
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
        $config = $this->payment_config('stripe', 'payment_config');

        return view('stripe', compact('customer', 'data', 'config'));
    }


    public function payment_process_3d(Request $request): JsonResponse
    {
        $params = explode('&&', base64_decode($request['token']));

        foreach ($params as $param) {
            $data = explode('=', $param);
            if ($data[0] == 'access_token') {
                $access_token = $data[1];
            } elseif ($data[0] == 'callback') {
                $callback = $data[1];
            } elseif ($data[0] == 'zone_id') {
                $zone_id = $data[1];
            } elseif ($data[0] == 'service_schedule') {
                $service_schedule = $data[1];
            } elseif ($data[0] == 'service_address_id') {
                $service_address_id = $data[1];
            }
        }

        $booking_amount = cart_total($access_token);
        $config = business_config('stripe', 'payment_config');
        Stripe::setApiKey($config->live_values['api_key']);
        header('Content-Type: application/json');
        $currency_code = currency_code();

        $business_name = business_config('business_name', 'business_information');
        $business_logo = business_config('business_logo', 'business_information');

        $query_parameter = 'access_token=' . $access_token;
        $query_parameter .= isset($callback) ? '&&callback=' . $callback : '';
        $query_parameter .= '&&zone_id=' . $zone_id . '&&service_schedule=' . $service_schedule . '&&service_address_id=' . $service_address_id;

        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency_code ?? 'usd',
                    'unit_amount' => round($booking_amount, 2) * 100,
                    'product_data' => [
                        'name' => $business_name->live_values,
                        'images' => [asset('storage/app/public/business') . '/' . $business_logo->live_values],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/') . '/payment/stripe/success?' . $query_parameter,
            'cancel_url' => url()->previous(),
        ]);
        return response()->json(['id' => $checkout_session->id]);
    }

    public function success(Request $request)
    {
        $tran_id = Str::random(6) . '-' . rand(1, 1000);;
        $request['payment_method'] = 'stripe';
        $response = place_booking_request($request['access_token'], $request, $tran_id);

        if ($response['flag'] == 'success') {
            if ($request->has('callback')) {
                return redirect($request['callback'] . '?payment_status=success');
            } else {
                return response()->json($this->response_formatter(DEFAULT_200), 200);
            }
        }
        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }
}
