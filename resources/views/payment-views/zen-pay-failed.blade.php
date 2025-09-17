@extends('payment-views.layouts.master')

@section('content')
<div class="container">
    <h2>Payment Failed</h2>
    <p>Status: {{ $status }}</p>
    <p>PayRef ID: {{ $payref }}</p>
</div>
@endsection
