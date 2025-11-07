<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['movies'] = $this->when(
            $this->resource->relationLoaded('movies'),
            fn () => $this->resource->movies->map(fn ($movie) => [
                'id' => $movie->id,
                'slug' => $movie->slug,
                'title' => $movie->title,
                'role' => $movie->pivot->role ?? null,
                'character_name' => $movie->pivot->character_name ?? null,
            ])->toArray()
        );

        $data['bios'] = $this->when(
            $this->resource->relationLoaded('bios'),
            fn () => $this->resource->bios->map(fn ($bio) => $bio->toArray())->toArray()
        );

        if ($links = $this->additional['links'] ?? $this->additional['_links'] ?? null) {
            $data['_links'] = $links;
        }

        if (array_key_exists('_meta', $this->additional)) {
            $data['_meta'] = $this->additional['_meta'];
        }

        return $data;
    }
}
