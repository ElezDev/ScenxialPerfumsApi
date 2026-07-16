<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DecantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'ml' => $this->ml,
            'price' => $this->price,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
