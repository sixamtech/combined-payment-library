@extends('layouts.payment-client-master')

@push('script')
    {{--stripe--}}
    <script src="https://polyfill.io/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
    <script src="https://js.stripe.com/v3/"></script>
    {{--stripe--}}
@endpush

@section('content')
    <center><h1>Please do not refresh this page...</h1></center>

    <script type="text/javascript">
        // Create an instance of the Stripe object with your publishable API key
        var stripe = Stripe('{{$config->live_values->published_key}}');
        document.addEventListener("DOMContentLoaded", function () {
            fetch("{{ route('stripe.token',['payment_id'=>$data->id]) }}", {
                method: "GET",
            }).then(function (response) {
                console.log(response)
                return response.text();
            }).then(function (session) {
                console.log(session)
                return stripe.redirectToCheckout({sessionId: JSON.parse(session).id});
            }).then(function (result) {
                if (result.error) {
                    alert(result.error.message);
                }
            }).catch(function (error) {
                console.error("error:", error);
            });
        });

    </script>
@endsection
