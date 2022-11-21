<?php

namespace Mdalimrun\CombinedPaymentLibrary\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Mdalimrun\CombinedPaymentLibrary\Models\Payment;
use Ramsey\Uuid\Nonstandard\Uuid;

class PaymentController extends Controller
{

    private Payment $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function create_payment_data(Request $request)
    {
        $payment = $this->payment;
        $payment->id = Uuid::uuid4();
        $payment->unit_id = $request['unit_id'];
        $payment->unit_name = $request['unit_name'];
        $payment->customer_id = $request['customer_id'];

        if ($request['unit_placed_first']) {
            $unit = DB::table($request['unit_name'])->where('id', $request['unit_id'])->first();
            $payment->payment_amount = $unit[$request['payment_of']];
        }

    }
}
