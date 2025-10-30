<?php

namespace App\Services;

interface AiServiceInterface
{
    public function queueMovieGeneration(string $slug, string $jobId): void;

    public function queuePersonGeneration(string $slug, string $jobId): void;
}


