<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Actions\QueuePersonGenerationAction;
use App\Enums\Locale;
use App\Models\Person;
use App\Models\PersonBio;
use App\Repositories\PersonRepository;
use App\Services\EntityVerificationServiceInterface;
use App\Services\PersonRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PersonRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_retrieve_person_returns_cached_result_when_available(): void
    {
        $cachedData = ['id' => 1, 'name' => 'Cached Person'];
        $cacheKey = 'person:test-slug:bio:default';
        Cache::put($cacheKey, $cachedData, 3600);

        $service = $this->createService();
        $result = $service->retrievePerson('test-slug', null);

        $this->assertTrue($result->isCached());
        $this->assertEquals($cachedData, $result->getData());
    }

    public function test_retrieve_person_returns_existing_person_when_found_locally(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
        ]);

        // Create a bio for the person (person without bio would return generationQueued)
        PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => 'Test bio',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        $service = $this->createService();
        $result = $service->retrievePerson('keanu-reeves-1964', null);

        $this->assertFalse($result->isCached());
        $this->assertTrue($result->isFound());
        $this->assertEquals($person->id, $result->getPerson()?->id);
        $this->assertNull($result->getSelectedBio());
    }

    public function test_retrieve_person_returns_person_with_selected_bio(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
        ]);

        $bio = PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => 'Test bio',
            'context_tag' => \App\Enums\ContextTag::DEFAULT,
            'origin' => \App\Enums\DescriptionOrigin::GENERATED,
        ]);

        $service = $this->createService();
        $result = $service->retrievePerson('keanu-reeves-1964', $bio->id);

        $this->assertTrue($result->isFound());
        $this->assertEquals($bio->id, $result->getSelectedBio()?->id);
    }

    public function test_retrieve_person_returns_not_found_when_bio_id_invalid(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
        ]);

        $service = $this->createService();
        $nonExistentBioId = '00000000-0000-0000-0000-000000000000'; // Non-existent UUID
        $result = $service->retrievePerson('keanu-reeves-1964', $nonExistentBioId);

        $this->assertFalse($result->isFound());
        $this->assertTrue($result->isBioNotFound());
    }

    public function test_retrieve_person_returns_not_found_when_person_not_found(): void
    {
        Feature::define('ai_bio_generation', false);

        $service = $this->createService();
        $result = $service->retrievePerson('non-existent-person', null);

        $this->assertTrue($result->isNotFound());
        $this->assertEquals(404, $result->getErrorCode());
    }

    private function createService(): PersonRetrievalService
    {
        $personRepository = $this->app->make(PersonRepository::class);
        $tmdbService = $this->createMock(EntityVerificationServiceInterface::class);
        $queueAction = $this->createMock(QueuePersonGenerationAction::class);

        return new PersonRetrievalService(
            $personRepository,
            $tmdbService,
            $queueAction
        );
    }
}
