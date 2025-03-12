<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'option_name',
        'option_code',
        'option_value',
        'terms_and_conditions_en',
        'terms_and_conditions_ml',
        'symbol',
        'status'
    ];
}
