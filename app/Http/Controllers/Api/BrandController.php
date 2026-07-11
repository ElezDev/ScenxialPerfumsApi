<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Brand::query();

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return BrandResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:brands,slug'],
            'logo' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] ??= Str::slug($validated['name']);

        $brand = Brand::create($validated);

        return response()->json([
            'message' => 'Marca creada.',
            'data' => new BrandResource($brand),
        ], 201);
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:brands,slug,'.$brand->id],
            'logo' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $brand->update($validated);

        return response()->json([
            'message' => 'Marca actualizada.',
            'data' => new BrandResource($brand->fresh()),
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(['message' => 'Marca eliminada.']);
    }
}
