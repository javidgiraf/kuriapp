<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'total_amount',
        'total_period',
        'schedule_amount',
        'description',
        'status',
        'scheme_type_id'
    ];

    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->attributes['total_amount'], 2);
    }
    public function getFormattedScheduleAmountAttribute()
    {
        return number_format($this->attributes['schedule_amount'], 2);
    }
    
    public function schemeType()
    {
        return $this->belongsTo(SchemeType::class, 'scheme_type_id');
    }

    public function schemeSetting()
    {
        return $this->hasOne(SchemeSetting::class, 'scheme_id', 'id');
    }
}
