<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Traits\Processor;
use Illuminate\Http\Request;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;
use App\CentralLogics\Helpers;

class ZenPayController extends Controller
{
    use Processor;

    private $config_values;
    private PaymentRequest $payment;
    private $user;

    public function __construct(PaymentRequest $payment, User $user)
    {
        $config = $this->payment_config('zenpay', 'payment_config');

        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }

        $this->payment = $payment;
        $this->user = $user;
    }

    public function index(Request $request): View|Factory|JsonResponse|Application|RedirectResponse|Redirector
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json(
                $this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)),
                400
            );
        }

        $payment_data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 0])->first();
        if (!$payment_data) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        return $this->createHostedPayment($payment_data);
    }

    /**
     * Handle form submission from payment-view.blade.php
     */
    public function createHostedPayment(Request $request = null, $payment_data = null)
    {
        // If called from payment view form
        if ($request && $request->has('order_id')) {
            $order = Order::find($request->input('order_id'));
            if (!$order) {
                return redirect()->route('payment-fail')->with('error', 'Order not found');
            }
            
            $user = User::find($order->user_id);
            $config = Helpers::get_business_settings('zenpay');
            
            return $this->processHostedPayment($order, $user, $config);
        }
        
        // If called from payment gateway (original method)
        if ($payment_data) {
            return $this->processHostedPaymentFromGateway($payment_data);
        }
        
        return redirect()->route('payment-fail')->with('error', 'Invalid payment request');
    }

    /**
     * Process hosted payment from order form
     */
    private function processHostedPayment($order, $user, $config)
    {
        // Store order info in session
        session()->put('order_id', $order->id);
        
        // Prepare request data for ZenPay API
        $requestData = [
            'biller_code' => $config['merchant_id'] ?? '',
            'order_id' => (string)$order->id,
            'email' => $user->email ?? '',
            'amount' => number_format($order->order_amount - $order->partially_paid_amount, 2, '.', ''),
            'callback_url' => route('zenpay-callback'),
            'return_url' => route('zenpay-success'),
            'decline_url' => route('zenpay-failed'),
            'currency' => 'MYR',
            'timestamp' => now()->toISOString()
        ];

        return $this->makeZenPayApiCall($requestData, $config['secret_key']);
    }

    /**
     * Process hosted payment from payment gateway (original flow)
     */
    private function processHostedPaymentFromGateway($payment_data)
    {
        $payer = json_decode($payment_data['payer_information']);
        $config = $this->config_values;
        session()->put('payment_id', $payment_data->id);

        // Prepare request data for ZenPay API
        $requestData = [
            'biller_code' => $config->merchant_id ?? $config->biller_code ?? '',
            'order_id' => (string)$payment_data->attribute_id,
            'email' => $payer->email ?? '',
            'amount' => number_format($payment_data->payment_amount, 2, '.', ''),
            'callback_url' => route('zenpay-callback'),
            'return_url' => route('zenpay-success'),
            'decline_url' => route('zenpay-failed'),
            'currency' => 'MYR',
            'timestamp' => now()->toISOString()
        ];

        return $this->makeZenPayApiCall($requestData, $config->secret_key);
    }

    /**
     * Make ZenPay API call to create checkout session
     */
    private function makeZenPayApiCall($requestData, $secretKey)
    {
        // Generate signature using helper function
        $signature = generateZenpaySignature($requestData, $secretKey);

        // Determine API URL based on environment
        $apiUrl = $this->getApiUrl() . '/v1/checkout-sessions';

        try {
            // Make API call to create checkout session
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature' => $signature
            ])->post($apiUrl, $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data']['url'])) {
                    // Redirect to ZenPay hosted checkout page
                    return redirect($responseData['data']['url']);
                }
            }

            // If API call fails, show error
            $errorMessage = $response->json()['message'] ?? 'Payment session creation failed';
            return redirect()->route('payment-fail')->with('error', $errorMessage);

        } catch (\Exception $e) {
            return redirect()->route('payment-fail')->with('error', 'Payment gateway error: ' . $e->getMessage());
        }
    }


    /**
     * Get ZenPay API URL based on environment
     */
    private function getApiUrl(): string
    {
        return env('APP_MODE') === 'live' 
            ? 'https://api.thezenpay.com' 
            : 'https://api-staging.thezenpay.com';
    }

    /**
     * Handle callback from ZenPay (webhook)
     */
    public function callback(Request $request)
    {
        $paymentId = session()->get('payment_id');
        $orderId = session()->get('order_id');
        
        // Verify callback signature if needed
        // $this->verifyCallbackSignature($request);
        
        $status = $request->input('status'); // success/failed based on ZenPay documentation
        $transactionId = $request->input('payref_id');
        
        if ($status === 'success') {
            // Handle order-based payment (from payment-view)
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'payment_method' => 'zenpay',
                        'transaction_reference' => $transactionId,
                    ]);
                }
                return response()->json(['status' => 'success'], 200);
            }
            
            // Handle payment gateway flow
            if ($paymentId) {
                $this->payment::where(['id' => $paymentId])->update([
                    'payment_method' => 'zenpay',
                    'is_paid' => 1,
                    'transaction_id' => $transactionId,
                ]);

                $data = $this->payment::where(['id' => $paymentId])->first();

                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
                return response()->json(['status' => 'success'], 200);
            }
        }

        return response()->json(['status' => 'failed'], 200);
    }

    /**
     * Handle success redirect from ZenPay
     */
    public function success(Request $request)
    {
        $orderId = session()->get('order_id');
        $paymentId = session()->get('payment_id');
        
        // Handle order-based flow
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order && $order->payment_status === 'paid') {
                session()->forget('order_id');
                return redirect()->route('payment-success')->with('success', 'Payment completed successfully');
            }
        }
        
        // Handle payment gateway flow
        if ($paymentId) {
            $data = $this->payment::where(['id' => $paymentId])->first();
            if ($data && $data->is_paid) {
                return $this->payment_response($data, 'success');
            }
        }
        
        return redirect()->route('payment-fail')->with('error', 'Payment verification failed');
    }

    /**
     * Handle failed redirect from ZenPay
     */
    public function failed(Request $request)
    {
        $orderId = session()->get('order_id');
        $paymentId = session()->get('payment_id');
        
        // Handle order-based flow
        if ($orderId) {
            session()->forget('order_id');
            return redirect()->route('payment-fail')->with('error', 'Payment was declined or failed');
        }
        
        // Handle payment gateway flow
        if ($paymentId) {
            $payment_data = $this->payment::where(['id' => $paymentId])->first();

            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            return $this->payment_response($payment_data, 'fail');
        }
        
        return redirect()->route('payment-fail')->with('error', 'Payment failed');
    }

    public function healthCheck()
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.zenpay.api_key')
        ])->get(config('services.zenpay.base_url') . '/v1/health');

        return response()->json($response->json(), $response->status());
    }

}
