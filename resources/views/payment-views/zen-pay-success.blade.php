@extends('payment-views.layouts.master')

@section('content')
<div class="container">
    <h3>Payment result</h3>
    <p>Status: {{ $status }}</p>
    <p>Reference: {{ $payref }}</p>
    <p><a href="{{ route('zenpay.pay') }}">Back</a></p>
</div>
@endsection
