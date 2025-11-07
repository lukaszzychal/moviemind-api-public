<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Models\PersonBio;
use Illuminate\Http\Request;
use Tests\TestCase;

class PersonResourceTest extends TestCase
{
    public function test_resource_includes_links_and_relations(): void
    {
        $person = Person::make([
            'id' => 1,
            'name' => 'Keanu Reeves',
            'slug' => 'keanu-reeves',
        ]);

        $person->setRelation('bios', collect([
            PersonBio::make(['locale' => 'en-US', 'text' => 'Actor']),
        ]));

        $person->setRelation('movies', collect());

        $resource = PersonResource::make($person)->additional([
            '_links' => ['self' => 'http://example.com'],
        ]);

        $payload = $resource->toArray(new Request);

        $this->assertSame('Keanu Reeves', $payload['name']);
        $this->assertArrayHasKey('_links', $payload);
        $this->assertEquals('http://example.com', $payload['_links']['self']);
    }
}
