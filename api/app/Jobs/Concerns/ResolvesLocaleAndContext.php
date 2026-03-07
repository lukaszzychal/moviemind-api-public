<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\ContextTag;
use App\Enums\Locale;
use App\Helpers\GenerationRequestNormalizer;
use Illuminate\Support\Facades\Log;

trait ResolvesLocaleAndContext
{
    /**
     * Resolve Locale enum from the requested locale string.
     * Falls back to EN_US if invalid or not provided.
     */
    protected function resolveLocale(): Locale
    {
        if (isset($this->locale) && $this->locale !== '') {
            $normalized = $this->normalizeLocale($this->locale);
            if ($normalized !== null && ($enum = Locale::tryFrom($normalized))) {
                return $enum;
            }
        }

        return Locale::EN_US;
    }

    /**
     * Normalize locale string to canonical value.
     */
    protected function normalizeLocale(string $locale): ?string
    {
        return GenerationRequestNormalizer::normalizeLocale($locale);
    }

    /**
     * Determine context tag for the entity.
     * Uses explicit contextTag if valid, otherwise falls back to next available tag.
     */
    protected function determineContextTag(object $entity, Locale $locale): string
    {
        if (isset($this->contextTag) && $this->contextTag !== '') {
            $normalized = $this->normalizeContextTag($this->contextTag);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $this->nextContextTag($entity);
    }

    /**
     * Normalize context tag to canonical value.
     */
    protected function normalizeContextTag(string $contextTag): ?string
    {
        return GenerationRequestNormalizer::normalizeContextTag($contextTag);
    }

    /**
     * Determine the next appropriate context tag based on tags the entity already has.
     */
    protected function nextContextTag(object $entity): string
    {
        $existingTags = array_map(
            fn ($tag) => $tag instanceof ContextTag ? $tag->value : (string) $tag,
            $this->getExistingContextTags($entity)
        );

        $preferredOrder = [
            ContextTag::DEFAULT->value,
            ContextTag::MODERN->value,
            ContextTag::CRITICAL->value,
            ContextTag::HUMOROUS->value,
        ];

        foreach ($preferredOrder as $candidate) {
            if (! in_array($candidate, $existingTags, true)) {
                return $candidate;
            }
        }

        // All standard tags are used - fallback to DEFAULT
        Log::warning('All standard context tags are used, falling back to DEFAULT', [
            'entity_id' => $entity->id ?? null,
            'slug' => $entity->slug ?? null,
            'existing_tags' => $existingTags,
        ]);

        return ContextTag::DEFAULT->value;
    }

    /**
     * Get a list of context tags that this entity already has.
     * Must be implemented by the using class.
     *
     * @return array<string|ContextTag>
     */
    abstract protected function getExistingContextTags(object $entity): array;
}
