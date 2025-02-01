<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoldRate extends Model
{
    use HasFactory;
    protected $fillable = [
        'per_gram',
        'per_pavan',
        'date_on',
        'status',
    ];
}
