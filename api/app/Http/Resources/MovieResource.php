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
        $data = parent::toArray($request);

        $descriptionsCount = $this->resource->descriptions_count
            ?? ($this->resource->relationLoaded('descriptions') ? $this->resource->descriptions->count() : 0);

        $data['descriptions_count'] = $descriptionsCount;

        if ($links = $this->additional['links'] ?? $this->additional['_links'] ?? null) {
            $data['_links'] = $links;
        }

        if (array_key_exists('_meta', $this->additional)) {
            $data['_meta'] = $this->additional['_meta'];
        }

        return $data;
    }
}
