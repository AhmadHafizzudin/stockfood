@extends('payment-views.layouts.master')

@section('content')
<div class="container text-center mt-5">
    <h2>Payment Successful 🎉</h2>
    <p>Status: {{ $status }}</p>
    <p>Payref ID: {{ $payref }}</p>
</div>
@endsection
