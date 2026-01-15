<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\Transaction;
use App\Services\KhqrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function getConfig(Request $request)
    {
        $config = PaymentConfig::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['enabled' => true]
        );

        return response()->json([
            'config' => [
                'aba_merchant_id' => $config->bakong_id, // Merchant ID (ABA or Bakong)
                'merchant_name' => $config->merchant_name,
                'merchant_city' => $config->merchant_city,
                'enabled' => $config->enabled,
                'provider' => $config->provider ?? 'aba',
                'bakong_token' => $config->provider_merchant_info['bakong_token'] ?? null,
            ],
        ]);
    }



    public function saveConfig(Request $request)
    {
        $validated = $request->validate([
            'aba_merchant_id' => ['required', 'string', 'max:100'],
            'merchant_name' => ['nullable', 'string', 'max:200'],
            'merchant_city' => ['nullable', 'string', 'max:50'],
            'merchant_email' => ['nullable', 'email', 'max:100'],
            'enabled' => ['required', 'boolean'],
            'bakong_token' => ['nullable', 'string', 'max:255'],
        ]);

        $config = PaymentConfig::firstOrCreate(['user_id' => $request->user()->id]);

        // Map aba_merchant_id to bakong_id column (keeping variable name for now to avoid migration, but treating as Bakong ID)
        $config->bakong_id = $validated['aba_merchant_id'];
        $config->merchant_name = $validated['merchant_name'];
        $config->merchant_city = $validated['merchant_city'] ?? 'Phnom Penh';
        $config->enabled = $validated['enabled'];
        $config->provider = 'bakong'; // Force Bakong

        // Save generic info in json
        $info = $config->provider_merchant_info ?? [];
        if ($request->has('bakong_token')) {
            $info['bakong_token'] = $request->input('bakong_token');
        }
        if ($request->has('merchant_email')) {
            $info['merchant_email'] = $request->input('merchant_email');
        }
        $config->provider_merchant_info = $info;

        $config->save();

        return response()->json([
            'config' => [
                'aba_merchant_id' => $config->bakong_id,
                'merchant_name' => $config->merchant_name,
                'merchant_city' => $config->merchant_city,
                'merchant_email' => $config->provider_merchant_info['merchant_email'] ?? null,
                'enabled' => $config->enabled,
                'provider' => 'bakong',
                'bakong_token' => $config->provider_merchant_info['bakong_token'] ?? null,
            ],
        ]);
    }

    public function testPayment(Request $request, KhqrService $khqrService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'order_id' => ['nullable', 'string', 'max:25'],
        ]);

        $config = PaymentConfig::where('user_id', $request->user()->id)->first();
        if (!$config || !$config->enabled || !$config->bakong_id) {
            return response()->json(['error' => 'Bakong Merchant payment not configured'], 422);
        }

        // Validate Bakong/ABA Merchant ID
        $merchantId = trim($config->bakong_id);
        if (empty($merchantId) || strlen($merchantId) > 25) {
            return response()->json(['error' => 'Invalid Merchant ID (must be 1-25 characters)'], 422);
        }

        // Auto-generate Order ID if not provided
        $orderId = $validated['order_id'] ?? 'ORD-' . strtoupper(Str::random(8));

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'order_id' => $orderId,
            'amount' => $validated['amount'],
            'currency' => 'USD',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        // Get merchant info
        $merchantCity = $config->merchant_city ?? 'Phnom Penh';

        // Generate KHQR
        try {
            $khqrString = $khqrService->generateKhqrString(
                $merchantId,
                (float) $validated['amount'],
                $orderId,
                $config->merchant_name,
                $merchantCity
            );
        } catch (\Exception $e) {
            Log::error('KHQR generation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to generate QR code: ' . $e->getMessage()], 500);
        }

        $transaction->khqr_string = $khqrString;
        $transaction->remark = $orderId;
        $transaction->save();

        // Generate QR code images
        $qrSvg = $khqrService->generateQrCode($khqrString, 'svg');
        $qrPng = null;
        if (extension_loaded('imagick')) {
            try {
                $qrPng = $khqrService->generateQrCode($khqrString, 'png');
            } catch (\Throwable $e) {
                // Fallback to SVG
            }
        }

        $paymentLink = $khqrService->generatePaymentLink($khqrString);

        // Calculate MD5 for Bakong Status Check
        $md5 = md5($khqrString);

        return response()->json([
            'transaction' => $transaction,
            'khqr_string' => $khqrString,
            'md5' => $md5,
            'qr_svg' => $qrSvg,
            'qr_png' => $qrPng,
            'payment_link' => $paymentLink,
        ]);
    }

    public function listTransactions(Request $request)
    {
        $query = Transaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by order_id
        if ($request->has('search')) {
            $query->where('order_id', 'like', '%' . $request->search . '%');
        }

        $transactions = $query->paginate(20);

        return response()->json($transactions);
    }

    public function getTransaction(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json(['transaction' => $transaction]);
    }



    public function checkStatus(Request $request, \App\Services\BakongService $bakongService)
    {
        $validated = $request->validate([
            'md5' => ['required', 'string'],
            'token' => ['nullable', 'string'],
        ]);

        $config = PaymentConfig::where('user_id', $request->user()->id)->first();
        // Use provided token or fallback to stored token
        $token = $validated['token'] ?? ($config->provider_merchant_info['bakong_token'] ?? null);

        if (!$token) {
            return response()->json(['error' => 'Bakong Token not provided'], 400);
        }

        // Result handled seamlessly by service based on config (Tunnel or Direct)
        $result = $bakongService->checkTransactionStatus($token, $validated['md5']);

        if (!$result) {
            return response()->json(['status' => 'unknown', 'message' => 'Failed to check status'], 500);
        }

        // Response handling
        // 0 = Success
        if (($result['responseCode'] ?? -1) === 0) {
            // Find matched pending transaction by calculating MD5 of khqr_string
            // This is slightly inefficient but works without migration for now.
            // Or assume polling only checks recent transactions.
            $pending = Transaction::where('status', 'pending')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->limit(20) // Limit to recent pending to avoid heavy load
                ->get();

            $transaction = $pending->first(function ($t) use ($validated) {
                return md5($t->khqr_string) === $validated['md5'];
            });

            if ($transaction) {
                $transaction->status = 'paid';
                $transaction->paid_at = now();
                $transaction->save();

                // Send Telegram Notification
                try {
                    $telegramService = new \App\Services\TelegramService();
                    $telegramService->sendPaymentSuccess($transaction);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Telegram Notification Error', ['msg' => $e->getMessage()]);
                }
            }

            return response()->json([
                'status' => 'paid',
                'data' => $result
            ]);
        }

        return response()->json([
            'status' => 'pending',
            'details' => $result
        ]);
    }

    public function renewToken(Request $request, \App\Services\BakongService $bakongService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $bakongService->renewToken($validated['email']);

        if (!$result || ($result['responseCode'] ?? -1) !== 0) {
            return response()->json([
                'error' => 'Failed to renew token. ' . ($result['responseMessage'] ?? ''),
                'details' => $result
            ], 400);
        }

        // Successfully got token
        $token = $result['data']['token'] ?? null;

        if ($token) {
            // Save it automatically?
            $config = PaymentConfig::firstOrCreate(['user_id' => $request->user()->id]);
            $info = $config->provider_merchant_info ?? [];
            $info['bakong_token'] = $token;
            // Also update email if different
            $info['merchant_email'] = $validated['email'];
            $config->provider_merchant_info = $info;
            $config->save();
        }

        return response()->json([
            'message' => 'Token renewed successfully',
            'token' => $token
        ]);
    }
}
