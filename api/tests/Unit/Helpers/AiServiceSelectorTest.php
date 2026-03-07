<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\AiServiceSelector;
use App\Jobs\MockGenerateMovieJob;
use App\Jobs\RealGenerateMovieJob;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiServiceSelectorTest extends TestCase
{
    public function test_get_job_class_returns_real_job_when_config_is_real(): void
    {
        Config::set('services.ai.service', 'real');

        $jobClass = AiServiceSelector::getJobClass(RealGenerateMovieJob::class, MockGenerateMovieJob::class);

        $this->assertSame(RealGenerateMovieJob::class, $jobClass);
    }

    public function test_get_job_class_returns_mock_job_when_config_is_mock(): void
    {
        Config::set('services.ai.service', 'mock');

        $jobClass = AiServiceSelector::getJobClass(RealGenerateMovieJob::class, MockGenerateMovieJob::class);

        $this->assertSame(MockGenerateMovieJob::class, $jobClass);
    }

    public function test_get_job_class_throws_when_config_is_invalid(): void
    {
        Config::set('services.ai.service', 'invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AI service: invalid. Must be mock or real.');

        AiServiceSelector::getJobClass(RealGenerateMovieJob::class, MockGenerateMovieJob::class);
    }
}
