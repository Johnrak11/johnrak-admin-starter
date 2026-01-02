<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Login attempt received', [
            'email' => (string) $request->input('email'),
            'device_name' => (string) $request->input('device_name')
        ]);
        $payload = json_decode($request->getContent(), true) ?: [];
        if (!empty($payload)) { $request->merge($payload); }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Trusted device skip
        $trustedToken = (string) $request->header('X-Trusted-Device', '');
        if ($trustedToken && $user->hasTrustedDevice(hash('sha256', $trustedToken))) {
            $user->tokens()->delete();
            $tokenName = $validated['device_name'] ?? 'johnrak-admin';
            $token = $user->createToken($tokenName, ['*'])->plainTextToken;
            return response()->json([
                'token' => $token,
                'user' => $user->only(['id', 'name', 'email', 'role']),
            ]);
        }

        if ($user->isTwoFactorEnabled()) {
            $challengeId = Str::uuid()->toString();
            $challengeToken = Str::random(64);
            Cache::put("login2fa:{$challengeId}", [
                'user_id' => $user->id,
                'token' => $challengeToken,
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
            ], now()->addMinutes(5));

            return response()->json([
                'requires_2fa' => true,
                'challenge_id' => $challengeId,
                'challenge_token' => $challengeToken,
            ]);
        }

        $user->tokens()->delete();
        $tokenName = $validated['device_name'] ?? 'johnrak-admin';
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        $response = [
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ];

        \Illuminate\Support\Facades\Log::info('Login successful', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        return response()->json($response);
    }

    public function login2fa(Request $request)
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string'],
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'remember_device' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cache = Cache::get("login2fa:{$validated['challenge_id']}");
        if (! $cache || ($cache['token'] ?? '') !== $validated['challenge_token']) {
            return response()->json(['message' => 'Challenge expired'], 422);
        }
        Cache::forget("login2fa:{$validated['challenge_id']}");

        $user = User::find($cache['user_id'] ?? 0);
        if (! $user || ! $user->isTwoFactorEnabled()) {
            return response()->json(['message' => 'Invalid challenge'], 422);
        }

        $ok = false;
        if (! empty($validated['recovery_code'])) {
            $ok = $user->consumeRecoveryCode($validated['recovery_code']);
        } elseif (! empty($validated['code'])) {
            $secret = $user->getTwoFactorSecretDecrypted();
            if ($secret) {
                $google2fa = new Google2FA();
                $ok = $google2fa->verifyKey($secret, $validated['code']);
            }
        }
        if (! $ok) {
            return response()->json(['message' => 'Invalid 2FA verification'], 422);
        }

        $user->two_factor_last_used_at = now();
        $user->save();

        $user->tokens()->delete();
        $tokenName = $validated['device_name'] ?? 'johnrak-admin';
        $token = $user->createToken($tokenName, ['*'])->plainTextToken;

        $trustedDeviceToken = null;
        if (! empty($validated['remember_device'])) {
            $trustedDeviceToken = Str::random(32);
            $user->addTrustedDevice(hash('sha256', $trustedDeviceToken), [
                'name' => $tokenName,
            ]);
        }

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'trusted_device_token' => $trustedDeviceToken,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['ok' => true]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'mfa_code' => ['nullable', 'string'],
        ]);

        // 1. Check Current Password
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        // 2. Check 2FA if enabled
        if ($user->isTwoFactorEnabled()) {
            if (empty($validated['mfa_code'])) {
                throw ValidationException::withMessages([
                    'mfa_code' => ['MFA Code is required to change password.'],
                ]);
            }

            $secret = $user->getTwoFactorSecretDecrypted();
            $google2fa = new Google2FA();
            
            // Allow window of 1 (30s drift)
            if (!$google2fa->verifyKey($secret, $validated['mfa_code'], 1)) {
                throw ValidationException::withMessages([
                    'mfa_code' => ['Invalid MFA Code.'],
                ]);
            }
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
