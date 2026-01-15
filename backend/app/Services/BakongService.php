<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BakongService
{
    // Default to Production Environment
    // SIT: https://sit-api-bakong.nbc.gov.kh/v1
    // PROD: https://api-bakong.nbc.gov.kh/v1
    private $baseUrl = '';
    private $proxy = null;
    private $accessToken = '';
    private $version = 'v1';

    // Tunnel Config
    private $useTunnel = false;
    private $tunnelIp = '';
    private $tunnelPort = 9000;
    private $targetHost = 'api-bakong.nbc.gov.kh';

    public function __construct()
    {
        // Allow override via config if set
        $this->baseUrl = rtrim(config('services.bakong.base_url', env('BAKONG_BASE_URL', 'https://api-bakong.nbc.gov.kh')), '/') . '/' . $this->version;
        $this->accessToken = config('services.bakong.access_token', '');

        // Extract host for tunnel connect-to usage
        $parsedUrl = parse_url($this->baseUrl);
        $this->targetHost = $parsedUrl['host'] ?? 'api-bakong.nbc.gov.kh';

        // Proxy support
        $this->proxy = config('services.bakong.proxy_url');

        // Tunnel support
        // Explicitly check .env to avoid config cache issues during dev
        $this->useTunnel = filter_var(env('BAKONG_TUNNEL_ENABLED', config('services.bakong.tunnel_enabled', false)), FILTER_VALIDATE_BOOLEAN);
        $this->tunnelIp = env('BAKONG_TUNNEL_IP', config('services.bakong.tunnel_ip', '172.19.0.1'));
        $this->tunnelPort = env('BAKONG_TUNNEL_PORT', config('services.bakong.tunnel_port', 9000));
    }

    /**
     * Check transaction status by MD5 hash
     */
    public function checkTransactionStatus(?string $token, string $md5)
    {
        return $this->sendRequest('post', '/check_transaction_by_md5', [
            'md5' => $md5
        ], $token);
    }

    /**
     * Check transaction status by MD5 List
     * Endpoint: {{baseUrl}}/v1/check_transaction_by_md5_list
     */
    public function checkTransactionStatusList(array $md5List)
    {
        return $this->sendRequest('post', '/check_transaction_by_md5_list', $md5List);
    }

    /**
     * Renew Token
     */
    public function renewToken(string $email)
    {
        // Renew token usually doesn't need auth token, or needs specific handling?
        // Documentation says POST /v1/renew_token with email body. No Bearer needed usually.
        return $this->sendRequest('post', '/renew_token', [
            'email' => $email
        ]);
    }

    /**
     * Generate Deep Link (Short Link)
     */
    public function generateDeeplink(string $qr, ?array $sourceInfo = null)
    {
        // Default Source Info if not provided
        if (empty($sourceInfo)) {
            $sourceInfo = [
                'appIconUrl' => 'https://bakong.nbc.gov.kh/images/logo.svg',
                'appName' => 'Bakong Payment',
                'appDeepLinkCallback' => 'https://bakong.nbc.gov.kh/'
            ];
        }

        return $this->sendRequest('post', '/generate_deeplink_by_qr', [
            'qr' => $qr,
            'sourceInfo' => $sourceInfo
        ]);
    }

    /**
     * Centralized Request Handler
     * Handles Tunneling, Proxy, Headers, and Error Logging
     */
    private function sendRequest(string $method, string $endpoint, array $data = [], ?string $token = null)
    {
        try {
            // 1. Prepare Configuration
            $url = $this->baseUrl . $endpoint;
            $token = $token ?: $this->accessToken;

            $headers = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
                // Important for WAF/CloudFront to validate host match despite tunnel
                'Referer' => "https://{$this->targetHost}/",
                'Origin' => "https://{$this->targetHost}",
            ];

            $options = [
                'verify' => !$this->useTunnel, // Secure by default (True), unless Tunnel is ON (False)
                'http_errors' => false, // We handle errors manually
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]
            ];

            // 2. Decide Tunnel vs Proxy
            if ($this->useTunnel) {
                // FORCE Connection via Tunnel IP
                $options['curl'][CURLOPT_CONNECT_TO] = ["{$this->targetHost}:443:{$this->tunnelIp}:{$this->tunnelPort}"];

                // IMPORTANT: When tunneling, we often hit a raw IP or internal gateway.
                // We MUST trust the host header works, but if SSL fails, verify=false handles it.
            } elseif ($this->proxy) {
                // Use Standard HTTP Proxy
                $options['proxy'] = $this->proxy;
            }

            // 3. Execute Request
            $response = Http::withHeaders($headers)
                        ->withOptions($options)
                        ->withToken($token)
                ->$method($url, $data);

            // 4. Handle Response
            if ($response->successful()) {
                return $response->json();
            }

            // 5. Log & Return Error
            Log::error("Bakong {$endpoint} Failed", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'responseCode' => -1,
                'responseMessage' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("Bakong {$endpoint} Exception", ['error' => $e->getMessage()]);
            return [
                'responseCode' => -1,
                'responseMessage' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
