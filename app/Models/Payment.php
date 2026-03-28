<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'amount',
        'merchant_order_id', 'phonepe_order_id', 'source', 'transaction_id',
        'status', 'response_data', 'last_synced_at', 'phonepe_link',
    ];

    protected $casts = [
        'response_data' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
