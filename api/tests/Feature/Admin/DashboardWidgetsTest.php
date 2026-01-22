<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\FailedJobsWidget;
use App\Filament\Widgets\JobsChart;
use App\Filament\Widgets\RecentJobsWidget;
use App\Filament\Widgets\StatsOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'admin@moviemind.local']);
        $this->actingAs($this->admin);
    }

    public function test_stats_overview_widget_renders(): void
    {
        Livewire::test(StatsOverview::class)
            ->assertSuccessful();
    }

    public function test_jobs_chart_widget_renders(): void
    {
        Livewire::test(JobsChart::class)
            ->assertSuccessful();
    }

    public function test_recent_jobs_widget_renders(): void
    {
        Livewire::test(RecentJobsWidget::class)
            ->assertSuccessful();
    }

    public function test_failed_jobs_widget_renders(): void
    {
        Livewire::test(FailedJobsWidget::class)
            ->assertSuccessful();
    }
}
