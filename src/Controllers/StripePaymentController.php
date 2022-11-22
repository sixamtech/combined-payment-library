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
use Stripe\Stripe;

class StripePaymentController extends Controller
{
    use Processor;

    private $config_values;
    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('stripe', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
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
        $config = $this->config_values;

        return view('payments.stripe', compact('data', 'config'));
    }

    public function payment_process_3d(Request $request): JsonResponse
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(DEFAULT_204), 200);
        }
        $payment_amount = $data['payment_amount'];

        Stripe::setApiKey($this->config_values->api_key);
        header('Content-Type: application/json');
        $currency_code = $data->currency_code;

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "";
            $business_logo = $business->business_logo ?? "";
        } else {
            $business_name = "";
            $business_logo = "";
        }

        $checkout_session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency_code ?? 'usd',
                    'unit_amount' => round($payment_amount, 2) * 100,
                    'product_data' => [
                        'name' => $business_name,
                        'images' => [$business_logo],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/') . '/payment/stripe/success?payment_id' . $data->id,
            'cancel_url' => url()->previous(),
        ]);
        return response()->json(['id' => $checkout_session->id]);
    }

    public function success(Request $request)
    {
        $tran_id = Str::random(6) . '-' . rand(1, 1000);;
        $request['payment_method'] = 'stripe';
        //  $response = place_booking_request($request['access_token'], $request, $tran_id);

        /*if ($response['flag'] == 'success') {
            if ($request->has('callback')) {
                return redirect($request['callback'] . '?payment_status=success');
            } else {
                return response()->json($this->response_formatter(DEFAULT_200), 200);
            }
        }*/
        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }
}
