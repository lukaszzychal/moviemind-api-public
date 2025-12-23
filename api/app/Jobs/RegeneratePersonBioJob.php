<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Services\AiOutputValidator;
use App\Services\OpenAiClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for regenerating a person bio after report verification.
 */
class RegeneratePersonBioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $personId,
        public string $bioId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        OpenAiClientInterface $openAiClient,
        PersonRepository $personRepository,
        AiOutputValidator $outputValidator
    ): void {
        Log::info('RegeneratePersonBioJob started', [
            'person_id' => $this->personId,
            'bio_id' => $this->bioId,
        ]);

        $person = Person::find($this->personId);
        if ($person === null) {
            Log::warning('Person not found for regeneration', [
                'person_id' => $this->personId,
            ]);
            $this->fail(new \RuntimeException("Person not found: {$this->personId}"));

            return;
        }

        $bio = PersonBio::find($this->bioId);
        if ($bio === null) {
            Log::warning('Bio not found for regeneration', [
                'bio_id' => $this->bioId,
            ]);
            $this->fail(new \RuntimeException("Bio not found: {$this->bioId}"));

            return;
        }

        // Generate new bio using AI
        $contextTag = ($bio->context_tag !== null) ? $bio->context_tag->value : 'DEFAULT';
        $result = $openAiClient->generatePerson(
            $person->slug,
            null // No TMDb data for regeneration
        );

        if (! $result['success'] || empty($result['biography'])) {
            Log::error('Failed to generate new bio', [
                'person_id' => $this->personId,
                'bio_id' => $this->bioId,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            $this->fail(new \RuntimeException('Failed to generate new bio'));

            return;
        }

        // Validate and sanitize output
        $validation = $outputValidator->validateAndSanitizeDescription($result['biography']);

        if (! $validation['valid']) {
            Log::error('Generated bio failed validation', [
                'person_id' => $this->personId,
                'bio_id' => $this->bioId,
                'errors' => $validation['errors'],
            ]);
            $this->fail(new \RuntimeException('Generated bio failed validation: '.implode(', ', $validation['errors'])));

            return;
        }

        // Delete old bio (due to unique constraint on person_id, locale, context_tag)
        // We need to delete it before creating new one
        $oldBioId = $bio->id;
        $wasDefault = $person->default_bio_id === $oldBioId;
        $locale = $bio->locale;
        $contextTag = $bio->context_tag;
        $origin = $bio->origin;

        $bio->delete();

        // Create new bio with same locale and context_tag
        $newBio = PersonBio::create([
            'person_id' => $person->id,
            'locale' => $locale,
            'text' => $validation['sanitized'],
            'context_tag' => $contextTag,
            'origin' => $origin,
            'ai_model' => $result['model'] ?? 'gpt-4o-mini',
        ]);

        // Update person's default_bio_id to point to new bio if this was the default
        if ($wasDefault) {
            $person->update([
                'default_bio_id' => $newBio->id,
            ]);
        }

        // Update all related reports to RESOLVED (both old and new bio IDs)
        \App\Models\PersonReport::where('person_id', $this->personId)
            ->whereIn('bio_id', [$oldBioId, $newBio->id])
            ->where('status', ReportStatus::VERIFIED)
            ->update([
                'status' => ReportStatus::RESOLVED,
                'resolved_at' => now(),
            ]);

        Log::info('Person bio regenerated successfully', [
            'person_id' => $this->personId,
            'old_bio_id' => $oldBioId,
            'new_bio_id' => $newBio->id,
        ]);
    }
}
