<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
            'folder' => ['nullable', 'string', 'max:50'],
        ]);

        $folder = $request->input('folder', 'uploads');
        $folder = Str::slug($folder) ?: 'uploads';

        $path = $request->file('file')->store($folder, 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'message' => 'Archivo subido.',
            'url' => $url,
            'path' => $path,
        ], 201);
    }
}
