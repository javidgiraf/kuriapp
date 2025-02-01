<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldDeposit extends Model
{
    use HasFactory;
    protected $fillable = [
        'deposit_id',
        'gold_weight',
        'gold_unit',
        'status',

    ];
}
