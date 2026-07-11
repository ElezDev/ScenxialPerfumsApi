<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoResource;
use App\Models\Promo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Promo::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return PromoResource::collection(
            $query->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:50', 'unique:promos,code'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json([
                'message' => 'El descuento porcentual no puede superar el 100%.',
            ], 422);
        }

        $promo = Promo::create($validated);

        return response()->json([
            'message' => 'Promoción creada.',
            'data' => new PromoResource($promo),
        ], 201);
    }

    public function show(Promo $promo): PromoResource
    {
        return new PromoResource($promo);
    }

    public function update(Request $request, Promo $promo): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:50', 'unique:promos,code,'.$promo->id],
            'discount_type' => ['sometimes', 'in:percentage,fixed'],
            'discount_value' => ['sometimes', 'numeric', 'min:0'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $discountType = $validated['discount_type'] ?? $promo->discount_type;
        $discountValue = $validated['discount_value'] ?? $promo->discount_value;

        if ($discountType === 'percentage' && $discountValue > 100) {
            return response()->json([
                'message' => 'El descuento porcentual no puede superar el 100%.',
            ], 422);
        }

        $promo->update($validated);

        return response()->json([
            'message' => 'Promoción actualizada.',
            'data' => new PromoResource($promo->fresh()),
        ]);
    }

    public function destroy(Promo $promo): JsonResponse
    {
        $promo->delete();

        return response()->json(['message' => 'Promoción eliminada.']);
    }
}
