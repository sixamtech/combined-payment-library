<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment as PaymentTable;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;
use MercadoPago\SDK;
use MercadoPago\Payment;
use MercadoPago\Payer;

class MercadoPagoController extends Controller
{
    use Processor;

    private PaymentTable $payment;
    private $config;

    public function __construct(PaymentTable $payment)
    {
        $config = $this->payment_config('mercadopago', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = json_decode($config->test_values);
        }

        $this->config = $values;
        $this->payment = $payment;
    }


    public function index(Request $request)
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
        $config = $this->config;
        return view('payment-view-marcedo-pogo', compact('config', 'data'));
    }

    public function make_payment(Request $request)
    {
        SDK::setAccessToken($this->config->access_token);
        $payment = new Payment();
        $payment->transaction_amount = (float)$request['transactionAmount'];
        $payment->token = $request['token'];
        $payment->description = $request['description'];
        $payment->installments = (int)$request['installments'];
        $payment->payment_method_id = $request['paymentMethodId'];
        $payment->issuer_id = (int)$request['issuer'];

        $payer = new Payer();
        $payer->email = $request['payer']['email'];
        $payer->identification = array(
            "type" => $request['payer']['identification']['type'],
            "number" => $request['payer']['identification']['number']
        );
        $payment->payer = $payer;
        $payment->save();

        if ($payment->status == 'approved') {
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'mercadopago',
                    'transaction_id' => $payment->id,
                    'payment_id' => $request['payment_id'],
                ]);

                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'mercadopago',
                    'is_paid' => 1,
                    'transaction_id' => $payment->id,
                ]);
            }
            if ($data['callback'] != null) {
                return redirect($data['callback'] . '?payment_status=success');
            }
            return response()->json($this->response_formatter(DEFAULT_200), 200);
        }

        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }

    public function get_test_user(Request $request)
    {
        // curl -X POST \
        // -H "Content-Type: application/json" \
        // -H 'Authorization: Bearer PROD_ACCESS_TOKEN' \
        // "https://api.mercadopago.com/users/test_user" \
        // -d '{"site_id":"MLA"}'

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://api.mercadopago.com/users/test_user");
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config->access_token
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, '{"site_id":"MLA"}');
        $response = curl_exec($curl);
    }
}
