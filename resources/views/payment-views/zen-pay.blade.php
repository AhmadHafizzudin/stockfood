@extends('payment-views.layouts.master')

@section('content')
<div class="container">
    <h2>Pay with ZenPay (Hosted)</h2>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('zenpay.initiate') }}">
        @csrf

        <input type="hidden" name="reference" value="{{ $reference }}">

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="{{ old('email') }}">
        </div>

        <div class="mb-3">
            <label class="form-label">Amount (MYR)</label>
            <input name="amount" type="number" step="0.01" class="form-control" required value="{{ old('amount', '10.00') }}">
        </div>

        <button class="btn btn-primary">Pay (Hosted)</button>
    </form>
</div>
@endsection

