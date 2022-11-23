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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Mdalimrun\CombinedPaymentLibrary\Traits\Processor;

class SenangPayController extends Controller
{
    use Processor;

    private $config_values;

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $config = $this->payment_config('senang_pay', 'payment_config');
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
        $customer = DB::table('users')->where(['id' => $data['customer_id']])->first();
        $config = $this->config_values;
        session()->put('payment_id', $data->id);
        return view('payments.senang-pay', compact('data', 'customer', 'config'));
    }

    public function return_senang_pay(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        if ($request['status_id'] == 1) {
            $data = $this->payment::where(['id' => $request['payment_id']])->first();
            if (isset($data) && function_exists($data->hook)) {
                call_user_func($data->hook, [
                    'payment_method' => 'senang_pay',
                    'transaction_id' => $request['transaction_id'],
                    'payment_id' => session('payment_id'),
                ]);

                $this->payment::where(['id' => session('payment_id')])->update([
                    'payment_method' => 'senang_pay',
                    'is_paid' => 1,
                    'transaction_id' => $request['transaction_id'],
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
