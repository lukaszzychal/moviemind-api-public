<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Jobs\RegeneratePersonBioJob;
use App\Models\Person;
use App\Models\PersonBio;
use App\Models\PersonReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminPersonReportVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Bypass Admin API auth for tests
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');
        Queue::fake();
    }

    public function test_admin_can_verify_person_report(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        $bio = PersonBio::where('person_id', $person->id)->first();
        if ($bio === null) {
            $bio = PersonBio::create([
                'person_id' => $person->id,
                'locale' => 'en-US',
                'text' => 'Test biography',
                'context_tag' => 'DEFAULT',
                'origin' => 'GENERATED',
            ]);
        }

        $report = PersonReport::create([
            'person_id' => $person->id,
            'bio_id' => $bio->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Test report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);

        $response = $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        $response->assertOk()
            ->assertJson([
                'id' => $report->id,
                'entity_type' => 'person',
                'person_id' => $person->id,
                'bio_id' => $bio->id,
                'status' => ReportStatus::VERIFIED->value,
            ]);

        $report->refresh();
        $this->assertEquals(ReportStatus::VERIFIED, $report->status);
        $this->assertNotNull($report->verified_at);
    }

    public function test_verification_queues_regeneration_job(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        $bio = PersonBio::where('person_id', $person->id)->first();
        if ($bio === null) {
            $bio = PersonBio::create([
                'person_id' => $person->id,
                'locale' => 'en-US',
                'text' => 'Test biography',
                'context_tag' => 'DEFAULT',
                'origin' => 'GENERATED',
            ]);
        }

        $report = PersonReport::create([
            'person_id' => $person->id,
            'bio_id' => $bio->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Test report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);

        $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        Queue::assertPushed(RegeneratePersonBioJob::class, function ($job) use ($person, $bio) {
            return $job->personId === $person->id
                && $job->bioId === $bio->id;
        });
    }

    public function test_verification_returns_404_for_nonexistent_person_report(): void
    {
        $response = $this->postJson('/api/v1/admin/reports/00000000-0000-0000-0000-000000000000/verify');

        $response->assertStatus(404);
    }

    public function test_verification_does_not_queue_job_if_bio_not_found(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        $report = PersonReport::create([
            'person_id' => $person->id,
            'bio_id' => null, // No bio
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Test report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);

        $this->postJson("/api/v1/admin/reports/{$report->id}/verify");

        // Should still verify, but may not queue job if bio_id is null
        $report->refresh();
        $this->assertEquals(ReportStatus::VERIFIED, $report->status);

        // Job should not be queued if bio_id is null
        Queue::assertNotPushed(RegeneratePersonBioJob::class);
    }
}
