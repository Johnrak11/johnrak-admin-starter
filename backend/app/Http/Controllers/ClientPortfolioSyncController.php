<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortfolioExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;

class ClientPortfolioSyncController extends Controller
{
    public function issueToken(Request $request)
    {
        $user = $request->user();
        $ttl = (int) env('PORTFOLIO_SYNC_TOKEN_TTL_MINUTES', 1440);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        Cache::put("portfolio_sync_token:{$hash}", [
            'user_id' => $user->id,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes($ttl));

        return response()->json([
            'token' => $token,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
        ]);
    }

    public function sync(Request $request, PortfolioExportService $service)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);
        $validator->validate();

        $hash = hash('sha256', (string) $request->input('token'));
        $entry = Cache::get("portfolio_sync_token:{$hash}");
        if (! $entry) {
            return response()->json(['message' => 'Invalid or expired token'], 422);
        }
        $user = User::find($entry['user_id'] ?? 0);
        if (! $user) {
            return response()->json(['message' => 'Invalid token owner'], 422);
        }
        $secret = $user->getTwoFactorSecretDecrypted();
        if (! $secret) {
            return response()->json(['message' => '2FA not enabled'], 422);
        }
        $g2fa = new Google2FA();
        if (! $g2fa->verifyKey($secret, (string) $request->input('otp'))) {
            return response()->json(['message' => 'Invalid OTP'], 422);
        }

        $data = $service->build($user->id);

        return response()->json($data);
    }
}
