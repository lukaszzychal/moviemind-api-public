<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
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
        public string $jobId
    ) {}

    public function handle(): void
    {
        try {
            // Check if person already exists
            $existing = Person::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Double-check (race condition protection)
            $existing = Person::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);

                return;
            }

            // Call real AI API using OpenAiClient
            $openAiClient = app(OpenAiClientInterface::class);
            $aiResponse = $openAiClient->generatePerson($this->slug);

            if (! $aiResponse || ! isset($aiResponse['success']) || ! $aiResponse['success']) {
                throw new \RuntimeException('AI API returned error: '.($aiResponse['error'] ?? 'Unknown error'));
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

            $bio = PersonBio::create([
                'person_id' => $person->id,
                'locale' => Locale::EN_US,
                'text' => (string) $biography,
                'context_tag' => ContextTag::DEFAULT,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => $aiResponse['model'] ?? 'openai-gpt-4',
            ]);

            $person->default_bio_id = $bio->id;
            $person->save();

            $this->updateCache('DONE', $person->id);
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

    private function updateCache(string $status, ?int $id = null): void
    {
        Cache::put($this->cacheKey(), [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'PERSON',
            'slug' => $this->slug,
            'id' => $id,
        ], now()->addMinutes(15));
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
}
