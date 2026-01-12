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
    private $proxy = null;

    public function __construct()
    {
        // Allow override via config if set
        $this->baseUrl = config('services.bakong.base_url', 'https://api-bakong.nbc.gov.kh/v1');

        // Proxy support for bypassing IP blocks
        // Format: http://user:pass@1.2.3.4:8080 or just http://1.2.3.4:8080
        $this->proxy = env('BAKONG_PROXY_URL', null);
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
            // Build options
            $options = [
                'verify' => true,
                'version' => 2.0,
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]
            ];

            if ($this->proxy) {
                $options['proxy'] = $this->proxy;
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
                'Referer' => 'https://bakong.nbc.gov.kh/',
                'Origin' => 'https://bakong.nbc.gov.kh',
            ])->withOptions($options)
                ->withToken($token)
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
            return [
                'responseCode' => -1,
                'responseMessage' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Bakong Check Status Exception', ['error' => $e->getMessage()]);
            return [
                'responseCode' => -1,
                'responseMessage' => 'Exception: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Renew Token
     * Endpoint: {{baseUrl}}/v1/renew_token
     */
    public function renewToken(string $email)
    {
        try {
            $options = [
                'verify' => true,
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]
            ];

            if ($this->proxy) {
                $options['proxy'] = $this->proxy;
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
                'Referer' => 'https://bakong.nbc.gov.kh/',
                'Origin' => 'https://bakong.nbc.gov.kh',
            ])->withOptions($options)
                ->post("{$this->baseUrl}/renew_token", [
                    'email' => $email
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Bakong Renew Token Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'responseCode' => -1,
                'responseMessage' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Bakong Renew Token Exception', ['error' => $e->getMessage()]);
            return [
                'responseCode' => -1,
                'responseMessage' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
