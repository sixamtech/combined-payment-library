<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
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
use Razorpay\Api\Api;

class RazorPayController extends Controller
{
    use Processor;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('razor_pay', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $razor = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $razor = json_decode($config->test_values);
        }

        if ($razor) {
            $config = array(
                'api_key' => $razor->api_key,
                'api_secret' => $razor->api_secret
            );
            Config::set('razor_config', $config);
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

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ??  url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        return view('payments.razor-pay', compact('data', 'customer','business_logo','business_name'));
    }

    public function payment(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $input = $request->all();
        $api = new Api(config('razor_config.api_key'), config('razor_config.api_secret'));
        $payment = $api->payment->fetch($input['razorpay_payment_id']);

        if (count($input) && !empty($input['razorpay_payment_id'])) {
            $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount' => $payment['amount']));
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'razor_pay',
                    'transaction_id' => $input['razorpay_payment_id'],
                    'payment_id' => $request->input('payment_id'),
                ]);

                $this->payment::where(['id' => $request['payment_id']])->update([
                    'payment_method' => 'razor_pay',
                    'is_paid' => 1,
                    'transaction_id' => $input['razorpay_payment_id'],
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
