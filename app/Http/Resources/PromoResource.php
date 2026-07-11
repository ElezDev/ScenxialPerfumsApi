<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'code' => $this->code,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'image' => $this->image,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'min_purchase' => $this->min_purchase !== null ? (float) $this->min_purchase : null,
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
