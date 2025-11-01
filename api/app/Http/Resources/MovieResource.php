<?php

namespace App\Http\Resources;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Movie
 */
class MovieResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get director from people relationship (role=DIRECTOR)
        $director = null;
        if ($this->resource instanceof Movie && $this->resource->relationLoaded('people')) {
            /** @var \App\Models\Person|null $directorPerson */
            $directorPerson = $this->resource->people->where('pivot.role', 'DIRECTOR')->first();
            $director = $directorPerson;
        }

        // Get main actors (top 5 by billing_order)
        // Only return actors if people relationship is loaded
        $actors = collect();
        if ($this->resource instanceof Movie && $this->resource->relationLoaded('people')) {
            $actors = $this->resource->people
                ->where('pivot.role', 'ACTOR')
                ->sortBy('pivot.billing_order')
                ->take(5)
                ->values()
                ->map(function ($person) {
                    /** @var \App\Models\Person $person */
                    return [
                        'id' => $person->id,
                        'name' => $person->name,
                        'slug' => $person->slug,
                        'character_name' => $person->pivot->character_name ?? null,
                        'billing_order' => $person->pivot->billing_order ?? null,
                    ];
                });
        }

        /** @var Movie $movie */
        $movie = $this->resource;

        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'release_year' => $movie->release_year,
            'director' => $director instanceof \App\Models\Person ? [
                'id' => $director->id,
                'name' => $director->name,
                'slug' => $director->slug,
            ] : null,
            'genres' => $movie->genres,
            'default_description_id' => $movie->default_description_id,
            'created_at' => $movie->created_at,
            'updated_at' => $movie->updated_at,
            'default_description' => $this->whenLoaded('defaultDescription', function () use ($movie) {
                return $movie->defaultDescription;
            }),
            'people' => $actors, // Only main actors (top 5), rest can be generated on demand via AI
            '_links' => $this->when($this->additional['_links'] ?? null, function () {
                return $this->additional['_links'];
            }),
        ];
    }
}
