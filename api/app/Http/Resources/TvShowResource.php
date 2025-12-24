<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TvShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Store descriptions count before unsetting relation
        $descriptionsCount = $this->resource->descriptions_count
            ?? ($this->resource->relationLoaded('descriptions') ? $this->resource->descriptions->count() : 0);

        $tvShow = $this->resource;

        $data = [
            'id' => $tvShow->id,
            'title' => $tvShow->title,
            'slug' => $tvShow->slug,
            'first_air_date' => $tvShow->first_air_date?->format('Y-m-d'),
            'last_air_date' => $tvShow->last_air_date?->format('Y-m-d'),
            'number_of_seasons' => $tvShow->number_of_seasons,
            'number_of_episodes' => $tvShow->number_of_episodes,
            'genres' => $tvShow->genres,
            'show_type' => $tvShow->show_type,
            'default_description_id' => $tvShow->default_description_id,
            'created_at' => $tvShow->created_at?->toISOString(),
            'updated_at' => $tvShow->updated_at?->toISOString(),
            'descriptions_count' => $descriptionsCount,
        ];

        // Add relations that are safe to serialize
        if ($tvShow->relationLoaded('defaultDescription') && $tvShow->defaultDescription) {
            $data['default_description'] = [
                'id' => $tvShow->defaultDescription->id,
                'locale' => $tvShow->defaultDescription->locale->value,
                'text' => $tvShow->defaultDescription->text,
                'context_tag' => $tvShow->defaultDescription->context_tag->value,
                'origin' => $tvShow->defaultDescription->origin->value,
                'ai_model' => $tvShow->defaultDescription->ai_model,
            ];
        }

        if ($tvShow->relationLoaded('people')) {
            $data['people'] = $tvShow->people->map(function ($person) {
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
