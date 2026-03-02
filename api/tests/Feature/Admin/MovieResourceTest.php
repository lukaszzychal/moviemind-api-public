<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MovieResource;
use App\Filament\Resources\MovieResource\Pages\ListMovies;
use App\Jobs\MockGenerateMovieJob;
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

        // THEN: A generation job should be pushed (Real or Mock depending on AI_SERVICE)
        $this->assertTrue(
            Queue::pushed(RealGenerateMovieJob::class)->contains(fn (RealGenerateMovieJob $job) => $job->slug === $movie->slug)
            || Queue::pushed(MockGenerateMovieJob::class)->contains(fn (MockGenerateMovieJob $job) => $job->slug === $movie->slug),
            'Expected RealGenerateMovieJob or MockGenerateMovieJob to be pushed for movie slug: '.$movie->slug
        );
    }
}
