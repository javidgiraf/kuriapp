<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;
    protected $table="address";
    protected $fillable = [
        'user_id',
        'address',
        'district_id',
        'state_id',
        'country_id',
        'pincode',
        'status',
    ];

    public function country()
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }
    public function state()
    {
        return $this->hasOne(State::class, 'id', 'state_id');
    }
    public function district()
    {
        return $this->hasOne(District::class, 'id', 'district_id');
    }

}
