<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TvSeriesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // Store descriptions count before unsetting relation
        $descriptionsCount = $this->resource->descriptions_count
            ?? ($this->resource->relationLoaded('descriptions') ? $this->resource->descriptions->count() : 0);

        $tvSeries = $this->resource;

        $data = [
            'id' => $tvSeries->id,
            'title' => $tvSeries->title,
            'slug' => $tvSeries->slug,
            'first_air_date' => $tvSeries->first_air_date?->format('Y-m-d'),
            'last_air_date' => $tvSeries->last_air_date?->format('Y-m-d'),
            'number_of_seasons' => $tvSeries->number_of_seasons,
            'number_of_episodes' => $tvSeries->number_of_episodes,
            'genres' => $tvSeries->genres,
            'default_description_id' => $tvSeries->default_description_id,
            'created_at' => $tvSeries->created_at?->toISOString(),
            'updated_at' => $tvSeries->updated_at?->toISOString(),
            'descriptions_count' => $descriptionsCount,
        ];

        // Add relations that are safe to serialize
        if ($tvSeries->relationLoaded('defaultDescription') && $tvSeries->defaultDescription) {
            $data['default_description'] = [
                'id' => $tvSeries->defaultDescription->id,
                'locale' => $tvSeries->defaultDescription->locale->value,
                'text' => $tvSeries->defaultDescription->text,
                'context_tag' => $tvSeries->defaultDescription->context_tag->value,
                'origin' => $tvSeries->defaultDescription->origin->value,
                'ai_model' => $tvSeries->defaultDescription->ai_model,
            ];
        }

        if ($tvSeries->relationLoaded('people')) {
            $data['people'] = $tvSeries->people->map(function ($person) {
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
