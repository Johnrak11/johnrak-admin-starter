<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ApiClient; // Added for DB lookup

class VerifyExternalApiKey
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Check DB for Key
        // Support X-SnapOrder-Key or Generic X-Api-Key
        $requestKey = $request->header('X-SnapOrder-Key') ?? $request->header('X-Api-Key');

        if (!$requestKey) {
            return response()->json(['message' => 'Missing API Key'], 401);
        }

        // Hash the incoming key to match DB storage
        $hash = hash('sha256', $requestKey);

        $client = ApiClient::where('secret_hash', $hash)->first();

        if (!$client || !$client->is_active) {
            Log::warning("External API Blocked: Invalid or Inactive Key", ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized Key'], 401);
        }

        // Update Last Used
        $client->update(['last_used_at' => now()]);

        // Origin check is handled by Laravel CORS configuration.
        // We rely on API Key authentication for primary security.

        return $next($request);
    }
}
