<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user->setTwoFactorSecretEncrypted($secret);
        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $company = config('app.name', 'Johnrak');
        $otpauthUrl = $google2fa->getQRCodeUrl($company, $user->email, $secret);
        $qrSvgRaw = QrCode::format('svg')->size(180)->generate($otpauthUrl);
        $qrSvg = is_string($qrSvgRaw) ? $qrSvgRaw : (string) $qrSvgRaw;
        $qrPngBase64 = null;
        if (extension_loaded('imagick')) {
            try {
                $qrPngRaw = QrCode::format('png')->size(180)->generate($otpauthUrl);
                $qrPngBase64 = 'data:image/png;base64,' . base64_encode($qrPngRaw);
            } catch (\Throwable $e) {
                $qrPngBase64 = null;
            }
        }

        return response()->json([
            'otpauth_url' => $otpauthUrl,
            'qr_svg' => $qrSvg,
            'qr_png_base64' => $qrPngBase64,
        ]);
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string'],
        ]);
        $validator->validate();

        $secret = $user->getTwoFactorSecretDecrypted();
        if (!$secret) {
            return response()->json(['message' => '2FA not initialized'], 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->input('code'));
        if (!$valid) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $codes = $user->generateRecoveryCodes(10);
        $user->setRecoveryCodesEncrypted($codes);
        $user->save();

        return response()->json(['recovery_codes' => $codes]);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);
        $validator->validate();

        $ok = false;
        if ($request->filled('recovery_code')) {
            $ok = $user->consumeRecoveryCode($request->input('recovery_code'));
        } elseif ($request->filled('code')) {
            $secret = $user->getTwoFactorSecretDecrypted();
            if ($secret) {
                $google2fa = new Google2FA();
                $ok = $google2fa->verifyKey($secret, $request->input('code'));
            }
        }
        if (!$ok) {
            return response()->json(['message' => 'Invalid verification'], 422);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_last_used_at = null;
        $user->two_factor_trusted_devices = null;
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function status(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'enabled' => (bool) $user->two_factor_enabled,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string'],
        ]);
        $validator->validate();

        $secret = $user->getTwoFactorSecretDecrypted();
        if (!$secret) {
            return response()->json(['message' => '2FA not enabled'], 422);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->input('code'));
        if (!$valid) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $codes = $user->generateRecoveryCodes(10);
        $user->setRecoveryCodesEncrypted($codes);
        $user->save();

        return response()->json(['recovery_codes' => $codes]);
    }

    public function generateRandomKey()
    {
        return response()->json([
            'key' => \Illuminate\Support\Str::random(32)
        ]);
    }
}
