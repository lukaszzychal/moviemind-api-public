<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContextTag;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL-specific tests.
 *
 * These tests verify features that are only available in PostgreSQL:
 * - Partial unique indexes
 * - JSON/JSONB operations
 * - Array types (if used)
 *
 * These tests are skipped in SQLite and should be run in CI with PostgreSQL.
 */
class PostgreSQLSpecificTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Skip all tests in this class if not using PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test suite requires PostgreSQL');
        }
    }

    /**
     * Test partial unique index for movie descriptions.
     *
     * Verifies that only one active (non-archived) description can exist
     * per (movie_id, locale, context_tag) combination.
     */
    public function test_partial_unique_index_prevents_duplicate_active_descriptions(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create first active description
        $firstDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'First active description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null, // Active
        ]);

        // Try to create duplicate active description - should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/unique|duplicate/i');

        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Duplicate active description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 2,
            'archived_at' => null, // Also active - should fail
        ]);
    }

    /**
     * Test that partial unique index allows multiple archived descriptions.
     *
     * Verifies that archived descriptions don't violate the unique constraint,
     * allowing version history to be maintained.
     */
    public function test_partial_unique_index_allows_multiple_archived_descriptions(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create first active description
        $activeDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Active description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null, // Active
        ]);

        // Archive the first description
        $activeDescription->update(['archived_at' => now()]);

        // Create new active description - should succeed
        $newActiveDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'New active description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 2,
            'archived_at' => null, // Active
        ]);

        $this->assertNotNull($newActiveDescription->id);
        $this->assertDatabaseHas('movie_descriptions', [
            'id' => $activeDescription->id,
            'archived_at' => $activeDescription->archived_at,
        ]);
        $this->assertDatabaseHas('movie_descriptions', [
            'id' => $newActiveDescription->id,
            'archived_at' => null,
        ]);

        // Verify both descriptions exist
        $descriptions = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', 'en-US')
            ->where('context_tag', ContextTag::MODERN->value)
            ->get();

        $this->assertCount(2, $descriptions);
        $this->assertEquals(1, $descriptions->whereNull('archived_at')->count(), 'Should have exactly one active description');
        $this->assertEquals(1, $descriptions->whereNotNull('archived_at')->count(), 'Should have exactly one archived description');
    }

    /**
     * Test that partial unique index allows different context tags.
     *
     * Verifies that different context tags don't violate the unique constraint.
     */
    public function test_partial_unique_index_allows_different_context_tags(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions for this movie
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create descriptions with different context tags - all should succeed
        $modernDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Modern description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $criticalDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Critical description.',
            'context_tag' => ContextTag::CRITICAL->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $humorousDescription = MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'Humorous description.',
            'context_tag' => ContextTag::HUMOROUS->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        // All should exist
        $this->assertDatabaseHas('movie_descriptions', ['id' => $modernDescription->id]);
        $this->assertDatabaseHas('movie_descriptions', ['id' => $criticalDescription->id]);
        $this->assertDatabaseHas('movie_descriptions', ['id' => $humorousDescription->id]);

        // Verify all are active
        $activeCount = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', 'en-US')
            ->whereNull('archived_at')
            ->count();

        $this->assertEquals(3, $activeCount, 'Should have 3 active descriptions with different context tags');
    }

    /**
     * Test JSON/JSONB operations in ai_jobs table.
     *
     * Verifies that JSON columns work correctly in PostgreSQL.
     */
    public function test_jsonb_operations_in_ai_jobs(): void
    {
        // Create a job with JSON payload
        $payload = [
            'entity_type' => 'MOVIE',
            'entity_id' => 123,
            'locale' => 'en-US',
            'context_tag' => 'modern',
            'metadata' => [
                'source' => 'api',
                'user_id' => 456,
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $jobId = DB::table('ai_jobs')->insertGetId([
            'entity_type' => 'MOVIE',
            'entity_id' => 123,
            'locale' => 'en-US',
            'status' => 'PENDING',
            'payload_json' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Query using JSON operators
        $job = DB::table('ai_jobs')
            ->where('id', $jobId)
            ->whereRaw("payload_json->>'entity_type' = ?", ['MOVIE'])
            ->first();

        $this->assertNotNull($job);
        $this->assertEquals('MOVIE', json_decode($job->payload_json)->entity_type);

        // Query nested JSON
        $jobWithMetadata = DB::table('ai_jobs')
            ->where('id', $jobId)
            ->whereRaw("payload_json->'metadata'->>'source' = ?", ['api'])
            ->first();

        $this->assertNotNull($jobWithMetadata);
        $decoded = json_decode($jobWithMetadata->payload_json, true);
        $this->assertEquals('api', $decoded['metadata']['source']);
    }

    /**
     * Test that partial unique index works with concurrent inserts.
     *
     * This test simulates a race condition scenario where two processes
     * try to create active descriptions simultaneously.
     */
    public function test_partial_unique_index_prevents_race_condition(): void
    {
        $movie = Movie::firstOrFail();

        // Delete any existing descriptions
        MovieDescription::where('movie_id', $movie->id)->delete();

        // Create first active description
        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => 'en-US',
            'text' => 'First description.',
            'context_tag' => ContextTag::MODERN->value,
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        // Try to create duplicate in a transaction (simulating race condition)
        DB::beginTransaction();
        try {
            MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => 'en-US',
                'text' => 'Duplicate description.',
                'context_tag' => ContextTag::MODERN->value,
                'origin' => 'GENERATED',
                'ai_model' => 'mock',
                'version_number' => 2,
                'archived_at' => null,
            ]);
            DB::commit();
            $this->fail('Expected QueryException for unique constraint violation');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            $this->assertStringContainsString('unique', strtolower($e->getMessage()));
        }

        // Verify only one active description exists
        $activeCount = MovieDescription::where('movie_id', $movie->id)
            ->where('locale', 'en-US')
            ->where('context_tag', ContextTag::MODERN->value)
            ->whereNull('archived_at')
            ->count();

        $this->assertEquals(1, $activeCount, 'Should have exactly one active description after failed duplicate insert');
    }
}
