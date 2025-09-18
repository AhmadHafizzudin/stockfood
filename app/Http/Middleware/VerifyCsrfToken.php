<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/restaurant-panel/store-token',
        '/pay-via-ajax',
        '/success',
        '/cancel',
        '/fail',
        '/ipn',
        '/payment-razor/*',
        '/paytm-response',
        '/liqpay-callback',
        '/mercadopago/make-payment',
        '/flutterwave-pay',
        '/admin/message/store*',
        '/restaurant-panel/dashboard/order-stats',

        // ✅ ZenPay routes
        'payment/zenpay/pay',
        'payment/zenpay/success',
        'payment/zenpay/failed',
        'api/v1/zenpay/*',
        'zenpay/callback',
    ];
}
