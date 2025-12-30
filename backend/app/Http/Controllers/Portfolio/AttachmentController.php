<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\PortfolioAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    public function index(Request $request)
    {
        $items = PortfolioAttachment::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'in:cv,certificate,other'],
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $validated['file'];
        $bytes = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $bytes);

        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $safeName = Str::uuid()->toString().'.'.$ext;
        $path = 'private/'.$request->user()->id.'/'.$safeName;

        Storage::disk('local')->put($path, $bytes);

        $att = PortfolioAttachment::create([
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'title' => $validated['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'storage_path' => $path,
            'sha256' => $sha256,
        ]);

        return response()->json(['item' => $att], 201);
    }

    public function download(Request $request, PortfolioAttachment $attachment)
    {
        if ($attachment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $path = $attachment->storage_path;
        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File missing'], 404);
        }

        return Storage::disk('local')->download($path, $attachment->original_name);
    }

    public function destroy(Request $request, PortfolioAttachment $attachment)
    {
        if ($attachment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        try {
            Storage::disk('local')->delete($attachment->storage_path);
        } catch (\Throwable $e) {
        }

        $attachment->delete();

        return response()->json(['ok' => true]);
    }
}
