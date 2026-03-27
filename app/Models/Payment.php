<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

   protected $fillable = [
        'name', 'email', 'phone', 'amount',
        'merchant_order_id', 'transaction_id',
        'status', 'response_data'
    ];

    protected $casts = [
        'response_data' => 'array',
    ];
}
