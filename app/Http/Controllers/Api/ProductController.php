<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Decant;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['category', 'brand', 'images', 'decants']);

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $request->brand));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) $request->get('per_page', 12), 50);

        return ProductResource::collection($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'attributes' => ['nullable', 'array'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*.path' => ['required', 'string', 'max:500'],
            'images.*.is_primary' => ['boolean'],
            'images.*.sort_order' => ['integer', 'min:0'],
            'decants' => ['nullable', 'array', 'max:10'],
            'decants.*.ml' => ['required', 'integer', 'min:1'],
            'decants.*.price' => ['required', 'numeric', 'min:0'],
            'decants.*.stock' => ['nullable', 'integer', 'min:0'],
            'decants.*.is_active' => ['boolean'],
            'decants.*.sort_order' => ['integer', 'min:0'],
        ]);

        $validated['slug'] ??= Str::slug($validated['name']);
        $images = $validated['images'] ?? null;
        $decants = $validated['decants'] ?? null;
        unset($validated['images'], $validated['decants']);

        $product = Product::create($validated);

        if ($images) {
            $this->syncImages($product, $images);
        }

        if ($decants) {
            $this->syncDecants($product, $decants);
        }
        $product->load(['category', 'brand', 'images', 'decants']);

        return response()->json([
            'message' => 'Producto creado.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'brand', 'images', 'decants']);

        return new ProductResource($product);
    }

    public function showBySlug(string $slug): ProductResource|JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['category', 'brand', 'images', 'decants'])
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:products,slug,'.$product->id],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'sku' => ['sometimes', 'string', 'max:100', 'unique:products,sku,'.$product->id],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'attributes' => ['nullable', 'array'],
            'images' => ['nullable', 'array', 'max:6'],
            'images.*.path' => ['required', 'string', 'max:500'],
            'images.*.is_primary' => ['boolean'],
            'images.*.sort_order' => ['integer', 'min:0'],
            'decants' => ['nullable', 'array', 'max:10'],
            'decants.*.ml' => ['required', 'integer', 'min:1'],
            'decants.*.price' => ['required', 'numeric', 'min:0'],
            'decants.*.stock' => ['nullable', 'integer', 'min:0'],
            'decants.*.is_active' => ['boolean'],
            'decants.*.sort_order' => ['integer', 'min:0'],
        ]);

        $images = $validated['images'] ?? null;
        $decants = $validated['decants'] ?? null;
        unset($validated['images'], $validated['decants']);

        $product->update($validated);

        if ($images !== null) {
            $this->syncImages($product, $images);
        }

        if ($decants !== null) {
            $this->syncDecants($product, $decants);
        }
        $product->load(['category', 'brand', 'images', 'decants']);

        return response()->json([
            'message' => 'Producto actualizado.',
            'data' => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado.']);
    }

    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach ($images as $index => $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $image['path'],
                'is_primary' => $image['is_primary'] ?? ($index === 0),
                'sort_order' => $image['sort_order'] ?? $index,
            ]);
        }
    }

    private function syncDecants(Product $product, array $decants): void
    {
        $product->decants()->delete();

        foreach ($decants as $index => $decant) {
            Decant::create([
                'product_id' => $product->id,
                'ml' => $decant['ml'],
                'price' => $decant['price'],
                'stock' => $decant['stock'] ?? 0,
                'is_active' => $decant['is_active'] ?? true,
                'sort_order' => $decant['sort_order'] ?? $index,
            ]);
        }
    }
}
