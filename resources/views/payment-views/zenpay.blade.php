@extends('payment-views.layouts.master')

@section('content')
<div class="container">
    <h2>Pay with ZenPay (Hosted)</h2>

    <form method="POST" action="{{ url('api/v1/zenpay/checkout') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Amount (MYR)</label>
            <input name="amount" type="number" step="0.01" class="form-control" required>
        </div>

        <button class="btn btn-primary">Pay (Hosted)</button>
    </form>
</div>
@endsection
