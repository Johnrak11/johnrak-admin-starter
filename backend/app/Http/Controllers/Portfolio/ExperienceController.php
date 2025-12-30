<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioExperience;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioExperience::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_current' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $item = PortfolioExperience::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, PortfolioExperience $experience)
    {
        if ($experience->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'company' => ['required', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_current' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $experience->fill($validated)->save();

        return response()->json(['item' => $experience]);
    }

    public function destroy(Request $request, PortfolioExperience $experience)
    {
        if ($experience->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $experience->delete();

        return response()->json(['ok' => true]);
    }
}
