<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Flutterwave\Flutterwave;
use Flutterwave\Util\Currency;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class FlutterwaveController extends Controller
{
    use Processor;

    private $config_values;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('flutterwave', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        if ($config) {
            $config = array(
                'publicKey' => env('FLW_PUBLIC_KEY', $this->config_values->public_key),
                'secretKey' => env('FLW_SECRET_KEY', $this->config_values->secret_key),
                'encryptionKey' => env('FLW_ENCRYPTION_KEY', $this->config_values->encryptionKey),
            );
            Config::set('flutterwave', $config);
        }

        $this->payment = $payment;
    }

    public function initialize(Request $request): JsonResponse|Redirector|RedirectResponse|Application
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

        $data = [
            "amount" => 2000,
            "currency" => Currency::NGN,
            "tx_ref" => "TEST-" . uniqid() . time(),
            "redirectUrl" => "https://www.example.com",
            "additionalData" => [
                "subaccounts" => [],
                "meta" => [
                    "unique_id" => uniqid() . uniqid()
                ],
                "preauthorize" => false,
                "payment_plan" => null,
                "card_details" => [
                    "card_number" => "5531886652142950",
                    "cvv" => "564",
                    "expiry_month" => "09",
                    "expiry_year" => "32"
                ]
            ],
        ];

        $cardpayment = Flutterwave::create("card");
        $customerObj = $cardpayment->customer->create([
            "full_name" => $customer['first_name'] . ' ' . $customer['last_name'],
            "email" => $customer['email'],
            "phone" => $customer['phone'],
        ]);
        $data['customer'] = $customerObj;
        $payload = $cardpayment->payload->create($data);
        $result = $cardpayment->initiate($payload);

        dd($result);

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ?? url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        $payment_data = [
            'payment_options' => 'card,banktransfer',
            'amount' => $data->payment_amount,
            'email' => $customer['email'],
            'tx_ref' => $reference,
            'currency' => $data->currency_code,
            'redirect_url' => route('flutterwave.callback', ['payment_id' => $data->id]),
            'customer' => [
                'email' => $customer['email'],
                "phone_number" => $customer['phone'],
                "name" => $customer['first_name'] . ' ' . $customer['last_name'],
            ],

            "customizations" => [
                "title" => $business_name ?? null,
                "description" => '',
            ]
        ];

        $payment = Flutterwave::initializePayment($payment_data);

        if ($payment['status'] !== 'success') {
            if ($data->callback != null) {
                return redirect($data->callback . '?payment_status=fail');
            } else {
                return response()->json($this->response_formatter(DEFAULT_204), 200);
            }
        }

        return redirect($payment['data']['link']);
    }

    public function callback(Request $request)
    {
        $transaction_reference = $request['transaction_reference'];
        $status = $request['status'];

        //If payment is successful
        if ($status == 'successful') {
            $request['payment_method'] = 'flutterwave';
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'flutterwave',
                    'transaction_id' => $transaction_reference,
                    'payment_id' => $request->input('payment_id'),
                ]);

                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'flutterwave',
                    'is_paid' => 1,
                    'transaction_id' => $transaction_reference,
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
