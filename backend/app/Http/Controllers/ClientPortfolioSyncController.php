<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortfolioExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientPortfolioSyncController extends Controller
{
    public function sync(Request $request, PortfolioExportService $service)
    {
        // 1. Security Check: Validate Origin
        // Allow both local dev and production URLs
        $allowedOrigins = [
            rtrim(env('PORTFOLIO_URL', 'http://localhost:5173'), '/'),
            'https://portfolio.johnrak.online'
        ];
        
        $requestOrigin = rtrim($request->header('Origin'), '/');

        // Note: For now we are lenient and relying mostly on the Shared Secret Key.
        // If strictly enforcing origin, uncomment the check below and ensure config matches exactly.
        
        /*
        if (!in_array($requestOrigin, $allowedOrigins) && $requestOrigin !== '*') {
             Log::warning("Portfolio Sync Blocked: Invalid Origin", ['expected' => $allowedOrigins, 'got' => $requestOrigin]);
             // return response()->json(['message' => 'Unauthorized Origin'], 403);
        }
        */

        // 2. Security Check: Validate Shared Secret Key
        $sharedSecret = env('PORTFOLIO_SHARED_SECRET');
        $requestKey = $request->header('X-Portfolio-Key');

        if (!$sharedSecret || $requestKey !== $sharedSecret) {
            Log::warning("Portfolio Sync Blocked: Invalid Key");
            return response()->json(['message' => 'Unauthorized Key'], 401);
        }

        // 3. Fetch Data (Assuming Single User/Owner system for now, typically ID 1)
        $user = User::first();
        if (!$user) {
            return response()->json(['message' => 'No user found'], 404);
        }

        $data = $service->build($user->id);
        $jsonData = json_encode($data);

        // 4. Encrypt Data
        $encrypted = $this->encryptData($jsonData, $sharedSecret);

        return response()->json($encrypted);
    }

    private function encryptData(string $data, string $key): array
    {
        $cipher = "aes-256-cbc";
        // Ensure key is 32 bytes (SHA256 hash of the secret ensures this)
        $keyHash = hash('sha256', $key, true);

        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $encrypted = openssl_encrypt($data, $cipher, $keyHash, 0, $iv);

        return [
            'payload' => $encrypted,
            'iv' => base64_encode($iv),
        ];
    }
}
