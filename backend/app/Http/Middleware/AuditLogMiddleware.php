<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if ($user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'status' => (int) $response->getStatusCode(),
                ]);
            }
        } catch (\Throwable $e) {
            // no-op
        }

        return $response;
    }
}
