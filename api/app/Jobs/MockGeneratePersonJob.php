<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use App\Services\JobErrorFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * Mock Generate Person Job - simulates AI generation for development/testing.
 * Used when AI_SERVICE=mock.
 */
class MockGeneratePersonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?int $existingPersonId = null,
        public ?int $baselineBioId = null,
        public ?string $locale = null,
        public ?string $contextTag = null
    ) {}

    public function handle(): void
    {
        try {
            $existing = $this->findExistingPerson();

            if ($existing) {
                $this->refreshExistingPerson($existing);

                return;
            }

            $this->createPersonWithLock();
        } catch (\Throwable $e) {
            Log::error('MockGeneratePersonJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            /** @var JobErrorFormatter $errorFormatter */
            $errorFormatter = app(JobErrorFormatter::class);
            $errorData = $errorFormatter->formatError($e, $this->slug, 'PERSON');
            $this->updateCache('FAILED', error: $errorData);

            throw $e; // Re-throw for retry mechanism
        }
    }

    private function refreshExistingPerson(Person $person): void
    {
        $person->loadMissing('bios');
        $locale = $this->resolveLocale();
        $bio = $this->shouldUpdateBaseline($person, $locale)
            ? $this->updateBaselineBio($person, $locale, [
                'text' => sprintf(
                    'Regenerated biography for %s on %s (MockGeneratePersonJob).',
                    $person->name,
                    now()->toIso8601String()
                ),
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-ai-1',
            ])
            : $this->persistBio(
                $person,
                $locale,
                $this->determineContextTag($person, $locale),
                [
                    'text' => sprintf(
                        'Regenerated biography for %s on %s (MockGeneratePersonJob).',
                        $person->name,
                        now()->toIso8601String()
                    ),
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => 'mock-ai-1',
                ]
            );

        $this->promoteDefaultIfEligible($person, $bio);
        $this->invalidatePersonCaches($person);
        $contextForCache = $bio->context_tag instanceof ContextTag ? $bio->context_tag->value : (string) $bio->context_tag;
        $this->updateCache('DONE', $person->id, $bio->id, $person->slug, $locale->value, $contextForCache);
    }

    private function updateCache(
        string $status,
        ?int $id = null,
        ?int $bioId = null,
        ?string $slug = null,
        ?string $locale = null,
        ?string $contextTag = null,
        ?array $error = null
    ): void {
        $payload = [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'PERSON',
            'slug' => $slug ?? $this->slug,
            'requested_slug' => $this->slug,
            'id' => $id,
            'bio_id' => $bioId,
            'locale' => $locale ?? $this->locale,
            'context_tag' => $contextTag ?? $this->contextTag,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        Cache::put($this->cacheKey(), $payload, now()->addMinutes(15));
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

    private function determineContextTag(Person $person, Locale $locale): string
    {
        if ($this->contextTag !== null) {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($person);
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

    private function persistBio(Person $person, Locale $locale, string $contextTag, array $attributes): PersonBio
    {
        $existing = PersonBio::where('person_id', $person->id)
            ->where('locale', $locale->value)
            ->where('context_tag', $contextTag)
            ->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();

            return $existing->fresh();
        }

        return PersonBio::create(array_merge([
            'person_id' => $person->id,
            'locale' => $locale->value,
            'context_tag' => $contextTag,
        ], $attributes));
    }

    private function shouldUpdateBaseline(Person $person, Locale $locale): bool
    {
        if (! $this->baselineLockingEnabled() || $this->baselineBioId === null || $this->contextTag !== null) {
            return false;
        }

        $baseline = $this->getBaselineBio($person);

        if (! $baseline instanceof PersonBio) {
            return false;
        }

        if ($this->locale !== null && strtolower($baseline->locale->value) !== strtolower($locale->value)) {
            return false;
        }

        return true;
    }

    private function baselineLockingEnabled(): bool
    {
        return Feature::active('ai_generation_baseline_locking');
    }

    private function getBaselineBio(Person $person): ?PersonBio
    {
        $bio = $person->bios->firstWhere('id', $this->baselineBioId);

        return $bio instanceof PersonBio ? $bio : PersonBio::find($this->baselineBioId);
    }

    private function updateBaselineBio(Person $person, Locale $locale, array $attributes): PersonBio
    {
        $baseline = $this->getBaselineBio($person);

        if (! $baseline instanceof PersonBio) {
            return $this->persistBio($person, $locale, $this->determineContextTag($person, $locale), $attributes);
        }

        $baseline->fill(array_merge($attributes, [
            'locale' => $locale->value,
        ]));
        $baseline->save();

        return $baseline->fresh();
    }

    private function nextContextTag(Person $person): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $person->bios()->pluck('context_tag')->all()
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

    public function failed(\Throwable $exception): void
    {
        Log::error('MockGeneratePersonJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        /** @var JobErrorFormatter $errorFormatter */
        $errorFormatter = app(JobErrorFormatter::class);
        $errorData = $errorFormatter->formatError($exception, $this->slug, 'PERSON');
        $this->updateCache('FAILED', error: $errorData);
    }

    private function findExistingPerson(): ?Person
    {
        if ($this->existingPersonId !== null) {
            $person = Person::with('bios')->find($this->existingPersonId);
            if ($person) {
                return $person;
            }
        }

        return Person::with('bios')->where('slug', $this->slug)->first();
    }

    private function createPersonWithLock(): void
    {
        $lock = Cache::lock($this->creationLockKey(), 15);

        try {
            $lock->block(5, function (): void {
                $existing = $this->findExistingPerson();
                if ($existing) {
                    $this->refreshExistingPerson($existing);

                    return;
                }

                [$person, $bio, $localeValue, $contextTag] = $this->createPersonRecord();

                $this->promoteDefaultIfEligible($person, $bio);
                $this->invalidatePersonCaches($person);
                $this->updateCache('DONE', $person->id, $bio->id, $person->slug, $localeValue, $contextTag);
            });
        } catch (LockTimeoutException $exception) {
            $existing = $this->findExistingPerson();
            if ($existing) {
                $this->refreshExistingPerson($existing);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: Person, 1: PersonBio, 2: string, 3: string}
     */
    private function createPersonRecord(): array
    {
        sleep(3);

        $name = Str::of($this->slug)->replace('-', ' ')->title();
        $person = Person::create([
            'name' => (string) $name,
            'slug' => $this->slug,
            'birth_date' => '1970-01-01',
            'birthplace' => 'Mock City',
        ]);

        $locale = $this->resolveLocale();
        $bio = $this->shouldUpdateBaseline($person, $locale)
            ? $this->updateBaselineBio($person, $locale, [
                'text' => sprintf(
                    'Generated biography for %s (%s locale). This text was produced by MockGeneratePersonJob (AI_SERVICE=mock).',
                    $name,
                    $locale->value
                ),
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-ai-1',
            ])
            : $this->persistBio(
                $person,
                $locale,
                $this->determineContextTag($person, $locale),
                [
                    'text' => sprintf(
                        'Generated biography for %s (%s locale). This text was produced by MockGeneratePersonJob (AI_SERVICE=mock).',
                        $name,
                        $locale->value
                    ),
                    'origin' => DescriptionOrigin::GENERATED,
                    'ai_model' => 'mock-ai-1',
                ]
            );

        $contextForCache = $bio->context_tag instanceof ContextTag ? $bio->context_tag->value : (string) $bio->context_tag;

        return [$person->fresh(['bios']), $bio, $locale->value, $contextForCache];
    }

    private function promoteDefaultIfEligible(Person $person, PersonBio $bio): void
    {
        $lock = Cache::lock($this->defaultLockKey($person), 10);

        try {
            $lock->block(5, function () use ($person, $bio): void {
                $person->refresh();
                $currentDefault = $person->default_bio_id;

                if ($this->baselineBioId !== null) {
                    if ((int) $currentDefault !== $this->baselineBioId) {
                        return;
                    }
                } elseif ($currentDefault !== null) {
                    return;
                }

                $person->default_bio_id = $bio->id;
                $person->save();
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('MockGeneratePersonJob default promotion lock timeout', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'person_id' => $person->id,
            ]);
        }
    }

    private function creationLockKey(): string
    {
        return 'lock:person:create:'.$this->slug;
    }

    private function defaultLockKey(Person $person): string
    {
        return 'lock:person:default:'.$person->id;
    }

    private function invalidatePersonCaches(Person $person): void
    {
        $slugs = array_unique(array_filter([
            $this->slug,
            $person->slug,
        ]));

        $bioIds = $person->bios()->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('person:'.$slug.':bio:default');

            foreach ($bioIds as $bioId) {
                Cache::forget('person:'.$slug.':bio:'.$bioId);
            }
        }
    }
}
