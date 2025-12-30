<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioCertification;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioCertification::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'issue_date' => ['nullable', 'date'],
            'expire_date' => ['nullable', 'date'],
            'credential_id' => ['nullable', 'string', 'max:200'],
            'credential_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $item = PortfolioCertification::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, PortfolioCertification $certification)
    {
        if ($certification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'issue_date' => ['nullable', 'date'],
            'expire_date' => ['nullable', 'date'],
            'credential_id' => ['nullable', 'string', 'max:200'],
            'credential_url' => ['nullable', 'url', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $certification->fill($validated)->save();

        return response()->json(['item' => $certification]);
    }

    public function destroy(Request $request, PortfolioCertification $certification)
    {
        if ($certification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $certification->delete();

        return response()->json(['ok' => true]);
    }
}
