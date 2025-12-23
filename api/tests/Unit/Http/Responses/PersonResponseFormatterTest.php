<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Responses;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Http\Responses\PersonResponseFormatter;
use App\Models\Person;
use App\Models\PersonBio;
use App\Services\HateoasService;
use App\Services\PersonDisambiguationService;
use App\Support\PersonRetrievalResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class PersonResponseFormatterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_format_success_returns_json_with_person_data(): void
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
            'context_tag' => ContextTag::DEFAULT,
            'origin' => DescriptionOrigin::GENERATED,
        ]);

        $formatter = $this->createFormatter();
        $response = $formatter->formatSuccess($person, 'keanu-reeves-1964', null);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('slug', $data);
        $this->assertArrayHasKey('bios', $data);
        $this->assertArrayHasKey('_links', $data);
    }

    public function test_format_success_includes_selected_bio_when_provided(): void
    {
        $person = Person::create([
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves-1964',
            'birth_date' => '1964-09-02',
        ]);

        $bio1 = PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => 'Bio 1',
            'context_tag' => ContextTag::DEFAULT,
            'origin' => DescriptionOrigin::GENERATED,
        ]);

        $bio2 = PersonBio::create([
            'person_id' => $person->id,
            'locale' => Locale::EN_US,
            'text' => 'Bio 2',
            'context_tag' => ContextTag::CRITICAL,
            'origin' => DescriptionOrigin::GENERATED,
        ]);

        $formatter = $this->createFormatter();
        $response = $formatter->formatSuccess($person, 'keanu-reeves-1964', $bio2);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('selected_bio', $data);
        $this->assertEquals($bio2->id, $data['selected_bio']['id']);
    }

    public function test_format_error_returns_json_with_error_message(): void
    {
        $formatter = $this->createFormatter();
        $response = $formatter->formatError('Test error', 400);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test error', $data['error']);
    }

    public function test_format_error_includes_additional_data(): void
    {
        $formatter = $this->createFormatter();
        $response = $formatter->formatError('Test error', 400, ['field' => 'value']);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test error', $data['error']);
        $this->assertEquals('value', $data['field']);
    }

    public function test_format_bio_not_found_returns_404(): void
    {
        $formatter = $this->createFormatter();
        $response = $formatter->formatBioNotFound();

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Bio not found for person', $data['error']);
    }

    public function test_format_not_found_returns_404(): void
    {
        $formatter = $this->createFormatter();
        $response = $formatter->formatNotFound();

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Person not found', $data['error']);
    }

    public function test_format_not_found_with_custom_message(): void
    {
        $formatter = $this->createFormatter();
        $response = $formatter->formatNotFound('Custom message');

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Custom message', $data['error']);
    }

    public function test_format_generation_queued_returns_202(): void
    {
        $formatter = $this->createFormatter();
        $generationResult = [
            'job_id' => '123',
            'status' => 'PENDING',
            'slug' => 'test-person',
        ];
        $response = $formatter->formatGenerationQueued($generationResult);

        $this->assertEquals(202, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('123', $data['job_id']);
    }

    public function test_format_disambiguation_returns_300(): void
    {
        $formatter = $this->createFormatter();
        $options = [
            ['slug' => 'person-1', 'name' => 'Person 1'],
            ['slug' => 'person-2', 'name' => 'Person 2'],
        ];
        $response = $formatter->formatDisambiguation('test-person', $options);

        $this->assertEquals(300, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Multiple people found', $data['error']);
        $this->assertArrayHasKey('options', $data);
        $this->assertCount(2, $data['options']);
    }

    public function test_format_invalid_slug_returns_400(): void
    {
        $formatter = $this->createFormatter();
        $validation = [
            'reason' => 'Invalid format',
            'confidence' => 0.5,
        ];
        $response = $formatter->formatInvalidSlug('invalid-slug', $validation);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid slug format', $data['error']);
        $this->assertEquals('Invalid format', $data['message']);
        $this->assertEquals(0.5, $data['confidence']);
    }

    public function test_format_from_result_with_cached_data(): void
    {
        $formatter = $this->createFormatter();
        $cachedData = ['id' => '123', 'name' => 'Cached Person'];
        $result = PersonRetrievalResult::fromCache($cachedData);
        $response = $formatter->formatFromResult($result, 'test-person');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('123', $data['id']);
    }

    public function test_format_from_result_with_found_person(): void
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
            'context_tag' => ContextTag::DEFAULT,
            'origin' => DescriptionOrigin::GENERATED,
        ]);

        $formatter = $this->createFormatter();
        $result = PersonRetrievalResult::found($person, null);
        $response = $formatter->formatFromResult($result, 'keanu-reeves-1964');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($person->id, $data['id']);
    }

    public function test_format_from_result_with_bio_not_found(): void
    {
        $formatter = $this->createFormatter();
        $result = PersonRetrievalResult::bioNotFound();
        $response = $formatter->formatFromResult($result, 'test-person');

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Bio not found for person', $data['error']);
    }

    public function test_format_from_result_with_not_found(): void
    {
        $formatter = $this->createFormatter();
        $result = PersonRetrievalResult::notFound();
        $response = $formatter->formatFromResult($result, 'test-person');

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Person not found', $data['error']);
    }

    public function test_format_from_result_with_generation_queued(): void
    {
        $formatter = $this->createFormatter();
        $generationResult = ['job_id' => '123', 'status' => 'PENDING'];
        $result = PersonRetrievalResult::generationQueued($generationResult);
        $response = $formatter->formatFromResult($result, 'test-person');

        $this->assertEquals(202, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('123', $data['job_id']);
    }

    public function test_format_from_result_with_disambiguation(): void
    {
        $formatter = $this->createFormatter();
        $options = [['slug' => 'person-1', 'name' => 'Person 1']];
        $result = PersonRetrievalResult::disambiguation('test-person', $options);
        $response = $formatter->formatFromResult($result, 'test-person');

        $this->assertEquals(300, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Multiple people found', $data['error']);
    }

    public function test_format_from_result_with_invalid_slug(): void
    {
        $formatter = $this->createFormatter();
        $validation = ['reason' => 'Invalid', 'confidence' => 0.5];
        $result = PersonRetrievalResult::invalidSlug('invalid-slug', $validation);
        $response = $formatter->formatFromResult($result, 'invalid-slug');

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid slug format', $data['error']);
    }

    private function createFormatter(): PersonResponseFormatter
    {
        $hateoas = $this->app->make(HateoasService::class);
        $disambiguationService = $this->app->make(PersonDisambiguationService::class);

        return new PersonResponseFormatter($hateoas, $disambiguationService);
    }
}
