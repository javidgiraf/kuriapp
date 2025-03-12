<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scheme extends Model
{
    use HasFactory;
    protected $fillable = [
        'title_en',
        'title_ml',
        'total_period',
        'pdf_file',
        'status',
        'scheme_type_id',
        'payment_terms_en',
        'terms_and_conditions_en',
        'description_en',
        'payment_terms_ml',
        'terms_and_conditions_ml',
        'description_ml',
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
