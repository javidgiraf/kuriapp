<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepositPeriod extends Model
{
    use HasFactory;
    protected $fillable = [
        'deposit_id',
        'scheme_amount',
        'due_date',
        'is_due',
        'status',
    ];

    public function deposit()
    {
        return $this->belongsTo(Deposit::class, 'deposit_id', 'id');
    }
}
