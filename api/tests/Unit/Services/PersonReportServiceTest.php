<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Person;
use App\Models\PersonReport;
use App\Services\PersonReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private PersonReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new PersonReportService;
    }

    public function test_calculate_priority_score_for_single_report(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);
        $report = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Test report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $score = $this->service->calculatePriorityScore($report);

        // FACTUAL_ERROR weight = 3.0, count = 1
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_increases_with_multiple_reports(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);

        // Create first report
        $report1 = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'First report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        // Create second report of same type
        $report2 = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Second report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        // Both should have same priority score: 2 reports * 3.0 weight = 6.0
        $score1 = $this->service->calculatePriorityScore($report1);
        $score2 = $this->service->calculatePriorityScore($report2);

        $this->assertEquals(6.0, $score1);
        $this->assertEquals(6.0, $score2);
    }

    public function test_calculate_priority_score_only_counts_pending_reports(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);

        // Create pending report
        $pendingReport = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Pending report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        // Create resolved report (should not be counted)
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Resolved report',
            'status' => ReportStatus::RESOLVED,
            'priority_score' => 0.0,
        ]);

        $score = $this->service->calculatePriorityScore($pendingReport);

        // Only pending report counts: 1 report * 1.5 weight = 1.5
        $this->assertEquals(1.5, $score);
    }

    public function test_calculate_priority_score_only_counts_same_type(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);

        // Create FACTUAL_ERROR report
        $factualReport = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Factual error',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        // Create GRAMMAR_ERROR report (different type, should not be counted)
        PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Grammar error',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $score = $this->service->calculatePriorityScore($factualReport);

        // Only FACTUAL_ERROR report counts: 1 report * 3.0 weight = 3.0
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_only_counts_same_person(): void
    {
        $person1 = Person::create([
            'name' => 'Test Person 1',
            'slug' => 'test-person-1',
        ]);
        $person2 = Person::create([
            'name' => 'Test Person 2',
            'slug' => 'test-person-2',
        ]);

        // Create report for person1
        $report1 = PersonReport::create([
            'person_id' => $person1->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Person1 report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        // Create report for person2 (different person, should not be counted)
        PersonReport::create([
            'person_id' => $person2->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Person2 report',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $score = $this->service->calculatePriorityScore($report1);

        // Only person1's report counts: 1 report * 3.0 weight = 3.0
        $this->assertEquals(3.0, $score);
    }

    public function test_calculate_priority_score_with_different_report_types(): void
    {
        $person = Person::create([
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);

        // Create reports with different types
        $factualReport = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::FACTUAL_ERROR,
            'message' => 'Factual error',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $grammarReport = PersonReport::create([
            'person_id' => $person->id,
            'type' => ReportType::GRAMMAR_ERROR,
            'message' => 'Grammar error',
            'status' => ReportStatus::PENDING,
            'priority_score' => 0.0,
        ]);

        $factualScore = $this->service->calculatePriorityScore($factualReport);
        $grammarScore = $this->service->calculatePriorityScore($grammarReport);

        // FACTUAL_ERROR: 1 report * 3.0 weight = 3.0
        $this->assertEquals(3.0, $factualScore);
        // GRAMMAR_ERROR: 1 report * 1.5 weight = 1.5
        $this->assertEquals(1.5, $grammarScore);
    }
}
