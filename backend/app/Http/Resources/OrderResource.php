<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'status' => $this->status,
            'total_price' => number_format($this->total_price, 2),
            
            // User who made the order
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            // Items in the order
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items()->count(),
            
            // Timestamps
            'ordered_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
