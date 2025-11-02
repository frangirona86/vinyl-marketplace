<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->qty,
            'price_each' => number_format($this->price_each, 2),
            'subtotal' => number_format($this->subtotal, 2),
            
            // Variant of the item
            'variant' => new VariantResource($this->whenLoaded('variant')),
        ];;
    }
}
