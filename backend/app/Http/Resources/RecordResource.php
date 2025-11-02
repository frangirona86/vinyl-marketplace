<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecordResource extends JsonResource
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
            'title' => $this->title,
            'artist' => $this->artist,
            'genre' => $this->genre,
            'year' => $this->year,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'artist' => new ArtistResource($this->whenLoaded('artist')),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            // Other data
            'variants_count' => $this->whenLoaded('variants', function() {
                return $this->variants->count();
            }, 0),
            'in_stock' => $this->whenLoaded('variants', function() {
                return $this->variants->where('stock', '>', 0)->isNotEmpty();
            }, false),

            // timestamps
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
