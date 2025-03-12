<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayTransaction extends Model
{
    use HasFactory;
   
    protected $fillable = [
        'deposit_id',
        'razorpay_payment_id',
        'razorpay_order_id',
        'razorpay_signature',
        'status'
    ];
}
