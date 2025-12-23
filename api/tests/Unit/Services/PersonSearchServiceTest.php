<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\PersonSearchService;
use App\Support\SearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PersonSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_search_returns_search_result(): void
    {
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->expects($this->once())
            ->method('searchPeople')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);

        $service = new PersonSearchService($personRepository, $tmdbService);

        $result = $service->search(['q' => 'Keanu']);

        $this->assertInstanceOf(SearchResult::class, $result);
    }

    public function test_search_merges_local_and_external_results(): void
    {
        // Create a local person
        $localPerson = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
            'birthplace' => 'Beirut, Lebanon',
        ]);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->expects($this->once())
            ->method('searchPeople')
            ->willReturn(Collection::make([$localPerson]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $tmdbService->expects($this->once())
            ->method('searchPeople')
            ->willReturn([
                [
                    'name' => 'Keanu Reeves',
                    'birthday' => '1964-09-02',
                    'place_of_birth' => 'Beirut, Lebanon',
                    'biography' => 'Actor known for The Matrix',
                    'id' => 6384,
                ],
            ]);

        Feature::define('tmdb_verification', true);

        $service = new PersonSearchService($personRepository, $tmdbService);

        $result = $service->search(['q' => 'Keanu']);

        $this->assertInstanceOf(SearchResult::class, $result);
        $this->assertGreaterThan(0, $result->total);
        $this->assertEquals(1, $result->localCount);
        $this->assertEquals(1, $result->externalCount);
    }

    public function test_search_filters_by_birth_year(): void
    {
        $person1964 = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
        ]);

        $person1970 = Person::create([
            'name' => 'Christopher Nolan',
            'slug' => 'christopher-nolan-1970',
            'birth_date' => '1970-07-30',
        ]);

        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->expects($this->once())
            ->method('searchPeople')
            ->willReturn(Collection::make([$person1964, $person1970]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);

        $service = new PersonSearchService($personRepository, $tmdbService);

        $result = $service->search(['q' => 'Keanu', 'birth_year' => 1964]);

        $this->assertCount(1, $result->results);
        $this->assertEquals('keanu-reeves-1964', $result->results[0]['slug']);
    }

    public function test_search_caches_results(): void
    {
        $personRepository = $this->createMock(PersonRepository::class);
        $personRepository->expects($this->once())
            ->method('searchPeople')
            ->willReturn(Collection::make([]));

        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);

        $service = new PersonSearchService($personRepository, $tmdbService);

        // First call
        $result1 = $service->search(['q' => 'Keanu']);

        // Second call (should use cache)
        $result2 = $service->search(['q' => 'Keanu']);

        $this->assertInstanceOf(SearchResult::class, $result1);
        $this->assertInstanceOf(SearchResult::class, $result2);
    }
}
