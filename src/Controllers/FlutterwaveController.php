<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use KingFlamez\Rave\Facades\Rave as Flutterwave;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
                'secretHash' => env('FLW_SECRET_HASH', $this->config_values->encryptionKey),
            );
            Config::set('flutterwave', $config);
        }

        $this->payment = $payment;
    }

    public function initialize(Request $request)
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

        $customer = DB::table('users')->where(['id' => $data['customer_id']])->first();
        $reference = \KingFlamez\Rave\Facades\Rave::generateReference();
        $payment_data = [
            'payment_options' => 'card,banktransfer',
            'amount' => $data->payment_amount,
            'email' => $customer->email,
            'tx_ref' => $reference,
            'currency' => $data->currency_code,
            'redirect_url' => route('flutterwave_callback', ['payment_id' => $data->id]),
            'customer' => [
                'email' => $customer->email,
                "phone_number" => $customer->phone,
                "name" => $customer->first_name . '' . $customer->last_name
            ],

            "customizations" => [
                "title" => $business_name,
                "description" => $data->id,
            ]
        ];

        $payment = Flutterwave::initializePayment($payment_data);

        if ($payment['status'] == 'success') {
            return redirect($payment['data']['link']);
        }
        return response()->json($this->response_formatter(DEFAULT_404), 200);
    }

    public function callback(Request $request)
    {
        if (in_array($request->status, ['successful', 'completed'])) {
            $transactionID = Flutterwave::getTransactionIDFromCallback();
            $data = Flutterwave::verifyTransaction($transactionID);
            $request['payment_method'] = 'flutterwave';
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'flutterwave',
                    'transaction_id' => $transactionID,
                    'payment_id' => $request->input('payment_id'),
                ]);
                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'flutterwave',
                    'is_paid' => 1,
                    'transaction_id' => $transactionID,
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
