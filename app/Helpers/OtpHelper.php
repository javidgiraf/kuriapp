<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;


class OtpHelper
{
    public static function getOtp()
    {
       
       
        $otp = rand(1000, 9999);
      
        return $otp;
    }
}
