<?php

namespace App\Traits;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;

trait NotificationTraits
{
    public static function sendNotificationToAll($data)
    {
        // Path to your Firebase service account JSON file
        $serviceAccountPath = storage_path('app/google/madhurima-gold-a20be1d55954.json'); 

        // Initialize Firebase
        $firebase = (new Factory)
            ->withServiceAccount($serviceAccountPath); // Path to the service account key file

        // Get the messaging instance
        $messaging = $firebase->createMessaging();

        try {
            // Prepare the message
            $message = CloudMessage::new()
                ->withNotification([
                    'title' => $data['title'] ?? 'Default Title', // Dynamic title from data
                    'body' => $data['body'] ?? 'Default message body', // Dynamic body from data
                ])
                ->withTarget($data['device_token']); // Corrected to use the device token directly

            // Send the message via Firebase Cloud Messaging API
            $response = $messaging->send($message);

            // Log the response (this could be an array or object)
            Log::info('FCM Notification Response: ', (array) $response);

            return $response;
        } catch (\Exception $e) {
            // Log the error message if something goes wrong
            Log::error('FCM Notification error: ' . $e->getMessage());
            return 'Error: ' . $e->getMessage();
        }
    }
}
