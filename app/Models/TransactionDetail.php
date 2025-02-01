<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionDetail extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'deposit_id',
        'transaction_no',
        'payment_method',
        'paid_amount',
        'payment_response',
        'receipt_upload',
        'remark',
        'status',
    ];

    public function deposit()
    {
        return $this->hasOne(Deposit::class, 'id', 'deposit_id');
    }
}
