<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nominee extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'phone',
        'status',
    ];
}
