<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Repositories\MovieRepository;
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
use Illuminate\Support\Str;
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
        public ?int $existingMovieId = null,
        public ?int $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null
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
                [$movie, $description, $localeValue, $contextTag] = $this->createMovieRecord($openAiClient);
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
        $aiResponse = $openAiClient->generateMovie($this->slug);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                Log::warning('Movie not found by AI during refresh', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                    'movie_id' => $movie->id,
                ]);

                throw new \RuntimeException("Movie not found: {$this->slug}");
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

        $descriptionText = $aiResponse['description']
            ?? sprintf('Regenerated description for %s via RealGenerateMovieJob.', $movie->title);

        $locale = $this->resolveLocale();
        $description = $this->shouldUpdateBaseline($movie, $locale)
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

        $this->promoteDefaultIfEligible($movie, $description);
        $this->invalidateMovieCaches($movie);
        $contextForCache = $description->context_tag instanceof ContextTag ? $description->context_tag->value : (string) $description->context_tag;
        $this->updateCache('DONE', $movie->id, $movie->slug, $description->id, $locale->value, $contextForCache);
    }

    private function updateCache(
        string $status,
        ?int $id = null,
        ?string $slug = null,
        ?int $descriptionId = null,
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

        $suffix = 2;
        do {
            $candidate = ContextTag::DEFAULT->value.'_'.$suffix;
            $suffix++;
        } while (in_array($candidate, $existingTags, true));

        return $candidate;
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
     * @return array{0: Movie, 1: MovieDescription, 2: string, 3: string}
     */
    private function createMovieRecord(OpenAiClientInterface $openAiClient): array
    {
        $aiResponse = $openAiClient->generateMovie($this->slug);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            // Check if it's a "not found" error from AI
            if (stripos($error, 'not found') !== false) {
                Log::warning('Movie not found by AI', [
                    'slug' => $this->slug,
                    'job_id' => $this->jobId,
                ]);

                throw new \RuntimeException("Movie not found: {$this->slug}");
            }

            throw new \RuntimeException('AI API returned error: '.$error);
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

        $title = $aiResponse['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $releaseYear = $aiResponse['release_year'] ?? 1999;
        $director = $aiResponse['director'] ?? 'Unknown Director';
        $descriptionText = $aiResponse['description'] ?? "Generated description for {$title}.";
        $genres = $aiResponse['genres'] ?? ['Action', 'Drama'];

        // Generate unique slug from AI data instead of using slug from request
        // This ensures uniqueness and prevents conflicts with ambiguous slugs
        // Following DIP: use Movie model method, not direct slug from request
        $generatedSlug = Movie::generateSlug((string) $title, $releaseYear, $director);

        $movie = Movie::create([
            'title' => (string) $title,
            'slug' => $generatedSlug,
            'release_year' => $releaseYear,
            'director' => $director,
            'genres' => $genres,
        ]);

        $locale = $this->resolveLocale();
        $description = $this->shouldUpdateBaseline($movie, $locale)
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
                    if ((int) $currentDefault !== $this->baselineDescriptionId) {
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
}
