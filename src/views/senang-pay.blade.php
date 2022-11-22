@extends('payments.layouts.payment-client-master')

@push('script')

@endpush

@section('content')

    <center><h1>Please do not refresh this page...</h1></center>

    <div class="col-md-6 mb-4" style="cursor: pointer">
        <div class="card">
            <div class="card-body" style="height: 70px">
                @php($secretkey = $config->secret_key)
                @php($object = new \stdClass())
                @php($object->merchantId = $config->merchant_id)
                @php($object->amount = $data->payment_amount)
                @php($object->name = $customer->first_name??'')
                @php($object->email = $customer->email ??'')
                @php($object->phone = $customer->phone ??'')
                @php($object->hashed_string = md5($secretkey . urldecode($data->payment_amount) ))

                <form id="form" method="post"
                      action="https://{{$config->mode=='live'?'app.senangpay.my':'sandbox.senangpay.my'}}/payment/{{$config->merchant_id}}">
                    <input type="hidden" name="amount" value="{{$object->amount}}">
                    <input type="hidden" name="name" value="{{$object->name}}">
                    <input type="hidden" name="email" value="{{$object->email}}">
                    <input type="hidden" name="phone" value="{{$object->phone}}">
                    <input type="hidden" name="hash" value="{{$object->hashed_string}}">
                </form>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById("form").submit();
        });
    </script>
@endsection
