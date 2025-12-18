<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\JobErrorFormatter;
use App\Services\JobStatusService;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * Real Generate Movie Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGenerateMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingMovieId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}

    /**
     * Execute the job.
     * Note: OpenAiClientInterface is injected via method injection.
     * Constructor injection is not possible because Jobs are serialized to queue.
     */
    public function handle(OpenAiClientInterface $openAiClient): void
    {
        Log::info('RealGenerateMovieJob started', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'attempt' => $this->attempts(),
            'existing_movie_id' => $this->existingMovieId,
            'baseline_description_id' => $this->baselineDescriptionId,
            'locale' => $this->locale,
            'context_tag' => $this->contextTag,
            'tmdb_data' => $this->tmdbData !== null,
            'pid' => getmypid(),
        ]);
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        /** @var MovieRepository $movieRepository */
        $movieRepository = app(MovieRepository::class);

        try {
            $existing = $movieRepository->findBySlugForJob($this->slug, $this->existingMovieId);

            if ($existing) {
                $this->refreshExistingMovie($existing, $openAiClient);
                Log::info('RealGenerateMovieJob refreshed existing movie', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'movie_id' => $existing->id,
                ]);

                return;
            }

            try {
                $result = $this->createMovieRecord($openAiClient);

                // If result is null, suggested slugs were found and error was already handled
                if ($result === null) {
                    return;
                }

                [$movie, $description, $localeValue, $contextTag] = $result;
                $this->promoteDefaultIfEligible($movie, $description);
                $this->invalidateMovieCaches($movie);
                $this->updateCache('DONE', $movie->id, $movie->slug, $description->id, $localeValue, $contextTag);
                Log::info('RealGenerateMovieJob created new movie', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'movie_id' => $movie->id,
                    'description_id' => $description->id,
                    'locale' => $localeValue,
                    'context_tag' => $contextTag,
                    'pid' => getmypid(),
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueMovieSlugViolation($exception)) {
                    $existingAfterViolation = $movieRepository->findBySlugForJob($this->slug, $this->existingMovieId);
                    if ($existingAfterViolation) {
                        Log::info('RealGenerateMovieJob detected concurrent creation - using existing movie', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'movie_id' => $existingAfterViolation->id,
                            'pid' => getmypid(),
                        ]);
                        $this->markDoneUsingExisting($existingAfterViolation);

                        return;
                    }

                    Log::warning('RealGenerateMovieJob unique slug violation without movie record', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'pid' => getmypid(),
                        'error' => $exception->getMessage(),
                    ]);
                }

                throw $exception;
            }
        } catch (\Throwable $e) {
            Log::error('RealGenerateMovieJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'MOVIE');
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        } finally {
            $jobStatusService->releaseGenerationSlot('MOVIE', $this->slug, $this->locale, $this->contextTag);
        }
    }

    /**
     * Configure retry backoff for rate-limited responses (e.g. OpenAI trial limits: 3 RPM).
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        if (! config('services.openai.backoff.enabled', true)) {
            return [];
        }

        $intervals = config('services.openai.backoff.intervals', []);

        return ! empty($intervals) ? $intervals : [20, 60, 180];
    }

    private function refreshExistingMovie(Movie $movie, OpenAiClientInterface $openAiClient): void
    {
        $movie->loadMissing('descriptions');
        $aiResponse = $openAiClient->generateMovie($this->slug, $this->tmdbData);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                Log::warning('Movie not found by AI during refresh', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'movie_id' => $movie->id,
                ]);

                throw new \RuntimeException(
                    "Movie not found by AI during refresh: '{$this->slug}' (movie_id: {$movie->id}). ".
                    'AI could not generate description for existing movie. '.
                    'This may indicate the movie data is incorrect or the slug format is ambiguous.'
                );
            }

            throw new \RuntimeException('AI API returned error: '.$error);
        }

        // Validate AI response data consistency with slug (during refresh)
        if (Feature::active('hallucination_guard')) {
            $validator = app(\App\Services\AiDataValidator::class);
            $validation = $validator->validateMovieData($aiResponse, $this->slug);

            if (! $validation['valid']) {
                Log::warning('AI data validation failed for movie during refresh', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'movie_id' => $movie->id,
                    'errors' => $validation['errors'],
                    'similarity' => $validation['similarity'],
                ]);

                throw new \RuntimeException(
                    'AI data validation failed: '.implode(', ', $validation['errors'])
                );
            }
        }

        $descriptionText = $aiResponse['description'] ?? null;

        // Validate description for refresh
        if (! is_string($descriptionText) || trim($descriptionText) === '') {
            Log::warning('AI response missing description during refresh', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'movie_id' => $movie->id,
                'ai_response' => $aiResponse,
            ]);

            throw new \RuntimeException('AI response missing required description field');
        }

        if (strlen(trim($descriptionText)) < 50) {
            Log::warning('AI response description too short during refresh', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'movie_id' => $movie->id,
                'description_length' => strlen(trim($descriptionText)),
                'ai_response' => $aiResponse,
            ]);

            throw new \RuntimeException(
                'AI response description must be at least 50 characters long (current: '.strlen(trim($descriptionText)).')'
            );
        }

        if (stripos($descriptionText, 'Regenerated description for') === 0 || stripos($descriptionText, 'Generated description for') === 0) {
            Log::warning('AI response description is fallback text during refresh', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'movie_id' => $movie->id,
                'ai_response' => $aiResponse,
            ]);

            throw new \RuntimeException('AI response description cannot be fallback text');
        }

        $locale = $this->resolveLocale();
        $baselineLockingActive = $this->baselineLockingEnabled();
        $willUpdateBaseline = $this->shouldUpdateBaseline($movie, $locale);

        if ($baselineLockingActive) {
            Log::info('Baseline locking active for movie generation', [
                'job_id' => $this->jobId,
                'slug' => $this->slug,
                'movie_id' => $movie->id,
                'baseline_description_id' => $this->baselineDescriptionId,
                'will_update_baseline' => $willUpdateBaseline,
                'locale' => $locale->value,
                'context_tag' => $this->contextTag,
            ]);
        }

        $description = $willUpdateBaseline
            ? $this->updateBaselineDescription($movie, $locale, [
                'text' => (string) $descriptionText,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ])
            : $this->persistDescription(
                $movie,
                $locale,
                $this->determineContextTag($movie, $locale),
                [
                    'text' => (string) $descriptionText,
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
                ]
            );

        if ($baselineLockingActive) {
            Log::info('Baseline locking result for movie generation', [
                'job_id' => $this->jobId,
                'slug' => $this->slug,
                'movie_id' => $movie->id,
                'description_id' => $description->id,
                'result' => $willUpdateBaseline ? 'baseline_updated' : 'alternative_appended',
                'locale' => $locale->value,
                'context_tag' => $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag,
            ]);
        }

        $this->promoteDefaultIfEligible($movie, $description);
        $this->invalidateMovieCaches($movie);
        $contextForCache = $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag;
        $this->updateCache('DONE', $movie->id, $movie->slug, $description->id, $locale->value, $contextForCache);
    }

    private function updateCache(
        string $status,
        ?string $id = null,
        ?string $slug = null,
        ?string $descriptionId = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $error = null
    ): void {
        $payload = [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'MOVIE',
            'slug' => $slug ?? $this->slug,
            'requested_slug' => $this->slug,
            'id' => $id,
            'description_id' => $descriptionId,
            'locale' => $locale ?? $this->locale,
            'context_tag' => $contextTag ?? $this->contextTag,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        Cache::put($this->cacheKey(), $payload, now()->addMinutes(15));
    }

    private function nextContextTag(Movie $movie): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $movie->descriptions()->pluck('context_tag')->all()
        );
        $preferredOrder = [
            ContextTag::DEFAULT->value,
            ContextTag::MODERN->value,
            ContextTag::CRITICAL->value,
            ContextTag::HUMOROUS->value,
        ];

        foreach ($preferredOrder as $candidate) {
            if (! in_array($candidate, $existingTags, true)) {
                return $candidate;
            }
        }

        // All standard tags are used - fallback to DEFAULT
        // This prevents creating invalid enum values like "DEFAULT_2"
        // If user needs more descriptions, they should explicitly specify context_tag
        Log::warning('All standard context tags are used, falling back to DEFAULT', [
            'movie_id' => $movie->id,
            'slug' => $movie->slug,
            'existing_tags' => $existingTags,
        ]);

        return ContextTag::DEFAULT->value;
    }

    private function cacheKey(): string
    {
        return 'ai_job:'.$this->jobId;
    }

    private function resolveLocale(): Locale
    {
        if ($this->locale) {
            $normalized = $this->normalizeLocale($this->locale);
            if ($normalized !== null && ($enum = Locale::tryFrom($normalized))) {
                return $enum;
            }
        }

        return Locale::EN_US;
    }

    private function normalizeLocale(string $locale): ?string
    {
        $candidate = str_replace('_', '-', $locale);
        $candidateLower = strtolower($candidate);

        foreach (Locale::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function determineContextTag(Movie $movie, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($movie);
    }

    private function normalizeContextTag(string $contextTag): ?string
    {
        $candidateLower = strtolower($contextTag);

        foreach (ContextTag::cases() as $case) {
            if (strtolower($case->value) === $candidateLower) {
                return $case->value;
            }
        }

        return null;
    }

    private function persistDescription(Movie $movie, Locale $locale, string $contextTag, array $attributes): MovieDescription
    {
        $existing = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return MovieDescription::create(array_merge([
            'movie_id' => $movie->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(Movie $movie, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineDescriptionId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineDescription($movie);

        if (! $baseline instanceof MovieDescription) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function getBaselineDescription(Movie $movie): ?MovieDescription
    {
        $description = $movie->descriptions->firstWhere('id', $this->baselineDescriptionId);

        return $description instanceof MovieDescription
            ? $description
            : MovieDescription::find($this->baselineDescriptionId);
    }

    private function updateBaselineDescription(Movie $movie, Locale $locale, array $attributes): MovieDescription
    {
        $baseline = $this->getBaselineDescription($movie);

        if (! $baseline instanceof MovieDescription) {
            return $this->persistDescription($movie, $locale, $this->determineContextTag($movie, $locale), $attributes);
        }

        // If context_tag is explicitly provided and differs from baseline, create new description instead
        if ($this->contextTag !== null) {
            $normalizedContextTag = $this->normalizeContextTag($this->contextTag);
            if ($normalizedContextTag !== null && $baseline->context_tag->value !== $normalizedContextTag) {
                // User wants a different context_tag, create new description instead of updating baseline
                return $this->persistDescription($movie, $locale, $normalizedContextTag, $attributes);
            }
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    public function failed(\Throwable $exception): void
    {
        /** @var JobStatusService $jobStatusService */
        $jobStatusService = app(JobStatusService::class);
        $jobStatusService->releaseGenerationSlot('MOVIE', $this->slug, $this->locale, $this->contextTag);

        Log::error('RealGenerateMovieJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, 'MOVIE');
        $this->updateCache('FAILED', error: $errorData);
    }

    /**
     * Get the minimum valid release year for movies.
     * 1888 marks the beginning of cinematography with "Roundhay Garden Scene" by Louis Le Prince.
     *
     * @return int Minimum valid release year
     */
    private function getMinimumReleaseYear(): int
    {
        return 1888;
    }

    /**
     * Get the maximum valid release year for movies.
     * Current year + 10 allows for announced future releases.
     *
     * @return int Maximum valid release year
     */
    private function getMaximumReleaseYear(): int
    {
        return (int) date('Y') + 10;
    }

    /**
     * Check if release year is missing (null).
     *
     * @param  int|null  $releaseYear  Release year to check
     * @return bool True if release year is missing
     */
    private function isReleaseYearMissing(?int $releaseYear): bool
    {
        return $releaseYear === null;
    }

    /**
     * Check if release year is before the cinema era began.
     *
     * @param  int|null  $releaseYear  Release year to check
     * @return bool True if release year is before cinema era
     */
    private function isReleaseYearBeforeCinemaEra(?int $releaseYear): bool
    {
        if ($releaseYear === null) {
            return false;
        }

        return $releaseYear < $this->getMinimumReleaseYear();
    }

    /**
     * Check if release year is too far in the future.
     *
     * @param  int|null  $releaseYear  Release year to check
     * @return bool True if release year is too far in the future
     */
    private function isReleaseYearTooFarInFuture(?int $releaseYear): bool
    {
        if ($releaseYear === null) {
            return false;
        }

        return $releaseYear > $this->getMaximumReleaseYear();
    }

    /**
     * Check if release year is valid (within acceptable range).
     *
     * @param  int|null  $releaseYear  Release year to validate
     * @return bool True if release year is valid
     */
    private function isReleaseYearValid(?int $releaseYear): bool
    {
        if ($this->isReleaseYearMissing($releaseYear)) {
            return false;
        }

        if ($this->isReleaseYearBeforeCinemaEra($releaseYear)) {
            return false;
        }

        if ($this->isReleaseYearTooFarInFuture($releaseYear)) {
            return false;
        }

        return true;
    }

    /**
     * Check if title is missing or empty.
     *
     * @param  string|null  $title  Title to check
     * @return bool True if title is missing or empty
     */
    private function isTitleMissingOrEmpty(?string $title): bool
    {
        return ! is_string($title) || trim($title) === '';
    }

    /**
     * Check if director is missing (null or not a string).
     *
     * @param  string|null  $director  Director to check
     * @return bool True if director is missing
     */
    private function isDirectorMissing(?string $director): bool
    {
        return ! is_string($director);
    }

    /**
     * Check if director is empty (whitespace only).
     *
     * @param  string|null  $director  Director to check
     * @return bool True if director is empty
     */
    private function isDirectorEmpty(?string $director): bool
    {
        if (! is_string($director)) {
            return false;
        }

        return trim($director) === '';
    }

    /**
     * Check if director is the fallback "Unknown Director" value.
     *
     * @param  string|null  $director  Director to check
     * @return bool True if director is "Unknown Director"
     */
    private function isDirectorUnknownFallback(?string $director): bool
    {
        if (! is_string($director)) {
            return false;
        }

        return strtolower(trim($director)) === 'unknown director';
    }

    /**
     * Check if director is valid (not missing, not empty, not fallback value).
     *
     * @param  string|null  $director  Director to validate
     * @return bool True if director is valid
     */
    private function isDirectorValid(?string $director): bool
    {
        if ($this->isDirectorMissing($director)) {
            return false;
        }

        if ($this->isDirectorEmpty($director)) {
            return false;
        }

        if ($this->isDirectorUnknownFallback($director)) {
            return false;
        }

        return true;
    }

    /**
     * Get minimum required length for movie description.
     *
     * @return int Minimum description length in characters
     */
    private function getMinimumDescriptionLength(): int
    {
        return 50;
    }

    /**
     * Check if description is missing (null or not a string).
     *
     * @param  string|null  $descriptionText  Description to check
     * @return bool True if description is missing
     */
    private function isDescriptionMissing(?string $descriptionText): bool
    {
        return ! is_string($descriptionText);
    }

    /**
     * Check if description is empty (whitespace only).
     *
     * @param  string|null  $descriptionText  Description to check
     * @return bool True if description is empty
     */
    private function isDescriptionEmpty(?string $descriptionText): bool
    {
        if (! is_string($descriptionText)) {
            return false;
        }

        return trim($descriptionText) === '';
    }

    /**
     * Get the actual length of description (trimmed).
     *
     * @param  string|null  $descriptionText  Description to measure
     * @return int Description length in characters
     */
    private function getDescriptionLength(?string $descriptionText): int
    {
        if (! is_string($descriptionText)) {
            return 0;
        }

        return strlen(trim($descriptionText));
    }

    /**
     * Check if description is too short (below minimum length).
     *
     * @param  string|null  $descriptionText  Description to check
     * @return bool True if description is too short
     */
    private function isDescriptionTooShort(?string $descriptionText): bool
    {
        $length = $this->getDescriptionLength($descriptionText);
        $minimumLength = $this->getMinimumDescriptionLength();

        return $length < $minimumLength;
    }

    /**
     * Check if description is the fallback text.
     *
     * @param  string|null  $descriptionText  Description to check
     * @return bool True if description is fallback text
     */
    private function isDescriptionFallbackText(?string $descriptionText): bool
    {
        if (! is_string($descriptionText)) {
            return false;
        }

        return stripos($descriptionText, 'Generated description for') === 0;
    }

    /**
     * Check if description is valid (not missing, not empty, long enough, not fallback).
     *
     * @param  string|null  $descriptionText  Description to validate
     * @return array{valid: bool, error: string|null} Validation result with error message if invalid
     */
    private function validateDescription(?string $descriptionText): array
    {
        if ($this->isDescriptionMissing($descriptionText) || $this->isDescriptionEmpty($descriptionText)) {
            return [
                'valid' => false,
                'error' => 'Description is required and cannot be empty',
            ];
        }

        if ($this->isDescriptionTooShort($descriptionText)) {
            $length = $this->getDescriptionLength($descriptionText);
            $minimumLength = $this->getMinimumDescriptionLength();

            return [
                'valid' => false,
                'error' => "Description must be at least {$minimumLength} characters long (current: {$length})",
            ];
        }

        if ($this->isDescriptionFallbackText($descriptionText)) {
            return [
                'valid' => false,
                'error' => 'Description cannot be the fallback text "Generated description for..."',
            ];
        }

        return [
            'valid' => true,
            'error' => null,
        ];
    }

    /**
     * Validate AI response contains all required fields with proper values.
     *
     * @param  array<string, mixed>  $aiResponse  Full AI response
     * @param  string|null  $title  Extracted title
     * @param  int|null  $releaseYear  Extracted release year
     * @param  string|null  $director  Extracted director
     * @param  string|null  $descriptionText  Extracted description
     *
     * @throws \RuntimeException If required fields are missing or invalid
     */
    private function validateAiResponse(
        array $aiResponse,
        ?string $title,
        ?int $releaseYear,
        ?string $director,
        ?string $descriptionText
    ): void {
        $errors = [];

        // Validate title
        if ($this->isTitleMissingOrEmpty($title)) {
            $errors[] = 'Title is required and cannot be empty';
        }

        // Validate release year
        if (! $this->isReleaseYearValid($releaseYear)) {
            $minYear = $this->getMinimumReleaseYear();
            $maxYear = $this->getMaximumReleaseYear();
            $errors[] = "Release year is required and must be a valid year ({$minYear} to {$maxYear})";
        }

        // Validate director
        if (! $this->isDirectorValid($director)) {
            $errors[] = 'Director is required and cannot be empty or "Unknown Director"';
        }

        // Validate description
        $descriptionValidation = $this->validateDescription($descriptionText);
        if (! $descriptionValidation['valid']) {
            $errors[] = $descriptionValidation['error'];
        }

        if (! empty($errors)) {
            Log::warning('AI response validation failed for movie', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'errors' => $errors,
                'ai_response' => $aiResponse,
                'extracted_fields' => [
                    'title' => $title,
                    'release_year' => $releaseYear,
                    'director' => $director,
                    'description_length' => $descriptionText !== null ? strlen($descriptionText) : 0,
                ],
            ]);

            throw new \RuntimeException(
                'AI response validation failed: '.implode('; ', $errors)
            );
        }
    }

    /**
     * @return array{0: Movie, 1: MovieDescription, 2: string, 3: string}|null Returns null if suggested slugs were found (error already handled)
     */
    private function createMovieRecord(OpenAiClientInterface $openAiClient): ?array
    {
        // Pre-generation validation (before calling AI)
        if (Feature::active('hallucination_guard')) {
            $preValidator = app(\App\Services\PreGenerationValidator::class);
            $preValidation = $preValidator->shouldGenerateMovie($this->slug);

            if (! $preValidation['should_generate']) {
                Log::warning('Pre-generation validation failed for movie', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'reason' => $preValidation['reason'],
                    'confidence' => $preValidation['confidence'] ?? null,
                ]);

                throw new \RuntimeException(
                    "Pre-generation validation failed: {$preValidation['reason']}"
                );
            }
        }

        $aiResponse = $openAiClient->generateMovie($this->slug, $this->tmdbData);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                // If we have TMDb data, use it to generate the movie despite AI saying "not found"
                // This handles ambiguous slugs like "matrix-2003" where AI can't decide which movie
                // TMDb data was already verified, so we can trust it
                if ($this->tmdbData !== null && ! empty($this->tmdbData['title'])) {
                    Log::info('Movie not found by AI, but TMDb data available - using TMDb data as fallback', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'tmdb_title' => $this->tmdbData['title'] ?? null,
                        'tmdb_id' => $this->tmdbData['id'] ?? null,
                        'tmdb_has_director' => ! empty($this->tmdbData['director']),
                        'tmdb_has_overview' => ! empty($this->tmdbData['overview']),
                    ]);

                    // Use TMDb data directly to create the movie
                    // This handles cases where AI can't decide between ambiguous movies
                    // even when TMDb data is provided (e.g., "matrix-2003" could be Reloaded or Revolutions)
                    // TMDb data was already verified by controller, so we can trust it
                    Log::info('Using TMDb data directly to create movie despite AI "not found"', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                        'tmdb_title' => $this->tmdbData['title'] ?? null,
                        'tmdb_id' => $this->tmdbData['id'] ?? null,
                    ]);

                    // Create response from TMDb data
                    // We'll use TMDb data for title, director, release_year
                    // For description, use TMDb overview as fallback if it's long enough
                    // This allows the movie to be created even when AI can't generate description
                    // Note: TMDb overview will be used, but it should be long enough to pass validation
                    $tmdbOverview = $this->tmdbData['overview'] ?? null;
                    $useTmdbOverview = ! empty($tmdbOverview) && strlen(trim($tmdbOverview)) >= $this->getMinimumDescriptionLength();

                    $aiResponse = [
                        'success' => true,
                        'title' => $this->tmdbData['title'] ?? null,
                        'release_year' => ! empty($this->tmdbData['release_date'])
                            ? (int) substr($this->tmdbData['release_date'], 0, 4)
                            : null,
                        'director' => $this->tmdbData['director'] ?? null,
                        'description' => $useTmdbOverview ? $tmdbOverview : null,
                        'genres' => [],
                        'model' => config('services.openai.model', 'gpt-4o-mini'),
                    ];

                    if (! $useTmdbOverview) {
                        Log::warning('TMDb overview too short or missing, description will be null', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'overview_length' => $tmdbOverview !== null ? strlen(trim($tmdbOverview)) : 0,
                            'minimum_length' => $this->getMinimumDescriptionLength(),
                        ]);
                    } else {
                        Log::info('Using TMDb overview as description fallback', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'overview_length' => strlen(trim($tmdbOverview)),
                        ]);
                    }

                    // Note: We don't retry AI generation here because:
                    // 1. First call already had TMDb data and failed
                    // 2. Retry with same data likely to fail again
                    // 3. Using TMDb overview as fallback allows movie creation to proceed
                    // 4. If overview is too short, validation will catch it and provide clear error
                } else {
                    // No TMDb data available - try to find suggested slugs
                    $suggestedSlugs = $this->findSuggestedSlugs();

                    if (! empty($suggestedSlugs)) {
                        Log::info('Movie not found by AI, but found suggested slugs in TMDb', [
                            'slug' => $this->slug,
                            'job_id' => $this->jobId,
                            'suggestions_count' => count($suggestedSlugs),
                        ]);

                        // Format error with suggested slugs
                        $errorFormatter = app(JobErrorFormatter::class);
                        $errorData = $errorFormatter->formatError(
                            new \RuntimeException("Movie not found: {$this->slug}"),
                            $this->slug,
                            'MOVIE',
                            $suggestedSlugs
                        );

                        $this->updateCache('FAILED', error: $errorData);

                        return null; // Don't throw exception, just end the job with error
                    }

                    // No suggestions found - this is a real "not found"
                    Log::warning('Movie not found by AI and no TMDb data or suggestions available', [
                        'slug' => $this->slug,
                        'job_id' => $this->jobId,
                    ]);

                    throw new \RuntimeException(
                        "Movie not found: '{$this->slug}'. ".
                        'AI could not generate description and no TMDb data available. '.
                        "Possible solutions: 1) Verify slug format (title-year, e.g., 'the-matrix-1999'), ".
                        '2) Use disambiguation endpoint with ?tmdb_id parameter if multiple movies match, '.
                        '3) Check if movie exists in TMDb database.'
                    );
                }
            } else {
                // Other AI errors (not "not found")
                throw new \RuntimeException('AI API returned error: '.$error);
            }
        }

        // Validate AI response data consistency with slug
        if (Feature::active('hallucination_guard')) {
            $validator = app(\App\Services\AiDataValidator::class);
            $validation = $validator->validateMovieData($aiResponse, $this->slug);

            if (! $validation['valid']) {
                Log::warning('AI data validation failed for movie', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'errors' => $validation['errors'],
                    'similarity' => $validation['similarity'],
                    'ai_response' => $aiResponse,
                ]);

                throw new \RuntimeException(
                    'AI data validation failed: '.implode(', ', $validation['errors'])
                );
            }
        }

        // Validate and extract required fields from AI response
        $title = $aiResponse['title'] ?? null;
        $releaseYear = $aiResponse['release_year'] ?? null;
        $director = $aiResponse['director'] ?? null;
        $descriptionText = $aiResponse['description'] ?? null;
        $genres = $aiResponse['genres'] ?? [];

        // Track if description came from TMDb overview (should be marked as TRANSLATED, not GENERATED)
        $descriptionFromTmdb = false;

        // Use TMDb data as fallback for missing fields (only if TMDb data is available)
        if ($this->tmdbData !== null) {
            if (empty($title) && ! empty($this->tmdbData['title'])) {
                $title = $this->tmdbData['title'];
            }
            if (empty($director) && ! empty($this->tmdbData['director'])) {
                $director = $this->tmdbData['director'];
            }
            if ($releaseYear === null && ! empty($this->tmdbData['release_date'])) {
                $year = (int) substr($this->tmdbData['release_date'], 0, 4);
                if ($year > 0) {
                    $releaseYear = $year;
                }
            }
            // Check if description came from TMDb overview
            if (! empty($descriptionText) && ! empty($this->tmdbData['overview']) && $descriptionText === $this->tmdbData['overview']) {
                $descriptionFromTmdb = true;
            }
        }

        // Validate required fields before proceeding
        $this->validateAiResponse($aiResponse, $title, $releaseYear, $director, $descriptionText);

        // Generate unique slug from AI data instead of using slug from request
        // This ensures uniqueness and prevents conflicts with ambiguous slugs
        // Following DIP: use Movie model method, not direct slug from request
        $generatedSlug = Movie::generateSlug((string) $title, $releaseYear, $director);

        // Check if movie already exists with generated slug (prevent duplicates)
        // This handles race conditions where multiple jobs create the same movie
        /** @var MovieRepository $movieRepository */
        $movieRepository = app(MovieRepository::class);
        $existingByGeneratedSlug = $movieRepository->findBySlugForJob($generatedSlug);
        if ($existingByGeneratedSlug) {
            // Movie already exists with generated slug - use it
            $movie = $existingByGeneratedSlug;
        } else {
            // Check if movie exists by title + year (even if slug differs)
            // This prevents duplicates when slug format differs
            $existingByTitleYear = Movie::where('title', (string) $title)
                ->where('release_year', $releaseYear)
                ->first();

            if ($existingByTitleYear) {
                // Movie already exists - use it
                $movie = $existingByTitleYear;
            } else {
                // Create new movie
                $movie = Movie::create([
                    'title' => (string) $title,
                    'slug' => $generatedSlug,
                    'release_year' => $releaseYear,
                    'director' => $director,
                    'genres' => $genres,
                ]);

                // Invalidate movie search cache when new movie is created
                $this->invalidateMovieSearchCache();
            }
        }

        $locale = $this->resolveLocale();
        // Use TRANSLATED origin if description came from TMDb overview, otherwise GENERATED
        $descriptionOrigin = $descriptionFromTmdb ? DescriptionOrigin::TRANSLATED : DescriptionOrigin::GENERATED;
        $description = $this->shouldUpdateBaseline($movie, $locale)
            ? $this->updateBaselineDescription($movie, $locale, [
                'text' => (string) $descriptionText,
                'origin' => $descriptionOrigin,
                'ai_model' => $descriptionFromTmdb ? null : ($aiResponse['model'] ?? 'openai-gpt-4'),
            ])
            : $this->persistDescription(
                $movie,
                $locale,
                $this->determineContextTag($movie, $locale),
                [
                    'text' => (string) $descriptionText,
                    'origin' => $descriptionOrigin,
                    'ai_model' => $descriptionFromTmdb ? null : ($aiResponse['model'] ?? 'openai-gpt-4'),
                ]
            );

        $contextForCache = $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag;

        return [$movie->fresh(['descriptions']), $description, $locale->value, $contextForCache];
    }

    private function promoteDefaultIfEligible(Movie $movie, MovieDescription $description): void
    {
        $lock = Cache::lock($this->defaultLockKey($movie), 15);

        try {
            $lock->block(5, function () use ($movie, $description): void {
                $movie->refresh();

                $currentDefault = $movie->default_description_id;
                if ($this->baselineDescriptionId !== null) {
                    // Compare UUID strings (not int)
                    if ((string) $currentDefault !== (string) $this->baselineDescriptionId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $movie->default_description_id = $description->id;
                $movie->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('RealGenerateMovieJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'movie_id' => $movie->id,
            ]);
        }
    }

    private function defaultLockKey(Movie $movie): string
    {
        return 'lock:movie:default:'.$movie->id;
    }

    private function invalidateMovieCaches(Movie $movie): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $movie->slug,
        ]));

        $descriptionIds = $movie->descriptions()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('movie:'.$slug.':desc:default');

            foreach ($descriptionIds as $descriptionId) {
                Cache::forget('movie:'.$slug.':desc:'.$descriptionId);
            }
        }
    }

    private function isUniqueMovieSlugViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? null;
        $sqlState = is_array($errorInfo) && isset($errorInfo[0])
            ? (string) $errorInfo[0]
            : (string) $exception->getCode();

        if (! in_array($sqlState, ['23000', '23505'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'movies.slug') || str_contains($message, 'movies_slug_unique')) {
            return true;
        }

        if (is_array($errorInfo) && isset($errorInfo[2])) {
            $details = strtolower((string) $errorInfo[2]);
            if (str_contains($details, 'movies.slug') || str_contains($details, 'movies_slug_unique')) {
                return true;
            }
        }

        return false;
    }

    private function markDoneUsingExisting(Movie $movie): void
    {
        $movie->loadMissing(['descriptions', 'defaultDescription']);
        /** @var MovieDescription|null $description */
        $description = $movie->defaultDescription ?? $movie->descriptions->first();

        $resolvedLocale = is_string($this->locale) ? $this->locale : null;
        $resolvedContextTag = $this->contextTag;

        if ($description instanceof MovieDescription) {
            /** @var mixed $descriptionLocale */
            $descriptionLocale = $description->locale;
            if ($descriptionLocale instanceof Locale) {
                $resolvedLocale = $descriptionLocale->value;
            } else {
                $resolvedLocale = (string) $descriptionLocale;
            }

            /** @var mixed $descriptionContext */
            $descriptionContext = $description->context_tag;
            if ($descriptionContext instanceof ContextTag) {
                $resolvedContextTag = $descriptionContext->value;
            } else {
                $resolvedContextTag = (string) $descriptionContext;
            }
        }

        $descriptionId = $description instanceof MovieDescription ? $description->id : null;

        $this->updateCache(
            'DONE',
            $movie->id,
            $movie->slug,
            $descriptionId,
            $resolvedLocale,
            $resolvedContextTag
        );
    }

    /**
     * Find suggested slugs from TMDb when movie is not found.
     * Searches TMDb for movies matching the slug and generates suggested slugs.
     *
     * @return array<int, array{slug: string, title: string, release_year: int|null, director: string|null, tmdb_id: int}>|null
     */
    private function findSuggestedSlugs(): ?array
    {
        // Only search if TMDb verification is enabled
        if (! Feature::active('tmdb_verification')) {
            return null;
        }

        try {
            /** @var EntityVerificationServiceInterface $tmdbService */
            $tmdbService = app(EntityVerificationServiceInterface::class);
            $searchResults = $tmdbService->searchMovies($this->slug, 5);

            if (empty($searchResults)) {
                return null;
            }

            // Generate suggested slugs from TMDb results
            $suggestedSlugs = [];
            foreach ($searchResults as $result) {
                $year = ! empty($result['release_date'])
                    ? (int) substr($result['release_date'], 0, 4)
                    : null;
                $director = $result['director'] ?? null;

                $suggestedSlugs[] = [
                    'slug' => Movie::generateSlug(
                        $result['title'],
                        $year,
                        $director
                    ),
                    'title' => $result['title'],
                    'release_year' => $year,
                    'director' => $director,
                    'tmdb_id' => $result['id'],
                ];
            }

            return $suggestedSlugs;
        } catch (\Throwable $e) {
            Log::warning('Failed to find suggested slugs from TMDb', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Invalidate movie search cache when a new movie is created.
     * Uses tagged cache if supported, otherwise clears all search cache keys.
     */
    private function invalidateMovieSearchCache(): void
    {
        try {
            // Try tagged cache invalidation (works with Redis, Memcached, DynamoDB)
            Cache::tags(['movie_search'])->flush();
            Log::debug('RealGenerateMovieJob: invalidated tagged cache after movie creation');
        } catch (\BadMethodCallException $e) {
            // Fallback: For database/file cache, we can't easily invalidate by tag
            // Cache will expire naturally after TTL (1 hour)
            // In production, consider using Redis for better cache control
            Log::debug('RealGenerateMovieJob: tagged cache not supported, cache will expire naturally', [
                'driver' => config('cache.default'),
            ]);
        }
    }
}
