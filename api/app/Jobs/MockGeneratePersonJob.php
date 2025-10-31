<?php

namespace App\Jobs;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            // Simulate long-running AI generation (mock)
            sleep(3);

            // Double-check (race condition protection)
            $existing = Person::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);

                return;
            }

            $name = Str::of($this->slug)->replace('-', ' ')->title();
            $person = Person::create([
                'name' => (string) $name,
                'slug' => $this->slug,
                'birth_date' => '1970-01-01',
                'birthplace' => 'Mock City',
            ]);

            $bio = PersonBio::create([
                'person_id' => $person->id,
                'locale' => Locale::EN_US,
                'text' => "Generated biography for {$name}. This text was produced by MockGeneratePersonJob (AI_SERVICE=mock).",
                'context_tag' => ContextTag::DEFAULT,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock-ai-1',
            ]);

            $person->default_bio_id = $bio->id;
            $person->save();

            $this->updateCache('DONE', $person->id);
        } catch (\Throwable $e) {
            Log::error('MockGeneratePersonJob failed', [
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
        Log::error('MockGeneratePersonJob permanently failed', [
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
