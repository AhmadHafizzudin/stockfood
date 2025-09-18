<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\Processor;
use Illuminate\Http\Request;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
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
        $config = $this->payment_config('zen_pay', 'payment_config');

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

        // Prepare variables for Blade view
        $secretKey = $config->secret_key ?? '';
        $amount = number_format($payment_data->payment_amount, 2);
        $attribute = $payment_data->attribute;
        $attributeId = $payment_data->attribute_id;

        $hashed_string = md5($secretKey . urldecode($attribute . $amount . $attributeId));

        return view('payment-views.zen-pay', [
            'config' => $config,
            'payment_data' => $payment_data,
            'payer' => $payer,
            'hashed_string' => $hashed_string,
            'amount' => $amount
        ]);
    }

    public function return_zen_pay(Request $request): JsonResponse|Redirector|RedirectResponse|Application
    {
        $paymentId = session()->get('payment_id');

        if ($request['status_id'] == 1) {
            $this->payment::where(['id' => $paymentId])->update([
                'payment_method' => 'zen_pay',
                'is_paid' => 1,
                'transaction_id' => $request['transaction_id'],
            ]);

            $data = $this->payment::where(['id' => $paymentId])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data, 'success');
        }

        $payment_data = $this->payment::where(['id' => $paymentId])->first();

        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }

        return $this->payment_response($payment_data, 'fail');
    }

    public function healthCheck()
    {
        $response = Http::withHeaders([
            'x-api-key' => config('services.zenpay.api_key')
        ])->get(config('services.zenpay.base_url') . '/v1/health');

        return response()->json($response->json(), $response->status());
    }

}
