<?php

declare(strict_types=1);

namespace App\Features;

/**
 * TMDb API verification for movies and people before AI generation.
 * When enabled, verifies entity existence in TMDb before queueing AI generation job.
 */
class tmdb_verification extends BaseFeature {}
