<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;
use Unicodeveloper\Paystack\Facades\Paystack;

class PaystackController extends Controller
{
    use Processor;

    private Payment $payment;
    private $paystack;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('paystack', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $values = $config->live_values;
        } elseif (!is_null($config) && $config->mode == 'test') {
            $values = $config->test_values;
        }

        if ($values) {
            $config = array(
                'publicKey' => env('PAYSTACK_PUBLIC_KEY', $values['public_key']),
                'secretKey' => env('PAYSTACK_SECRET_KEY', $values['secret_key']),
                'paymentUrl' => env('PAYSTACK_PAYMENT_URL', $values['callback_url']),
                'merchantEmail' => env('MERCHANT_EMAIL', $values['merchant_email']),
            );
            Config::set('paystack', $config);
        }

        $this->payment = $payment;

        $this->paystack = Paystack::genTranxRef();
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
        $customer = DB::table('users')->where(['id' => $data['customer_id']])->first();

        $paystack = $this->paystack;
        $reference = $paystack::generate_transaction_Referance();

        return view('payments.paystack', compact('data', 'customer', 'reference'));
    }

    public function redirectToGateway(Request $request)
    {
        return Paystack::getAuthorizationUrl()->redirectNow();
    }

    public function handleGatewayCallback(Request $request)
    {
        $paymentDetails = Paystack::getPaymentData();
        $transaction_reference = $paymentDetails['data']['reference'];

        if ($paymentDetails['status'] == true) {
            $data = $this->payment::where(['id' => $paymentDetails['data']['orderID']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'paystack',
                    'transaction_id' => $transaction_reference,
                    'payment_id' => $request->input('payment_id'),
                ]);

                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'paystack',
                    'is_paid' => 1,
                    'transaction_id' => $paymentDetails['data']['orderID'],
                ]);
            }
            if ($data['callback'] != null) {
                return redirect($data['callback'] . '?payment_status=success');
            }
            return response()->json($this->response_formatter(DEFAULT_200), 200);
        }

        return response()->json($this->response_formatter(DEFAULT_204), 200);
    }
}
