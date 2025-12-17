<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Http\JsonResponse;

/**
 * Response DTO for successful movie retrieval.
 */
class MovieResponse
{
    public function __construct(
        private readonly Movie $movie,
        private readonly string $slug,
        private readonly ?MovieDescription $selectedDescription = null
    ) {}

    public function toJsonResponse(int $statusCode = 200): JsonResponse
    {
        return response()->json($this->toArray(), $statusCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->transformMovie($this->movie, $this->slug);

        if ($this->selectedDescription !== null) {
            $data['selected_description'] = $this->selectedDescription->toArray();
        }

        return $data;
    }

    /**
     * Transform movie to array format.
     * This method should delegate to existing transformMovie logic.
     * For now, it's a placeholder - will be injected via dependency.
     *
     * @return array<string, mixed>
     */
    private function transformMovie(Movie $movie, string $slug): array
    {
        // This will be injected via constructor or method parameter
        // For now, return basic structure
        return [
            'id' => $movie->id,
            'slug' => $movie->slug,
            'title' => $movie->title,
            'release_year' => $movie->release_year,
            'director' => $movie->director,
        ];
    }
}
