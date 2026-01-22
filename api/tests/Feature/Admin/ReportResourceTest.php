<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ReportResource;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Models\MovieReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'admin@moviemind.local']);

        // Ensure migrations are run
        $this->artisan('migrate');
    }

    public function test_can_list_reports(): void
    {
        $this->withoutExceptionHandling();

        // GIVEN: Reports exist in the database
        MovieReport::factory()->count(3)->create();

        // WHEN: The admin visits the report list page
        $this->actingAs($this->admin)
            ->get(ReportResource::getUrl('index'))
            ->assertSuccessful();

        // THEN: The reports should be visible in the table
        Livewire::test(ListReports::class)
            ->assertCanSeeTableRecords(\App\Models\Report::all());
    }

    public function test_report_list_is_read_only(): void
    {
        // GIVEN: The report list page
        $livewire = Livewire::test(ListReports::class);

        // THEN: The create action should not exist
        $livewire->assertDontSeeHtml('Create Report');

        // AND: Bulk actions should not exist
        $livewire->assertDontSeeHtml('Delete selected');
    }
}
