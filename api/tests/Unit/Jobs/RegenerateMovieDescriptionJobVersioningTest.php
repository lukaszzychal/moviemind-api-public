<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\ContextTag;
use App\Enums\Locale;
use App\Enums\ReportStatus;
use App\Jobs\RegenerateMovieDescriptionJob;
use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\MovieReport;
use App\Repositories\MovieRepository;
use App\Services\AiOutputValidator;
use App\Services\OpenAiClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RegenerateMovieDescriptionJobVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
    }

    public function test_regenerating_description_archives_old_version_and_creates_new(): void
    {
        // Arrange: Create movie and description
        $movie = Movie::factory()->create();
        $oldDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::DEFAULT,
            'text' => 'Old description text',
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $movie->update(['default_description_id' => $oldDescription->id]);

        // Mock OpenAI client
        $openAiClient = $this->createMock(OpenAiClientInterface::class);
        $openAiClient->expects($this->once())
            ->method('generateMovieDescription')
            ->willReturn([
                'success' => true,
                'description' => 'New description text',
                'model' => 'gpt-4o-mini',
            ]);

        // Mock validator
        $validator = $this->createMock(AiOutputValidator::class);
        $validator->expects($this->once())
            ->method('validateAndSanitizeDescription')
            ->willReturn([
                'valid' => true,
                'sanitized' => 'New description text',
            ]);

        $repository = $this->app->make(MovieRepository::class);

        // Act: Execute job
        $job = new RegenerateMovieDescriptionJob($movie->id, $oldDescription->id);
        $job->handle($openAiClient, $repository, $validator);

        // Assert: Old description is archived
        $oldDescription->refresh();
        $this->assertNotNull($oldDescription->archived_at, 'Old description should be archived');
        $this->assertEquals(1, $oldDescription->version_number, 'Old description should keep version 1');

        // Assert: New description is created
        $newDescription = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', Locale::EN_US)
            ->where('context_tag', ContextTag::DEFAULT)
            ->whereNull('archived_at')
            ->first();

        $this->assertNotNull($newDescription, 'New description should be created');
        $this->assertEquals('New description text', $newDescription->text);
        $this->assertEquals(2, $newDescription->version_number, 'New description should have version 2 (max + 1)');
        $this->assertNull($newDescription->archived_at, 'New description should not be archived');

        // Assert: Movie's default_description_id is updated
        $movie->refresh();
        $this->assertEquals($newDescription->id, $movie->default_description_id, 'Movie should point to new description');
    }

    public function test_regenerating_description_increments_version_number_when_multiple_versions_exist(): void
    {
        // Arrange: Create movie with multiple archived versions
        $movie = Movie::factory()->create();

        // Create archived versions
        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::DEFAULT,
            'text' => 'Version 1 text',
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 1,
            'archived_at' => now()->subDays(1),
        ]);
        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::DEFAULT,
            'text' => 'Version 2 text',
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 2,
            'archived_at' => now()->subHours(1),
        ]);

        // Current active description
        $currentDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::DEFAULT,
            'text' => 'Version 3 text',
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 3,
            'archived_at' => null,
        ]);

        $movie->update(['default_description_id' => $currentDescription->id]);

        // Mock OpenAI client
        $openAiClient = $this->createMock(OpenAiClientInterface::class);
        $openAiClient->expects($this->once())
            ->method('generateMovieDescription')
            ->willReturn([
                'success' => true,
                'description' => 'New description text',
                'model' => 'gpt-4o-mini',
            ]);

        // Mock validator
        $validator = $this->createMock(AiOutputValidator::class);
        $validator->expects($this->once())
            ->method('validateAndSanitizeDescription')
            ->willReturn([
                'valid' => true,
                'sanitized' => 'New description text',
            ]);

        $repository = $this->app->make(MovieRepository::class);

        // Act: Execute job
        $job = new RegenerateMovieDescriptionJob($movie->id, $currentDescription->id);
        $job->handle($openAiClient, $repository, $validator);

        // Assert: New description has version 4 (max + 1)
        $newDescription = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', Locale::EN_US)
            ->where('context_tag', ContextTag::DEFAULT)
            ->whereNull('archived_at')
            ->first();

        $this->assertNotNull($newDescription);
        $this->assertEquals(4, $newDescription->version_number, 'New description should have version 4 (max + 1)');
    }

    public function test_regenerating_description_updates_related_reports_to_resolved(): void
    {
        // Arrange: Create movie, description, and reports
        $movie = Movie::factory()->create();
        $description = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => Locale::EN_US,
            'context_tag' => ContextTag::DEFAULT,
            'text' => 'Test description',
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
            'ai_model' => 'gpt-4o-mini',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $report1 = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'description_id' => $description->id,
            'status' => ReportStatus::VERIFIED,
        ]);

        $report2 = MovieReport::factory()->create([
            'movie_id' => $movie->id,
            'description_id' => $description->id,
            'status' => ReportStatus::VERIFIED,
        ]);

        // Mock OpenAI client
        $openAiClient = $this->createMock(OpenAiClientInterface::class);
        $openAiClient->expects($this->once())
            ->method('generateMovieDescription')
            ->willReturn([
                'success' => true,
                'description' => 'New description text',
                'model' => 'gpt-4o-mini',
            ]);

        // Mock validator
        $validator = $this->createMock(AiOutputValidator::class);
        $validator->expects($this->once())
            ->method('validateAndSanitizeDescription')
            ->willReturn([
                'valid' => true,
                'sanitized' => 'New description text',
            ]);

        $repository = $this->app->make(MovieRepository::class);

        // Act: Execute job
        $job = new RegenerateMovieDescriptionJob($movie->id, $description->id);
        $job->handle($openAiClient, $repository, $validator);

        // Assert: Reports are updated to RESOLVED
        $report1->refresh();
        $report2->refresh();
        $this->assertEquals(ReportStatus::RESOLVED, $report1->status);
        $this->assertEquals(ReportStatus::RESOLVED, $report2->status);
        $this->assertNotNull($report1->resolved_at);
        $this->assertNotNull($report2->resolved_at);
    }
}
