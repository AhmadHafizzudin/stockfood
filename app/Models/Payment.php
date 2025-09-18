<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments'; // ensure correct table name

    protected $fillable = [
        'order_id',
        'biller_code',
        'amount',
        'currency',
        'email',
        'status',
        'zen_session_id',
        'payref_id',
        'raw_response',
        'gateway',
    ];

    protected $casts = [
        'amount'       => 'decimal:2', // always numeric with 2 decimals
        'raw_response' => 'array',     // auto JSON encode/decode
    ];

    /**
     * Boot method to set defaults before insert
     */
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (empty($payment->order_id)) {
                $payment->order_id = 'ORDER' . time();
            }
            if (empty($payment->status)) {
                $payment->status = 'pending';
            }
            if (empty($payment->currency)) {
                $payment->currency = 'MYR';
            }
        });
    }
}
