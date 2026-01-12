<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BakongService
{
    // Default to Production Environment
    // SIT: https://sit-api-bakong.nbc.gov.kh/v1
    // PROD: https://api-bakong.nbc.gov.kh/v1
    private $baseUrl = 'https://api-bakong.nbc.gov.kh/v1';

    public function __construct()
    {
        // Allow override via config if set
        $this->baseUrl = config('services.bakong.base_url', 'https://api-bakong.nbc.gov.kh/v1');
    }

    /**
     * Check transaction status by MD5 hash
     * 
     * @param string $token Bearer token
     * @param string $md5 The MD5 hash of the KHQR string
     * @return array|null Response data or null on failure
     */
    public function checkTransactionStatus(string $token, string $md5)
    {
        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/check_transaction_by_md5", [
                    'md5' => $md5
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Bakong Check Status Failed', [
                'md5' => $md5,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Bakong Check Status Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }


    /**
     * Renew Token
     * Endpoint: {{baseUrl}}/v1/renew_token
     */
    public function renewToken(string $email)
    {
        try {
            $response = Http::post("{$this->baseUrl}/renew_token", [
                'email' => $email
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Bakong Renew Token Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Bakong Renew Token Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
