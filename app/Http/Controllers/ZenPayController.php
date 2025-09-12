<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ZenPayController extends Controller
{
    /**
     * Called by Flutter (or your backend) to create checkout session.
     */
    public function createCheckoutSession(Request $request)
    {
        // ✅ Validate incoming request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'email'  => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orderId = 'ORDER' . time();

        // ✅ Prepare request data
        $requestData = [
            "amount"       => number_format($request->input('amount'), 2, '.', ''),
            "biller_code"  => config('services.zenpay.biller_code'),
            "callback_url" => route('api.zenpay.webhook'),
            "currency"     => "MYR",
            "decline_url"  => route('web.zenpay.failed'),
            "email"        => $request->input('email'),
            "order_id"     => $orderId,
            "return_url"   => route('web.zenpay.success'),
            "timestamp"    => now()->setTimezone('UTC')->toIso8601String(),
        ];

        // ✅ Generate HMAC-SHA256 signature according to docs
        ksort($requestData); // Sort alphabetically by key
        $queryString = http_build_query($requestData, '', '&', PHP_QUERY_RFC3986);
        $secretKey = config('services.zenpay.secret_key');
        $signature = hash_hmac('sha256', $queryString, $secretKey);

        Log::info('ZenPay Query String', ['query' => $queryString]);
        Log::info('ZenPay Signature', ['signature' => $signature]);

        // ✅ Send to ZenPay
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature'  => $signature,
        ])->post(config('services.zenpay.endpoint') . '/v1/checkout-sessions', $requestData);

        Log::info('ZenPay Request Sent', $requestData);
        Log::info('ZenPay Response', $response->json());

        if ($response->successful()) {
            $data = $response->json();

            // ✅ Optional: Store payment in DB
            Payment::create([
                'order_id'     => $orderId,
                'email'        => $request->input('email'),
                'amount'       => $request->input('amount'),
                'status'       => 'pending',
                'gateway'      => 'zenpay',
                'raw_response' => $data,
            ]);

            return response()->json($data);
        }

        return response()->json([
            'error' => $response->json(),
            'message' => 'Failed to create ZenPay checkout session'
        ], $response->status());
    }


    /**
     * Webhook endpoint - ZenPay calls this to notify payment result.
     */
    public function webhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $incomingSignature = $request->header('X-Signature', '');

        // ✅ Verify signature
        $computed = hash_hmac('sha256', $rawPayload, config('services.zenpay.secret_key'));

        if (!hash_equals($computed, $incomingSignature)) {
            Log::warning('ZenPay webhook signature mismatch', [
                'incoming' => $incomingSignature,
                'computed' => $computed,
            ]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        Log::info('ZenPay Webhook Payload', $payload);

        $orderId = $payload['order_id'] ?? null;
        $status  = $payload['status'] ?? null;
        $payref  = $payload['payref_id'] ?? null;

        if ($orderId) {
            $payment = Payment::where('order_id', $orderId)->first();

            if ($payment) {
                $payment->update([
                    'status'       => $status ?? $payment->status,
                    'payref_id'    => $payref ?? $payment->payref_id,
                    'raw_response' => $payload,
                ]);
            } else {
                Log::warning('ZenPay webhook for unknown order', ['order_id' => $orderId]);
            }
        } else {
            Log::warning('ZenPay webhook without order_id');
        }

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Success page for browser return_url.
     */
    public function success(Request $request)
    {
        $status = $request->query('status');
        $payref = $request->query('payref_id');
        return view('payment-views.zen-pay-success', compact('status', 'payref'));
    }

    /**
     * Failed page for browser decline_url.
     */
    public function failed(Request $request)
    {
        $status = $request->query('status');
        $payref = $request->query('payref_id');
        return view('payment-views.zen-pay-failed', compact('status', 'payref'));
    }

    
    public function startPayment(Request $request)
    {
        $paymentId = $request->query('payment_id');

        // Here you could load the payment record from DB
        // Example: $payment = Payment::findOrFail($paymentId);

        // For now, just call createCheckoutSession internally
        // You can reuse the logic or redirect to a blade

        $requestData = [
            "amount"       => "150.50",
            "biller_code"  => config('services.zenpay.biller_code'),
            "callback_url" => route('api.zenpay.webhook'),
            "currency"     => "MYR",
            "decline_url"  => route('web.zenpay.failed'),
            "email"        => "customer@example.com",
            "order_id"     => "ORDER" . time(),
            "return_url"   => route('web.zenpay.success'),
            "timestamp"    => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];

        ksort($requestData);
        $queryString = http_build_query($requestData, '', '&', PHP_QUERY_RFC1738);
        $signature = hash_hmac('sha256', $queryString, config('services.zenpay.secret_key'));

        $response = \Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature'  => $signature,
        ])->post(config('services.zenpay.endpoint') . '/v1/checkout-sessions', $requestData);

        if ($response->successful()) {
            $session = $response->json();
            // redirect user to ZenPay checkout page
            return redirect()->away($session['data']['url']);
        }

        return response()->json(['error' => $response->json()], $response->status());
    }

    public function testSignature()
    {
        $requestData = [
            "amount"       => "150.50",
            "biller_code"  => config('services.zenpay.biller_code'),
            "callback_url" => route('api.zenpay.webhook'),
            "currency"     => "MYR",
            "decline_url"  => route('web.zenpay.failed'),
            "email"        => "customer@example.com",
            "order_id"     => "ORDER" . time(),
            "return_url"   => route('web.zenpay.success'),
            "timestamp"    => now()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];

        ksort($requestData);
        $queryString = http_build_query($requestData, '', '&', PHP_QUERY_RFC1738);
        $signature = hash_hmac('sha256', $queryString, config('services.zenpay.secret_key'));

        return response()->json([
            'query_string' => $queryString,
            'signature'    => $signature,
        ]);
    }

}
