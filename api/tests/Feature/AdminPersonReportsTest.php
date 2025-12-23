<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Person;
use App\Models\PersonReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPersonReportsTest extends TestCase
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
    }

    public function test_admin_can_list_person_reports(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Test report 1',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Test report 2',
            'status' => ReportStatus::PENDING,
            'priority_score' => 1.5,
        ]);
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::OTHER,
            'message' => 'Test report 3',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.5,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?type=person');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'entity_type',
                        'person_id',
                        'bio_id',
                        'type',
                        'message',
                        'suggested_fix',
                        'status',
                        'priority_score',
                        'verified_by',
                        'verified_at',
                        'resolved_at',
                        'created_at',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(3, count($data));
        // All should be person reports
        foreach ($data as $report) {
            $this->assertEquals('person', $report['entity_type']);
        }
    }

    public function test_admin_can_list_all_reports_including_person(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Person report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?type=all');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));

        // Should contain both movie and person reports
        $hasPersonReport = collect($data)->contains(fn ($report) => $report['entity_type'] === 'person');
        $this->assertTrue($hasPersonReport, 'Response should include person reports');
    }

    public function test_admin_can_filter_person_reports_by_status(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Pending report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 3.0,
        ]);
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Verified report',
            'status' => ReportStatus::VERIFIED,
            'priority_score' => 1.5,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?type=person&status=pending');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        foreach ($data as $report) {
            $this->assertEquals('person', $report['entity_type']);
            $this->assertEquals(ReportStatus::PENDING->value, $report['status']);
        }
    }

    public function test_admin_can_filter_person_reports_by_priority(): void
    {
        $person = Person::first();
        if ($person === null) {
            $person = Person::create([
                'name' => 'Test Person',
                'slug' => 'test-person',
            ]);
        }

        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'High priority',
            'status' => ReportStatus::PENDING,
            'priority_score' => 5.0,
        ]);
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Low priority',
            'status' => ReportStatus::PENDING,
            'priority_score' => 1.0,
        ]);

        $response = $this->getJson('/api/v1/admin/reports?type=person&priority=high');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        foreach ($data as $report) {
            $this->assertEquals('person', $report['entity_type']);
            $this->assertGreaterThanOrEqual(3.0, (float) $report['priority_score']);
        }
    }
}
