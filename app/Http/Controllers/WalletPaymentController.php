<?php

namespace App\Http\Controllers;

use App\CentralLogics\CustomerLogic;
use App\Models\Order;
use App\Models\BusinessSetting;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;

class WalletPaymentController extends Controller
{
    /**
     * make_payment Rave payment process
     * @return void
     */
    public function make_payment(Request $request)
    {
        if(BusinessSetting::where('key','wallet_status')->first()?->value != 1) return Toastr::error(translate('messages.customer_wallet_disable_warning'));
        $order = Order::with('customer')->where(['id' => $request->order_id, 'user_id'=>$request->user_id])->first();
        // Calculate unpaid amount considering any partial payments already made
        $partiallyPaid = (float)($order?->partially_paid_amount ?? 0);
        $unpaidAmount = (float)($order?->order_amount ?? 0) - $partiallyPaid;

        if($unpaidAmount <= 0){
            Toastr::success(translate('messages.order_already_paid'));
            return redirect()->route('payment-success');
        }

        if(($order?->customer?->wallet_balance ?? 0) < $unpaidAmount)
        {
            Toastr::error(translate('messages.insufficient_balance'));
            return back();
        }
        // Create wallet transaction for only the unpaid amount
        $transaction = CustomerLogic::create_wallet_transaction(user_id:$order->user_id,amount: $unpaidAmount, transaction_type:'order_place',referance: $order->id);
        if ($transaction != false) {
            try {
                $order->transaction_reference = $transaction->transaction_id;
                $order->payment_method = 'wallet';
                $order->partially_paid_amount = $partiallyPaid + $unpaidAmount;
                $order->payment_status = 'paid';
                $order->order_status = 'confirmed';
                $order->confirmed = now();
                $order?->save();
                Helpers::send_order_notification($order);
            } catch (\Exception $e) {
                info($e->getMessage());
            }

            if ($order->callback != null) {
                return redirect($order->callback . '&status=success');
            }else{
                return \redirect()->route('payment-success');
            }
        }
        else{
            $order->payment_method = 'wallet';
            $order->order_status = 'failed';
            $order->failed = now();
            $order?->save();
            if ($order->callback != null) {
                return redirect($order->callback . '&status=fail');
            }else{
                return \redirect()->route('payment-fail');
            }
        }

    }
}
