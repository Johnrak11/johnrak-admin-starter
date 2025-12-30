<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioProject;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioProject::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tech_stack' => ['nullable', 'string', 'max:1000'],
            'repo_url' => ['nullable', 'url', 'max:500'],
            'live_url' => ['nullable', 'url', 'max:500'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $item = PortfolioProject::create($validated);

        return response()->json(['item' => $item], 201);
    }

    public function update(Request $request, PortfolioProject $project)
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tech_stack' => ['nullable', 'string', 'max:1000'],
            'repo_url' => ['nullable', 'url', 'max:500'],
            'live_url' => ['nullable', 'url', 'max:500'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $project->fill($validated)->save();

        return response()->json(['item' => $project]);
    }

    public function destroy(Request $request, PortfolioProject $project)
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $project->delete();

        return response()->json(['ok' => true]);
    }
}
