<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MovieResource;
use App\Filament\Resources\MovieResource\Pages\ListMovies;
use App\Jobs\RealGenerateMovieJob;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MovieResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'admin@moviemind.local']);
    }

    public function test_can_list_movies(): void
    {
        // GIVEN: Movies exist in the database
        $movies = Movie::factory()->count(3)->create();

        // WHEN: The admin visits the movie list page
        $this->actingAs($this->admin)
            ->get(MovieResource::getUrl('index'))
            ->assertSuccessful();

        // THEN: The movies should be visible in the table
        Livewire::test(ListMovies::class)
            ->assertCanSeeTableRecords($movies);
    }

    public function test_generate_ai_action_triggers_job(): void
    {
        // GIVEN: A movie exists
        $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);

        // AND: Queue is faked
        Queue::fake();

        // WHEN: The admin triggers the 'generate' action on the movie
        Livewire::test(ListMovies::class)
            ->callTableAction('generate', $movie, data: [
                'locale' => 'pl-PL',
                'context_tag' => 'short',
            ]);

        // THEN: A generation job should be pushed to the queue
        // Note: The exact job class depends on feature flags and configuration
        // Assuming RealGenerateMovieJob or MockGenerateMovieJob based on test config
        // For simplicity, we check if ANY job was pushed, or specific one if we know it

        // Since we are in test env, it might use Mock job or Real job depending on setup
        // Let's check if QueueMovieGenerationAction dispatched something
        Queue::assertPushed(function ($job) use ($movie) {
            // Check for either Real or Mock job
            return str_contains(get_class($job), 'GenerateMovieJob') &&
                   $job->movie->id === $movie->id;
        });
    }
}
