<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Services\LinkedInImportService;
use Illuminate\Http\Request;

class LinkedInImportController extends Controller
{
    public function importJson(Request $request, LinkedInImportService $service)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $result = $service->import($request->user()->id, $validated['data']);

        return response()->json(['ok' => true, 'imported' => $result]);
    }
}
