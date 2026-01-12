<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $chatId;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->chatId = env('TELEGRAM_CHAT_ID', '1074091883'); // Default from user request
    }

    public function sendPaymentSuccess($transaction)
    {
        if (!$this->botToken) {
            Log::warning('Telegram Bot Token not configured.');
            return;
        }

        $amount = number_format($transaction->amount, 2);
        $currency = $transaction->currency;
        $orderId = $transaction->order_id;
        $date = now()->format('Y-m-d H:i:s');

        $message = "✅ *Payment Received*\n\n" .
            "💰 *Amount:* {$amount} {$currency}\n" .
            "🆔 *Order ID:* `{$orderId}`\n" .
            "📅 *Date:* {$date}\n" .
            "🔗 *Status:* Success\n\n" .
            "Thank you!";

        $this->sendMessage($message);
    }

    public function sendMessage($message)
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

            $response = Http::post($url, [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

            if (!$response->successful()) {
                Log::error('Telegram Send Failed', ['body' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram Exception', ['error' => $e->getMessage()]);
        }
    }
}
