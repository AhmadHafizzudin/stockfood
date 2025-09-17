<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ZenPayController extends Controller
{
    /**
     * Create checkout session (called by Flutter or your Blade form).
     */
    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'email'  => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $orderId = 'ORDER' . time();

        $requestData = [
            "biller_code"  => config('services.zenpay.biller_code'),
            "order_id"     => $orderId,
            "email"        => $request->input('email'),
            "amount"       => number_format($request->input('amount'), 2, '.', ''),
            "callback_url" => config('services.zenpay.callback_url'),
            "return_url"   => config('services.zenpay.return_url'),
            "decline_url"  => config('services.zenpay.decline_url'),
            "currency"     => "MYR",
            "timestamp"    => now()->setTimezone('UTC')->toIso8601String(),
        ];

        // ✅ Signature must be HMAC of raw JSON body
        $jsonBody  = json_encode($requestData, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $jsonBody, config('services.zenpay.secret_key'));

        Log::info('ZenPay Create Request', $requestData);
        Log::info('ZenPay Signature', ['sig' => $signature]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature'  => $signature,
        ])->post(config('services.zenpay.base_url').'/checkout-sessions', $requestData);

        Log::info('ZenPay Response', $response->json());

        if ($response->successful()) {
            $data = $response->json();

            // Save to DB
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
            'error'   => $response->json(),
            'message' => 'Failed to create ZenPay checkout session'
        ], $response->status());
    }

    /**
     * Webhook endpoint (ZenPay calls this).
     */
    public function webhook(Request $request)
    {
        $rawPayload = $request->getContent();
        $incomingSignature = $request->header('X-Signature', '');

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
        }

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Success return page.
     */
    public function success(Request $request)
    {
        $status = $request->query('status');
        $payref = $request->query('payref_id');
        return view('payment-views.zen-pay-success', compact('status', 'payref'));
    }

    /**
     * Failed return page.
     */
    public function failed(Request $request)
    {
        $status = $request->query('status');
        $payref = $request->query('payref_id');
        return view('payment-views.zen-pay-failed', compact('status', 'payref'));
    }
}
