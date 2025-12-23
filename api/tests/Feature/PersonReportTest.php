<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Person;
use App\Models\PersonBio;
use App\Models\PersonReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    public function test_user_can_report_person(): void
    {
        $person = Person::first();
        $this->assertNotNull($person, 'Person should exist from seed');

        $response = $this->postJson("/api/v1/people/{$person->slug}/report", [
            'type' => 'factual_error',
            'message' => 'The birth date is incorrect.',
            'suggested_fix' => 'Should be 1964, not 1965.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'person_id',
                    'type',
                    'message',
                    'status',
                    'priority_score',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('person_reports', [
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR->value,
            'message' => 'The birth date is incorrect.',
            'status' => ReportStatus::PENDING->value,
        ]);
    }

    public function test_report_automatically_calculates_priority_score(): void
    {
        $person = Person::first();
        $this->assertNotNull($person, 'Person should exist from seed');

        // Create first report
        $response1 = $this->postJson("/api/v1/people/{$person->slug}/report", [
            'type' => 'factual_error',
            'message' => 'First report',
        ]);

        $response1->assertStatus(201);
        $firstReport = PersonReport::where('person_id', $person->id)->first();
        $this->assertEquals(3.0, $firstReport->priority_score); // FACTUAL_ERROR weight = 3.0

        // Create second report of same type
        $response2 = $this->postJson("/api/v1/people/{$person->slug}/report", [
            'type' => 'factual_error',
            'message' => 'Second report',
        ]);

        $response2->assertStatus(201);
        $firstReport->refresh();
        $secondReport = PersonReport::where('person_id', $person->id)
            ->where('id', '!=', $firstReport->id)
            ->first();

        // Both should have updated priority score: 2 reports * 3.0 weight = 6.0
        $this->assertEquals(6.0, $firstReport->priority_score);
        $this->assertEquals(6.0, $secondReport->priority_score);
    }

    public function test_report_validates_required_fields(): void
    {
        $person = Person::first();
        $this->assertNotNull($person, 'Person should exist from seed');

        $response = $this->postJson("/api/v1/people/{$person->slug}/report", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'message']);
    }

    public function test_report_validates_type_enum(): void
    {
        $person = Person::first();
        $this->assertNotNull($person, 'Person should exist from seed');

        $response = $this->postJson("/api/v1/people/{$person->slug}/report", [
            'type' => 'invalid_type',
            'message' => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_report_can_include_bio_id(): void
    {
        $person = Person::first();
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

        $response = $this->postJson("/api/v1/people/{$person->slug}/report", [
            'type' => 'grammar_error',
            'message' => 'Grammar issue in bio',
            'bio_id' => $bio->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('person_reports', [
            'person_id' => $person->id,
            'bio_id' => $bio->id,
        ]);
    }

    public function test_report_returns_404_for_nonexistent_person(): void
    {
        $response = $this->postJson('/api/v1/people/nonexistent-person/report', [
            'type' => 'factual_error',
            'message' => 'Test message',
        ]);

        $response->assertStatus(404);
    }
}
