<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Models\BusinessSetting;

use App\Library\Payer;
use App\Traits\Payment;
use App\Library\Receiver;
use App\Library\Payment as PaymentInfo;


class PaymentController extends Controller
{
    public function __construct(){
        if (is_dir('App\Traits') && trait_exists('App\Traits\Payment')) {
            $this->extendWithPaymentGatewayTrait();
        }
    }

    private function extendWithPaymentGatewayTrait()
    {
        $extendedControllerClass = $this->generateExtendedControllerClass();
        // eval($extendedControllerClass);
    }

    private function generateExtendedControllerClass()
    {
        $baseControllerClass = get_class($this);
        $traitClassName = 'App\Traits\Payment';

        $extendedControllerClass = "
            class ExtendedController extends $baseControllerClass {
                use $traitClassName;
            }
        ";

        return $extendedControllerClass;
    }
    public function payment(Request $request)
    {
        if ($request->has('callback')) {
            Order::where(['id' => $request->order_id])->update(['callback' => $request['callback']]);
        }

        session()->put('customer_id', $request['customer_id']);
        session()->put('payment_platform', $request['payment_platform']);
        session()->put('order_id', $request->order_id);

        // Debug: Log the request parameters
        \Log::info('Payment Request:', [
            'order_id' => $request->order_id,
            'customer_id' => $request['customer_id'],
            'payment_method' => $request->payment_method ?? 'not_set'
        ]);

        $order = Order::where(['id' => $request->order_id, 'user_id' => $request['customer_id']])->first();

        if(!$order){
            \Log::info('Order not found:', [
                'order_id' => $request->order_id,
                'customer_id' => $request['customer_id'],
                'available_orders' => Order::where('id', $request->order_id)->get(['id', 'user_id', 'is_guest'])
            ]);
            return response()->json(['errors' => ['code' => 'order-payment', 'message' => 'Data not found']], 403);
        }

        // Zenpay Flow

        // Zenpay Flow
        //guest user check
        if ($order->is_guest) {
            $address = json_decode($order['delivery_address'] , true);
            $customer = collect([
                'first_name' => $address['contact_person_name'],
                'last_name' => '',
                'phone' => $address['contact_person_number'],
                'email' => $address['contact_person_email'],
            ]);

        } else {
            $customer = User::find($request['customer_id']);
            $customer = collect([
                'first_name' => $customer['f_name'],
                'last_name' => $customer['l_name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
            ]);
        }



        if (session()->has('payment_method') == false) {
            session()->put('payment_method', 'ssl_commerz_payment');
        }

        $order_amount = $order->order_amount - $order->partially_paid_amount;

            if (!isset($customer)) {
                return response()->json(['errors' => ['message' => 'Customer not found']], 403);
            }

            if (!isset($order_amount)) {
                return response()->json(['errors' => ['message' => 'Amount not found']], 403);
            }

            if (!$request->has('payment_method')) {
                return response()->json(['errors' => ['message' => 'Payment not found']], 403);
            }

            $payer = new Payer($customer['first_name'].' '.$customer['last_name'], $customer['email'], $customer['phone'], '');

            $currency=BusinessSetting::where(['key'=>'currency'])->first()->value;
            $additional_data = [
                'business_name' => BusinessSetting::where(['key'=>'business_name'])->first()?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' .BusinessSetting::where(['key' => 'logo'])->first()?->value
            ];
            $payment_info = new PaymentInfo(
                success_hook: 'order_place',
                failure_hook: 'order_failed',
                currency_code: $currency,
                payment_method: $request->payment_method,
                payment_platform: $request['payment_platform'],
                payer_id: $request['customer_id'],
                receiver_id: '100',
                additional_data: $additional_data,
                payment_amount: $order_amount,
                external_redirect_link: $request->has('callback')?$request['callback']:session('callback'),
                attribute: 'order',
                attribute_id: $order->id
            );

            $receiver_info = new Receiver('receiver_name','example.png');

            $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

            return redirect($redirect_link);


        //for default payment gateway

        if (isset($customer) && isset($order)) {
            $data = [
                'name' => $customer['f_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
            ];
            session()->put('data', $data);
            return view('payment-view');
        }

        return response()->json(['errors' => ['code' => 'order-payment', 'message' => 'Data not found']], 403);

    }


    public function success()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if (isset($order) && $order->callback != null) {
            $redirect = $order->callback . (str_contains($order->callback, '?') ? '&' : '?') . 'id=' . $order->id . '&status=success';
            // clear session
            session()->forget('order_id');
            session()->forget('customer_id');
            return redirect($redirect);
        }
        // Fallback: send to home instead of JSON
        session()->forget('order_id');
        session()->forget('customer_id');
        return redirect(url('/'));
    }

    public function fail()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if ($order) {
            // Delete order on cancel/fail to avoid showing in lists and notifications
            try {
                // Best-effort cascading removal of related rows
                $order->details()?->delete();
                $order->orderTaxes()?->delete();
                $order->payments()?->delete();
                $order->offline_payments()?->delete();
            } catch (\Throwable $e) {
                \Log::warning('Order related delete failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
            try {
                $orderId = $order->id;
                $callback = $order->callback;
                $order->delete();
                // clear session
                session()->forget('order_id');
                session()->forget('customer_id');
                if ($callback) {
                    $redirect = $callback . (str_contains($callback, '?') ? '&' : '?') . 'id=' . $orderId . '&status=fail';
                    return redirect($redirect);
                }
            } catch (\Throwable $e) {
                \Log::warning('Order delete failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
        // Fallback: send to home instead of JSON
        session()->forget('order_id');
        session()->forget('customer_id');
        return redirect(url('/'));
    }
    public function cancel(Request $request)
    {
        $order = Order::where(['id' => session('order_id'), 'user_id'=>session('customer_id')])->first();
        if ($order) {
            try {
                $order->details()?->delete();
                $order->orderTaxes()?->delete();
                $order->payments()?->delete();
                $order->offline_payments()?->delete();
            } catch (\Throwable $e) {
                \Log::warning('Order related delete failed (cancel)', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
            try {
                $orderId = $order->id;
                $callback = $order->callback;
                $order->delete();
                session()->forget('order_id');
                session()->forget('customer_id');
                if ($callback) {
                    $redirect = $callback . (str_contains($callback, '?') ? '&' : '?') . 'id=' . $orderId . '&status=fail';
                    return redirect($redirect);
                }
            } catch (\Throwable $e) {
                \Log::warning('Order delete failed (cancel)', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
        session()->forget('order_id');
        session()->forget('customer_id');
        return redirect(url('/'));
    }

}
