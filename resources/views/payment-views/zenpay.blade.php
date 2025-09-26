@extends('payment-views.layouts.master')

@push('script')
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Auto-submit form to create ZenPay checkout session
        document.getElementById("zenpay-form").submit();
    });
</script>
@endpush

@section('content')

    @if(isset($config))
        <div class="text-center"> 
            <h1>Please do not refresh this page...</h1>
            <p>Redirecting to ZenPay payment gateway...</p>
        </div>

        <div class="col-md-6 mb-4" style="cursor: pointer">
            <div class="card">
                <div class="card-body" style="height: 70px">
                    <form id="zenpay-form" method="post" action="{{ route('zenpay.make_payment') }}">
                        @csrf
                        <input type="hidden" name="payment_id" value="{{ $payment_data->id }}">
                    </form>
                </div>
            </div>
        </div>
    @endif

@endsection