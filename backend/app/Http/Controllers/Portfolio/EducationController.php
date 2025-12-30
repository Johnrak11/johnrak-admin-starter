<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioEducation;
use Illuminate\Http\Request;

class EducationController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioEducation::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school' => ['required', 'string', 'max:200'],
            'degree' => ['nullable', 'string', 'max:200'],
            'field_of_study' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $item = PortfolioEducation::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, PortfolioEducation $education)
    {
        if ($education->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'school' => ['required', 'string', 'max:200'],
            'degree' => ['nullable', 'string', 'max:200'],
            'field_of_study' => ['nullable', 'string', 'max:200'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $education->fill($validated)->save();

        return response()->json(['item' => $education]);
    }

    public function destroy(Request $request, PortfolioEducation $education)
    {
        if ($education->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $education->delete();

        return response()->json(['ok' => true]);
    }
}
