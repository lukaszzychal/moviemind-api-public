<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Real Generate Person Job - calls actual AI API for production.
 * Used when AI_SERVICE=real.
 */
class RealGeneratePersonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120; // Longer timeout for real API calls

    public function __construct(
        public string $slug,
        public string $jobId,
        public ?int $existingPersonId = null,
        public ?int $baselineBioId = null
    ) {}

    /**
     * Execute the job.
     * Note: OpenAiClientInterface is injected via method injection.
     * Constructor injection is not possible because Jobs are serialized to queue.
     */
    public function handle(OpenAiClientInterface $openAiClient): void
    {
        try {
            $existing = $this->findExistingPerson();

            if ($existing) {
                $this->refreshExistingPerson($existing, $openAiClient);

                return;
            }

            $this->createPersonWithLock($openAiClient);
        } catch (\Throwable $e) {
            Log::error('RealGeneratePersonJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateCache('FAILED');

            throw $e; // Re-throw for retry mechanism
        }
    }

    /**
     * Configure retry backoff tuned for OpenAI free-tier limits.
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

    private function refreshExistingPerson(Person $person, OpenAiClientInterface $openAiClient): void
    {
        $person->loadMissing('bios');
        $aiResponse = $openAiClient->generatePerson($this->slug);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            throw new \RuntimeException('AI API returned error: '.$error);
        }

        $biography = $aiResponse['biography']
            ?? sprintf('Regenerated biography for %s via RealGeneratePersonJob.', $person->name);

        $contextTag = $this->nextContextTag($person);

        $bio = PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => (string) $biography,
            'context_tag' => $contextTag,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
        ]);

        $this->promoteDefaultIfEligible($person, $bio);
        $this->invalidatePersonCaches($person);
        $this->updateCache('DONE', $person->id, $bio->id, $person->slug);
    }

    private function updateCache(string $status, ?int $id = null, ?int $bioId = null, ?string $slug = null): void
    {
        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'PERSON',
            'slug' => $slug ?? $this->slug,
            'id' => $id,
            'bio_id' => $bioId,
        ], now()->addMinutes(15));
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
        Log::error('RealGeneratePersonJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => 'FAILED',
            'entity' => 'PERSON',
            'slug' => $this->slug,
            'error' => $exception->getMessage(),
        ], now()->addMinutes(15));
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

    private function createPersonWithLock(OpenAiClientInterface $openAiClient): void
    {
        $lock = Cache::lock($this->creationLockKey(), 30);

        try {
            $lock->block(5, function () use ($openAiClient): void {
                $existing = $this->findExistingPerson();
                if ($existing) {
                    $this->refreshExistingPerson($existing, $openAiClient);

                    return;
                }

                [$person, $bio] = $this->createPersonRecord($openAiClient);

                $this->promoteDefaultIfEligible($person, $bio);
                $this->invalidatePersonCaches($person);
                $this->updateCache('DONE', $person->id, $bio->id, $person->slug);
            });
        } catch (LockTimeoutException $exception) {
            $existing = $this->findExistingPerson();
            if ($existing) {
                $this->refreshExistingPerson($existing, $openAiClient);

                return;
            }

            throw $exception;
        }
    }

    /**
     * @return array{0: Person, 1: PersonBio}
     */
    private function createPersonRecord(OpenAiClientInterface $openAiClient): array
    {
        $aiResponse = $openAiClient->generatePerson($this->slug);

        if ($aiResponse['success'] === false) {
            $error = $aiResponse['error'] ?? 'Unknown error';

            throw new \RuntimeException('AI API returned error: '.$error);
        }

        $name = $aiResponse['name'] ?? Str::of($this->slug)->replace('-', ' ')->title();
        $birthDate = $aiResponse['birth_date'] ?? '1970-01-01';
        $birthplace = $aiResponse['birthplace'] ?? 'Unknown';
        $biography = $aiResponse['biography'] ?? "Biography for {$name}.";

        $person = Person::create([
            'name' => (string) $name,
            'slug' => $this->slug,
            'birth_date' => $birthDate,
            'birthplace' => $birthplace,
        ]);

        $contextTag = $this->nextContextTag($person);

        $bio = PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => (string) $biography,
            'context_tag' => $contextTag,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
        ]);

        return [$person->fresh(['bios']), $bio];
    }

    private function promoteDefaultIfEligible(Person $person, PersonBio $bio): void
    {
        $lock = Cache::lock($this->defaultLockKey($person), 15);

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
            Log::warning('RealGeneratePersonJob default promotion lock timeout', [
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
