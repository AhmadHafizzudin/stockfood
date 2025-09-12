<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'biller_code', 'amount', 'currency', 'email',
        'status', 'zen_session_id', 'payref_id', 'raw_response'
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
}
