<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSubscription; 
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Carbon\Carbon;


class SendFirebaseNotification extends Command
{
    // Command name
    protected $signature = 'send:payment-reminder';

    // Command description
    protected $description = 'Send payment reminders to users based on their due date.';

    // Handle method for executing the command
  public function handle()
{
    $users = UserSubscription::with(['deposits', 'user', 'schemeSetting', 'user.customer'])
        ->get();

    $messaging = (new Factory)
        ->withServiceAccount(storage_path('app/google/madhurima-gold-a20be1d55954.json'))
        ->createMessaging();

    $currentMonth = now()->format('Y-m');

    $due_duration = $users->first()->schemeSetting->due_duration;

    $due_date = now()->startOfMonth()->addDays($due_duration - 1);

    $notification_date = $due_date->copy()->subDays(3);

    if (now()->between($notification_date->startOfDay(), $due_date->endOfDay())) {

        $tokensToNotify = [];

        foreach ($users as $user_subscription) {

            $depositExistsThisMonth = $user_subscription->deposits->contains(function ($deposit) use ($currentMonth) {
                return Carbon::parse($deposit->paid_at)->format('Y-m') === $currentMonth && $deposit->status == 0;
            });

            if (!$depositExistsThisMonth) {

                $token_id = $user_subscription->user->customer->token_id ?? null;

                if ($token_id && !in_array($token_id, $tokensToNotify)) {
                    $tokensToNotify[] = $token_id; // Collect token IDs
                } elseif (!$token_id) {

                    \Log::warning("Token ID is missing for user: {$user_subscription->user_id}");
                }
            }
        }

        if (!empty($tokensToNotify)) {

            $title = "Payment Reminder";
            $body = "Your payment is due on {$due_date->format('Y-m-d')}. Please make the payment before the due date.";

            $notification = Notification::create($title, $body);

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData([
                    // Your additional data goes here
                ]);

            try {

                $response = $messaging->sendMulticast($message, $tokensToNotify);

                \Log::info("One notification sent to multiple users.", ['response' => $response]);
            } catch (\Throwable $e) {

                \Log::error("Failed to send notification to users", ['error' => $e->getMessage()]);
            }
        }
    }

    // Return an integer status code, not a JsonResponse
    return 0; // 0 indicates success in Laravel commands
}

}
