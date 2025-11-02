<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
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
            'condition' => $this->condition,
            'color' => $this->color,
            'price' => number_format($this->price, 2),
            'stock' => $this->stock,
            'photos' => $this->photos,
            'available' => $this->stock > 0,

            // include record and artist data if they are loaded
            'record' => new RecordResource($this->whenLoaded('record')),
            'artist' => new ArtistResource($this->whenLoaded('artist')),
        ];
    }
}
