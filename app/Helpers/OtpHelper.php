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
    
    public static function sendOtpSms($mobile, $otp)
    {
        $apiKey   = env('MY_SMS_MANTRA_API_KEY');
        $clientId = env('MY_SMS_MANTRA_CLIENT_ID');
        $senderId = env('MY_SMS_MANTRA_SENDER_ID');
        $apiUrl   = env('MY_SMS_MANTRA_API_URL');

        $mobile = preg_replace('/\D/', '', $mobile); 
        if (strlen($mobile) == 10) {
            $mobile = "91" . $mobile; 
        }

        if (!preg_match('/^91\d{10}$/', $mobile)) {
            \Log::error("Invalid Mobile Number: " . $mobile);
            return false;
        }

        $message = "Dear customer, $otp is your OTP for completing the Registration for Madhurima Gold and Diamonds Mobile App. Please do not share with anyone. Thank you.";

        $queryParams = http_build_query([
            'ApiKey'        => $apiKey,
            'ClientId'      => $clientId,
            'SenderId'      => $senderId,
            'Message'       => $message,
            'MobileNumbers' => $mobile,
            'Is_Unicode'    => false,
            'Is_Flash'      => 0
        ]);

        $url = "$apiUrl?$queryParams";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        \Log::info("OTP API Response: " . $response . " | HTTP Code: " . $httpCode);

        if ($curlError) {
            \Log::error("cURL Error: " . $curlError);
            return false;
        }

        $responseData = json_decode($response, true);
        
        return ($httpCode == 200 && isset($responseData['ErrorCode']) && $responseData['ErrorCode'] == '000');
    }

}
