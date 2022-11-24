<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class FlutterwaveV3Controller extends Controller
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

        //* Prepare our rave request
        $request = [
            'tx_ref' => time(),
            'amount' => $data->payment_amount,
            'currency' => 'NGN',
            'payment_options' => 'card',
            'redirect_url' => route('flutterwave-v3.callback'),
            'customer' => [
                'email' => $customer->email,
                'name' => $customer->first_name . ' ' . $customer->last_name
            ],
            'meta' => [
                'price' => $data->payment_amount
            ],
            'customizations' => [
                'title' => $business_name,
                'description' => $data->id
            ]
        ];

        //http request
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($request),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer FLWPUBK_TEST-1ddc55a878e84876c7191306367066d1-X',
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $res = json_decode($response);
        if ($res->status == 'success') {
            $link = $res->data->link;
            header('Location: ' . $link);
        }

        return 'We can not process your payment';
    }
}
