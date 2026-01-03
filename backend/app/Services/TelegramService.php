<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public static function sendMessage(string $message)
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            Log::warning("TelegramService: Missing credentials in .env");
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            if ($response->failed()) {
                Log::error("TelegramService Failed: " . $response->body());
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("TelegramService Error: " . $e->getMessage());
            return false;
        }
    }
}
