<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Store descriptions count before unsetting relation
        $descriptionsCount = $this->resource->descriptions_count
            ?? ($this->resource->relationLoaded('descriptions') ? $this->resource->descriptions->count() : 0);

        // Exclude 'descriptions' relation to avoid serialization issues with invalid context_tag values
        // We use 'selected_description' instead in MovieResponseFormatter
        // Manually build array to exclude descriptions relation
        $movie = $this->resource;

        $data = [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'release_year' => $movie->release_year,
            'director' => $movie->director,
            'genres' => $movie->genres,
            'default_description_id' => $movie->default_description_id,
            'created_at' => $movie->created_at?->toISOString(),
            'updated_at' => $movie->updated_at?->toISOString(),
            'descriptions_count' => $descriptionsCount,
        ];

        // Add relations that are safe to serialize (excluding descriptions)
        if ($movie->relationLoaded('defaultDescription') && $movie->defaultDescription) {
            $data['default_description'] = [
                'id' => $movie->defaultDescription->id,
                'locale' => $movie->defaultDescription->locale->value,
                'text' => $movie->defaultDescription->text,
                'context_tag' => $movie->defaultDescription->context_tag->value,
                'origin' => $movie->defaultDescription->origin->value,
                'ai_model' => $movie->defaultDescription->ai_model,
            ];
        }

        if ($movie->relationLoaded('people')) {
            $data['people'] = $movie->people->map(function ($person) {
                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'slug' => $person->slug,
                    'role' => $person->pivot->role ?? null,
                    'character_name' => $person->pivot->character_name ?? null,
                ];
            })->values()->toArray();
        }

        if ($links = $this->additional['links'] ?? $this->additional['_links'] ?? null) {
            $data['_links'] = $links;
        }

        if (array_key_exists('_meta', $this->additional)) {
            $data['_meta'] = $this->additional['_meta'];
        }

        return $data;
    }
}
