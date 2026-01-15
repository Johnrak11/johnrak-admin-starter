<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    public function index()
    {
        return ApiClient::orderByDesc('created_at')->get(['id', 'name', 'is_active', 'last_used_at', 'created_at']);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $key = Str::random(32);
        $hash = hash('sha256', $key, true); // Binary hash to match EncryptionService logic if needed, or hex?
        // Wait, EncryptionService uses binary hash of the secret.
        // Middleware will receive Raw Key.
        // We should store the hash of the Raw Key.

        // Let's use Hex for database storage to be safe and readable.
        $storedHash = hash('sha256', $key);

        $client = ApiClient::create([
            'name' => $request->name,
            'secret_hash' => $storedHash,
            'is_active' => true,
        ]);

        return response()->json([
            'client' => $client,
            'plain_text_key' => $key // One time show
        ]);
    }

    public function destroy(ApiClient $client)
    {
        $client->delete();
        return response()->noContent();
    }
}
