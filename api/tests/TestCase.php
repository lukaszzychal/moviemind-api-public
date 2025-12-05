<?php

namespace Tests;

use App\Services\EntityVerificationServiceInterface;
use App\Services\OpenAiClientInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Doubles\Services\FakeEntityVerificationService;
use Tests\Doubles\Services\FakeOpenAiClient;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bind a fake implementation to an interface in the service container.
     *
     * Framework-agnostic helper for dependency injection in tests.
     *
     * @param  string  $interface  Interface class name
     * @param  object  $fake  Fake implementation instance
     */
    protected function bindFake(string $interface, object $fake): void
    {
        $this->app->instance($interface, $fake);
    }

    /**
     * Create and bind a fake EntityVerificationService.
     */
    protected function fakeEntityVerificationService(): FakeEntityVerificationService
    {
        $fake = new FakeEntityVerificationService;
        $this->bindFake(EntityVerificationServiceInterface::class, $fake);

        return $fake;
    }

    /**
     * Create and bind a fake OpenAiClient.
     */
    protected function fakeOpenAiClient(): FakeOpenAiClient
    {
        $fake = new FakeOpenAiClient;
        $this->bindFake(OpenAiClientInterface::class, $fake);

        return $fake;
    }
}
