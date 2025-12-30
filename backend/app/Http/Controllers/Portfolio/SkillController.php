<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioSkill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioSkill::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $item = PortfolioSkill::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, PortfolioSkill $skill)
    {
        if ($skill->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $skill->fill($validated)->save();

        return response()->json(['item' => $skill]);
    }

    public function destroy(Request $request, PortfolioSkill $skill)
    {
        if ($skill->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $skill->delete();

        return response()->json(['ok' => true]);
    }
}
