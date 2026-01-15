<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BakongService;
use App\Services\KhqrService;
use App\Services\TelegramService; // Explicit import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalPaymentController extends Controller
{
    protected $khqrService;
    protected $bakongService;
    protected $telegramService;

    public function __construct(
        KhqrService $khqrService,
        BakongService $bakongService,
        \App\Services\TelegramService $telegramService
    ) {
        $this->khqrService = $khqrService;
        $this->bakongService = $bakongService;
        $this->telegramService = $telegramService;
    }

    /**
     * Generate QR Code (Encrypted Payload)
     */
    public function generateQr(Request $request)
    {
        // 1. Read Request (No Decryption)
        $payload = $request->all();

        // 2. Validate Usage
        $amount = $payload['amount'] ?? 0;
        $currency = $payload['currency'] ?? 'USD';
        $telegramChatId = $payload['telegram_chat_id'] ?? null;

        // Optional Overrides
        $user = User::first(); // Owner
        $config = PaymentConfig::where('user_id', $user->id)->first();

        $bakongId = $payload['bakong_account_id'] ?? $config->bakong_id;
        $merchantName = $payload['merchant_name'] ?? $config->merchant_name;
        $merchantCity = $payload['merchant_city'] ?? $config->merchant_city;

        if ($amount <= 0 || empty($bakongId)) {
            return response()->json(['error' => 'Invalid Data'], 422);
        }

        // 3. Generate QR
        // Allow custom order ID (order_id or orderId)
        $customOrderId = $payload['order_id'] ?? $payload['orderId'] ?? null;
        $orderId = $customOrderId ? substr($customOrderId, 0, 25) : ('EXT-' . strtoupper(Str::random(8)));

        try {
            $khqrString = $this->khqrService->generateKhqrString(
                $bakongId,
                (float) $amount,
                $currency, // Pass currency
                $orderId,
                $merchantName,
                $merchantCity
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'QR Generation Failed'], 500);
        }

        // 4. Store Transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'remark' => "External API" . ($telegramChatId ? " (Tel: $telegramChatId)" : ""),
            'khqr_string' => $khqrString,
            'expires_at' => now()->addHours(24),
        ]);

        // Save Telegram Chat ID in metadata (using a hack since we don't have a meta column, appending to remark or description is brittle, but user asked for it in prompt flow. Ideally migration.)
        // Actually, user said "Store his TELEGRAM_CHAT_ID".
        // I will overload the 'remark' field or 'description' if possible.
        // Let's use the 'remark' field properly: "EXT|$telegramChatId"
        if ($telegramChatId) {
            $transaction->remark = "EXT|" . $telegramChatId;
            $transaction->save();
        }

        $md5 = md5($khqrString);

        // Generate Deep Link via Bakong API (Optional but recommended)
        $deepLink = null;

        // Check if client provided source info
        $sourceInfo = $payload['source_info'] ?? null;

        // If sourceInfo is provided OR specifically requested
        if ($sourceInfo) {
            // Map keys if necessary or pass directly if keys match API
            // Client should send: { appIconUrl, appName, appDeepLinkCallback }
            $dlResult = $this->bakongService->generateDeeplink($khqrString, $sourceInfo);
            if (($dlResult['responseCode'] ?? -1) === 0) {
                $deepLink = $dlResult['data']['shortLink'] ?? null;
            }
        }

        // Fallback to local link generation if API fails or not requested
        $paymentLink = $deepLink ?? $this->khqrService->generatePaymentLink($khqrString);

        // 5. Response (Plain JSON)
        return response()->json([
            'qr_string' => $khqrString,
            'md5' => $md5,
            'payment_link' => $paymentLink,
            'order_id' => $orderId
        ]);
    }

    /**
     * Check Batch Status (Plain JSON)
     */
    public function checkStatusBatch(Request $request)
    {
        // 1. Read Request (No Decryption)
        $payload = $request->all();

        $md5List = $payload['md5_list'] ?? [];
        if (!is_array($md5List) || empty($md5List)) {
            return response()->json(['error' => 'Invalid List'], 422);
        }

        // 2. Call Bakong API
        $apiResult = $this->bakongService->checkTransactionStatusList($md5List);

        // 3. Response (Plain JSON)
        return response()->json($apiResult);
    }

    /**
     * Check Single Status (Plain JSON)
     */
    public function checkStatus(Request $request)
    {
        // 1. Read Request (No Decryption)
        $payload = $request->all();

        $md5 = $payload['md5'] ?? null;
        if (empty($md5)) {
            return response()->json(['error' => 'Invalid MD5'], 422);
        }

        // 2. Call Bakong API
        $apiResult = $this->bakongService->checkTransactionStatus(null, $md5);
        $responseCode = $apiResult['responseCode'] ?? -1;

        // 3. Logic Handling
        if ($responseCode === 0) {
            // SUCCESS CASE
            // Prevent duplicate alerts: Only alert if we can transition a local transaction from 'pending' to 'paid'.

            // Find recent pending transactions to match MD5
            // (Optimize: In a real app, store MD5 or Hash in DB. traversing is OK for low volume)
            $pending = Transaction::where('status', 'pending')
                ->where('user_id', Request()->user() ? Request()->user()->id : User::first()->id) // Fallback to Owner if auth missing (Ext API uses key, no user session usually? Wait, api_clients don't map to users? ExternalPaymentController doesn't use auth:sanctum?)
                // Actually ExternalPaymentController isn't auth:sanctum, so user() is null.
                // We should query globally or by the user associated with the Key?
                // Current generateQr uses User::first(). Let's stick to that for now or just search all pending.
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $localTx = $pending->first(function ($t) use ($md5) {
                return md5($t->khqr_string) === $md5;
            });

            if ($localTx) {
                // Determine if we should notify (Transition Validation)
                $localTx->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);

                // Update remark to remove internal metadata if preferred, or keep it.

                $data = $apiResult['data'] ?? null;
                $amount = number_format($data['amount'] ?? 0, 2);
                $currency = $data['currency'] ?? 'KHR';
                $hash = $data['hash'] ?? 'N/A'; // Use Hash as Trx ID fallback
                $externalRef = $data['externalRef'] ?? $hash; // Prefer External Ref
                $payer = $data['fromAccountId'] ?? 'Guest';

                // Truncate/Format payer if it's an email/id
                // e.g. "foo@bar" -> "foo (*bar)" stylistic choice?
                // For now, keep as is or simple formatting.

                $trxDate = isset($data['createdDateMs'])
                    ? \Carbon\Carbon::createFromTimestampMs($data['createdDateMs'])->format('M d, h:i A')
                    : now()->format('M d, h:i A');

                // Fetch Merchant Name
                $merchantName = 'Shop';
                if ($localTx->user_id) {
                    $conf = PaymentConfig::where('user_id', $localTx->user_id)->first();
                    if ($conf)
                        $merchantName = $conf->merchant_name;
                }

                $chatId = $payload['telegram_chat_id'] ?? null;

                // Format: $0.01 paid by YUN VORAK (*436) on Jan 07, 06:42 PM via KHQR at {merchant_name}. Order ID {id}. Trx. ID: 123
                // Adjusting for currency symbol
                $amountDisplay = ($currency === 'USD') ? "$$amount" : "$amount $currency";

                $msg = "✅ *Payment Received*\n\n" .
                    "{$amountDisplay} paid by *{$payer}* on {$trxDate} via KHQR at *{$merchantName}*.\n" .
                    "Order ID: `{$localTx->order_id}`.\n" .
                    "Trx. ID: `{$externalRef}`";

                $this->telegramService->sendMessage($msg, $chatId, 'Markdown');
            } else {
                // Already paid or not found locally.
                // User requirement: "if user check again should not alert".
            }

        } elseif ($responseCode === 1) {
            // NOT FOUND CASE
            // User requirement: "if not found don't see send" -> Do nothing.

        } else {
            // ERROR CASE (e.g. connection error, bakong error 500, etc)
            // User requirement: "other case if something when wrong should alert also"

            $chatId = $payload['telegram_chat_id'] ?? null;
            $errorMsg = $apiResult['responseMessage'] ?? 'Unknown Error';

            $msg = "⚠️ *Payment Check Failed*\n\n" .
                "❌ Error: {$errorMsg}\n" .
                "🔍 MD5: `{$md5}`";

            $this->telegramService->sendMessage($msg, $chatId, 'Markdown');
        }

        // 4. Response (Plain JSON)
        return response()->json($apiResult);
    }

    private function sendNotification($transaction)
    {
        // Parse Telegram Chat ID from remark "EXT|123456"
        if (Str::startsWith($transaction->remark, 'EXT|')) {
            $chatId = explode('|', $transaction->remark)[1] ?? null;
            if ($chatId) {
                try {
                    $telegram = new \App\Services\TelegramService();
                    // Add method to force chat ID override
                    $telegram->sendPaymentSuccess($transaction, $chatId);
                } catch (\Exception $e) {
                }
            }
        }
    }
}
