<?php

namespace App\Http\Controllers;

use App\Models\PaymentConfig;
use App\Models\Transaction;
use App\Models\PaymentToken;
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
            [
                'provider' => 'bakong',
                'enabled' => true,
            ]
        );

        return response()->json([
            'config' => [
                'provider' => $config->provider,
                'bakong_id' => $config->bakong_id,
                'merchant_name' => $config->merchant_name,
                'enabled' => $config->enabled,
                'webhook_configured' => !empty($config->webhook_secret),
            ],
        ]);
    }

    public function getMerchantInfo(Request $request)
    {
        $config = PaymentConfig::where('user_id', $request->user()->id)->first();

        $providerMerchantInfo = $config->provider_merchant_info ?? [];

        // Default values for each provider
        $defaultInfo = [
            'merchant_city' => 'Phnom Penh',
            'merchant_phone' => null,
            'merchant_email' => null,
            'merchant_address' => null,
        ];

        return response()->json([
            'merchant_info' => [
                'aba' => array_merge($defaultInfo, $providerMerchantInfo['aba'] ?? []),
                'bakong' => array_merge($defaultInfo, $providerMerchantInfo['bakong'] ?? []),
            ],
        ]);
    }

    public function saveMerchantInfo(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:aba,bakong'],
            'merchant_city' => ['nullable', 'string', 'max:15'], // EMV standard max 15 chars
            'merchant_phone' => ['nullable', 'string', 'max:50'],
            'merchant_email' => ['nullable', 'email', 'max:200'],
            'merchant_address' => ['nullable', 'string', 'max:500'],
        ]);

        $config = PaymentConfig::firstOrCreate(['user_id' => $request->user()->id]);

        $provider = $validated['provider'];
        $providerMerchantInfo = $config->provider_merchant_info ?? [];

        // Update merchant info for the specific provider
        $providerMerchantInfo[$provider] = [
            'merchant_city' => $validated['merchant_city'] ?? 'Phnom Penh',
            'merchant_phone' => $validated['merchant_phone'] ?? null,
            'merchant_email' => $validated['merchant_email'] ?? null,
            'merchant_address' => $validated['merchant_address'] ?? null,
        ];

        $config->provider_merchant_info = $providerMerchantInfo;
        $config->save();

        return response()->json([
            'merchant_info' => $providerMerchantInfo[$provider],
        ]);
    }

    public function saveConfig(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:bakong,aba'],
            'bakong_id' => ['required', 'string', 'max:100'],
            'merchant_name' => ['nullable', 'string', 'max:200'],
            'enabled' => ['required', 'boolean'],
        ]);

        $config = PaymentConfig::firstOrCreate(['user_id' => $request->user()->id]);

        $config->fill($validated);
        $config->save();

        return response()->json([
            'config' => $config->only(['provider', 'bakong_id', 'merchant_name', 'enabled']),
        ]);
    }

    public function testPayment(Request $request, KhqrService $khqrService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'order_id' => ['required', 'string', 'max:25'], // Limit to 25 chars for EMV standard
        ]);

        $config = PaymentConfig::where('user_id', $request->user()->id)->first();
        if (!$config || !$config->enabled || !$config->bakong_id) {
            return response()->json(['error' => 'Payment not configured'], 422);
        }

        // Validate Bakong ID format
        $bakongId = trim($config->bakong_id);
        if (empty($bakongId) || strlen($bakongId) > 25) {
            return response()->json(['error' => 'Invalid Bakong ID format (must be 1-25 characters)'], 422);
        }

        // Create test transaction
        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'order_id' => $validated['order_id'],
            'amount' => $validated['amount'],
            'currency' => 'USD',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        // Get provider-specific merchant info
        $provider = $config->provider ?? 'bakong';
        $providerMerchantInfo = $config->provider_merchant_info ?? [];
        $merchantInfo = $providerMerchantInfo[$provider] ?? [];
        $merchantCity = $merchantInfo['merchant_city'] ?? $config->merchant_city ?? 'Phnom Penh';

        // Generate KHQR
        try {
            $khqrString = $khqrService->generateKhqrString(
                $bakongId,
                (float) $validated['amount'],
                $validated['order_id'],
                $config->merchant_name,
                $provider, // Pass the provider (aba or bakong)
                $merchantCity // Pass merchant city from provider-specific config
            );
        } catch (\Exception $e) {
            Log::error('KHQR generation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to generate QR code: ' . $e->getMessage()], 500);
        }

        $transaction->khqr_string = $khqrString;
        $transaction->remark = $validated['order_id'];
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

        $bakongLink = $khqrService->generateBakongLink($khqrString);

        return response()->json([
            'transaction' => $transaction,
            'khqr_string' => $khqrString,
            'qr_svg' => $qrSvg,
            'qr_png' => $qrPng,
            'bakong_link' => $bakongLink,
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

    public function generateToken(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $token = PaymentToken::create([
            'user_id' => $request->user()->id,
            'token' => PaymentToken::generate(),
            'name' => $validated['name'] ?? 'Python API Token',
            'expires_at' => isset($validated['expires_days'])
                ? now()->addDays($validated['expires_days'])
                : null,
            'is_active' => true,
        ]);

        return response()->json([
            'token' => $token->token, // Show once
            'name' => $token->name,
            'expires_at' => $token->expires_at,
        ]);
    }

    public function listTokens(Request $request)
    {
        $tokens = PaymentToken::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'tokens' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'is_active' => $token->is_active,
                    'is_valid' => $token->isValid(),
                    'created_at' => $token->created_at,
                ];
            }),
        ]);
    }

    public function revokeToken(Request $request, PaymentToken $token)
    {
        if ($token->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $token->is_active = false;
        $token->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Webhook endpoint for Telegram bot
     * This is called by the Python listener when payment is received
     */
    public function webhook(Request $request)
    {
        // Auth: API Access Token (Bearer)
        // Bot must send: Authorization: Bearer <token>
        $bearer = $request->bearerToken();
        if (!$bearer) {
            Log::warning('Payment webhook: Missing bearer token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $paymentToken = PaymentToken::where('token', $bearer)->first();
        if (!$paymentToken || !$paymentToken->isValid()) {
            Log::warning('Payment webhook: Invalid/expired token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $paymentToken->last_used_at = now();
        $paymentToken->save();

        // Resolve the owner config from the token owner
        $config = PaymentConfig::firstOrCreate(
            ['user_id' => $paymentToken->user_id],
            ['provider' => 'bakong', 'enabled' => true]
        );

        $validated = $request->validate([
            'order_id' => ['nullable', 'string'], // Order ID is optional - can be null
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'max:3'],
            'transaction_id' => ['required', 'string'], // ABA transaction ID (for idempotency)
            'payer_name' => ['nullable', 'string', 'max:200'],
            'payer_phone' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Use database transaction to prevent race conditions
        return DB::transaction(function () use ($validated, $config) {
            // Check if this transaction was already processed (idempotency)
            $existing = Transaction::where('transaction_id', $validated['transaction_id'])->first();
            if ($existing) {
                Log::info('Payment webhook: Duplicate transaction ignored', [
                    'transaction_id' => $validated['transaction_id'],
                ]);
                return response()->json([
                    'message' => 'Already processed',
                    'transaction' => $existing,
                ]);
            }

            // Find the pending transaction by order_id (if provided) or by transaction_id
            $transaction = null;

            if (!empty($validated['order_id'])) {
                // Try to find by order_id first
                $transaction = Transaction::where('order_id', $validated['order_id'])
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();
            }

            // If not found by order_id, try to find by transaction_id (for payments without order_id)
            if (!$transaction) {
                $transaction = Transaction::where('transaction_id', $validated['transaction_id'])
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();
            }

            // If still not found, create a new transaction record (for payments without order_id)
            if (!$transaction) {
                if (empty($validated['order_id'])) {
                    // Create a new transaction for payments without order_id
                    $transaction = Transaction::create([
                        'user_id' => $config->user_id,
                        'order_id' => null,
                        'amount' => $validated['amount'],
                        'currency' => $validated['currency'],
                        'status' => 'pending',
                        'transaction_id' => $validated['transaction_id'],
                        'payer_name' => $validated['payer_name'] ?? null,
                        'payer_phone' => $validated['payer_phone'] ?? null,
                        'metadata' => $validated['metadata'] ?? [],
                    ]);
                } else {
                    Log::warning('Payment webhook: Transaction not found', [
                        'order_id' => $validated['order_id'],
                        'transaction_id' => $validated['transaction_id'],
                    ]);
                    return response()->json(['error' => 'Transaction not found'], 404);
                }
            }

            // Verify amount matches (only if transaction already existed with an amount)
            if ($transaction->wasRecentlyCreated === false && $transaction->amount) {
                $expectedAmount = (float) $transaction->amount;
                $receivedAmount = (float) $validated['amount'];

                if (abs($expectedAmount - $receivedAmount) > 0.01) {
                    // Amount mismatch - mark as error
                    $transaction->update([
                        'status' => 'error',
                        'metadata' => array_merge($transaction->metadata ?? [], [
                            'error' => 'Amount mismatch',
                            'expected' => $expectedAmount,
                            'received' => $receivedAmount,
                        ]),
                    ]);

                    Log::warning('Payment webhook: Amount mismatch', [
                        'order_id' => $validated['order_id'] ?? null,
                        'transaction_id' => $validated['transaction_id'],
                        'expected' => $expectedAmount,
                        'received' => $receivedAmount,
                    ]);

                    return response()->json(['error' => 'Amount mismatch'], 422);
                }
            }

            // Update transaction as paid
            $updateData = [
                'status' => 'paid',
                'transaction_id' => $validated['transaction_id'],
                'payer_name' => $validated['payer_name'] ?? $transaction->payer_name,
                'payer_phone' => $validated['payer_phone'] ?? $transaction->payer_phone,
                'paid_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], $validated['metadata'] ?? []),
            ];

            // Update order_id if it was null and now we have it
            if (empty($transaction->order_id) && !empty($validated['order_id'])) {
                $updateData['order_id'] = $validated['order_id'];
            }

            // Update amount if transaction was just created (for payments without order_id)
            if ($transaction->wasRecentlyCreated) {
                $updateData['amount'] = $validated['amount'];
                $updateData['currency'] = $validated['currency'] ?? 'USD';
            }

            $transaction->update($updateData);

            Log::info('Payment webhook: Transaction paid', [
                'order_id' => $validated['order_id'] ?? null,
                'transaction_id' => $validated['transaction_id'],
            ]);

            // Here you can trigger additional actions:
            // - Send email notification
            // - Unlock digital product
            // - Update user credits
            // - etc.

            return response()->json([
                'message' => 'Payment processed',
                'transaction' => $transaction->fresh(),
            ]);
        });
    }
}
