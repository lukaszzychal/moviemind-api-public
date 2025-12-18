<?php

declare(strict_types=1);

namespace Tests\Feature\Helpers;

use App\Models\Person;
use App\Models\PersonBio;

/**
 * Helper class for creating test people with common scenarios.
 */
class PersonTestHelper
{
    /**
     * Create a person with multiple bios.
     *
     * @param  array<int, array{locale: string, context_tag: string, text: string, origin?: string, ai_model?: string}>  $bios
     * @param  array<string, mixed>  $personAttributes
     */
    public static function createPersonWithBios(array $bios, array $personAttributes = []): Person
    {
        $defaultAttributes = [
            'name' => 'Test Person',
            'slug' => 'test-person-'.uniqid(),
        ];

        $person = Person::create(array_merge($defaultAttributes, $personAttributes));

        $firstBio = null;
        foreach ($bios as $bioData) {
            $bio = PersonBio::create([
                'person_id' => $person->id,
                'locale' => $bioData['locale'],
                'text' => $bioData['text'],
                'context_tag' => $bioData['context_tag'],
                'origin' => $bioData['origin'] ?? 'GENERATED',
                'ai_model' => $bioData['ai_model'] ?? 'mock',
            ]);

            if ($firstBio === null) {
                $firstBio = $bio;
            }
        }

        // Set first bio as default if none exists
        if ($firstBio && ! $person->default_bio_id) {
            $person->update(['default_bio_id' => $firstBio->id]);
        }

        return $person->fresh(['bios']);
    }

    /**
     * Create a person with a single bio.
     *
     * @param  array<string, mixed>  $personAttributes
     * @param  array<string, mixed>  $bioAttributes
     */
    public static function createPersonWithBio(array $personAttributes = [], array $bioAttributes = []): Person
    {
        $defaultPersonAttributes = [
            'name' => 'Test Person',
            'slug' => 'test-person-'.uniqid(),
        ];

        $defaultBioAttributes = [
            'locale' => 'en-US',
            'text' => 'Test biography',
            'context_tag' => 'DEFAULT',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ];

        $person = Person::create(array_merge($defaultPersonAttributes, $personAttributes));

        $bio = PersonBio::create(array_merge([
            'person_id' => $person->id,
        ], $defaultBioAttributes, $bioAttributes));

        $person->update(['default_bio_id' => $bio->id]);

        return $person->fresh(['bios']);
    }
}
