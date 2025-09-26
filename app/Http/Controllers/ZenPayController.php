<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\Processor;
use Illuminate\Http\Request;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;

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

    public function index(Request $request): View|Factory|JsonResponse|Application
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

        $payer = json_decode($payment_data['payer_information']);
        $config = $this->config_values;
        session()->put('payment_id', $payment_data->id);
        
        return view('payment-views.zenpay', compact('payment_data', 'payer', 'config'));
    }

    public function make_payment(Request $request)
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id'], 'is_paid' => 0])->first();
        if (!$payment_data) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $payer = json_decode($payment_data['payer_information']);
        $config = $this->config_values;

        // Prepare request data for ZenPay API
        $requestData = [
            'biller_code' => $config->merchant_id ?? $config->biller_code ?? '',
            'order_id' => (string)$payment_data->attribute_id,
            'email' => $payer->email ?? '',
            'amount' => number_format($payment_data->payment_amount, 2, '.', ''),
            'callback_url' => route('zenpay.callback'),
            'return_url' => route('zenpay.success'),
            'decline_url' => null,
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
            Log::info('ZenPay API Call Response: ' . $response);
            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data']['url'])) {
                    // Redirect to ZenPay hosted checkout page
                    Log::info('ZenPay Redirect: ' . $responseData['data']['url']);
                    return redirect($responseData['data']['url']);
                }
            }

            // If API call fails, show error
            $errorMessage = $response->json() ?? 'Payment session creation failed';
            Log::error('ZenPay API Call Error: ' . $errorMessage);
            return redirect()->route('payment-fail')->with('error', $errorMessage);

        } catch (\Exception $e) {
            Log::error('ZenPay API Call Error: ' . $e->getMessage());
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


    public function callback(Request $request)
    {
        Log::info('ZenPay Callback Received:', $request->all());
        
        // Verify callback signature
        if (!$this->verifyCallbackSignature($request)) {
            Log::error('ZenPay Callback: Invalid signature');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }
        
        $status = $request->input('status');
        $statusCode = $request->input('status_code');
        $orderId = $request->input('order_id');
        $payrefId = $request->input('payref_id');
        
        // Check if payment is successful
        $isSuccessful = ($status === 'SUCCESSFUL' && $statusCode === '00') || $statusCode === '1C';
        
        if ($isSuccessful) {
            $paymentId = session()->get('payment_id');
            if ($paymentId) {
                $this->payment::where(['id' => $paymentId])->update([
                    'payment_method' => 'zenpay',
                    'is_paid' => 1,
                    'transaction_id' => $payrefId,
                ]);

                $data = $this->payment::where(['id' => $paymentId])->first();

                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
                
                Log::info("ZenPay Callback: Payment {$paymentId} marked as paid", ['payment_id' => $paymentId]);
                return $this->payment_response($data, 'success');
            }
        } else {
            Log::warning("ZenPay Callback: Payment failed for order {$orderId}. Status: {$status}, Code: {$statusCode}");
        }
        
        return redirect()->route('payment-fail');
    }

    public function success(Request $request)
    {
        $paymentId = session()->get('payment_id');
        
        if ($paymentId) {
            $data = $this->payment::where(['id' => $paymentId])->first();
            if ($data && $data->is_paid) {
                if (isset($data) && function_exists($data->success_hook)) {
                    call_user_func($data->success_hook, $data);
                }
                return $this->payment_response($data, 'success');
            }
        }
        
        return redirect()->route('payment-fail')->with('error', 'Payment verification failed');
    }

    public function failed(Request $request)
    {
        $paymentId = session()->get('payment_id');
        
        if ($paymentId) {
            $payment_data = $this->payment::where(['id' => $paymentId])->first();

            if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
                call_user_func($payment_data->failure_hook, $payment_data);
            }

            return $this->payment_response($payment_data, 'fail');
        }
        
        return redirect()->route('payment-fail')->with('error', 'Payment failed');
    }

    /**
     * Verify ZenPay callback signature
     */
    private function verifyCallbackSignature(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        
        if (!$signature) {
            Log::error('ZenPay Callback: Missing X-Signature header');
            return false;
        }
        
        // Get the secret key from config
        $config = $this->config_values ?? null;
        if (!$config || !isset($config->secret_key)) {
            Log::error('ZenPay Callback: Missing secret key configuration');
            return false;
        }
        
        // Get all callback parameters
        $callbackData = $request->all();
        
        // Generate signature using the same method as in the API call
        $expectedSignature = generateZenpaySignature($callbackData, $config->secret_key);
        
        // Compare signatures
        $isValid = hash_equals($expectedSignature, $signature);
        
        if (!$isValid) {
            Log::error('ZenPay Callback: Signature mismatch', [
                'expected' => $expectedSignature,
                'received' => $signature,
                'data' => $callbackData
            ]);
        }
        
        return $isValid;
    }

}
