<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RapidApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RapidApiServiceTest extends TestCase
{
    use RefreshDatabase;

    private RapidApiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RapidApiService;
    }

    public function test_map_rapid_api_plan_maps_basic_to_free(): void
    {
        $result = $this->service->mapRapidApiPlan('basic');

        $this->assertEquals('free', $result);
    }

    public function test_map_rapid_api_plan_maps_pro_to_pro(): void
    {
        $result = $this->service->mapRapidApiPlan('pro');

        $this->assertEquals('pro', $result);
    }

    public function test_map_rapid_api_plan_maps_ultra_to_enterprise(): void
    {
        $result = $this->service->mapRapidApiPlan('ultra');

        $this->assertEquals('enterprise', $result);
    }

    public function test_map_rapid_api_plan_returns_null_for_unknown_plan(): void
    {
        $result = $this->service->mapRapidApiPlan('unknown');

        $this->assertNull($result);
    }

    public function test_map_rapid_api_plan_returns_null_for_null(): void
    {
        $result = $this->service->mapRapidApiPlan(null);

        $this->assertNull($result);
    }

    public function test_map_rapid_api_plan_handles_case_insensitive(): void
    {
        $result = $this->service->mapRapidApiPlan('BASIC');

        $this->assertEquals('free', $result);
    }

    public function test_validate_proxy_secret_returns_true_when_verification_disabled(): void
    {
        config(['rapidapi.verify_proxy_secret' => false]);

        $result = $this->service->validateProxySecret('any-secret');

        $this->assertTrue($result);
    }

    public function test_validate_proxy_secret_returns_false_when_no_secret_configured(): void
    {
        config([
            'rapidapi.verify_proxy_secret' => true,
            'rapidapi.proxy_secret' => null,
        ]);

        $result = $this->service->validateProxySecret('any-secret');

        $this->assertFalse($result);
    }

    public function test_validate_proxy_secret_returns_false_when_no_secret_provided(): void
    {
        config([
            'rapidapi.verify_proxy_secret' => true,
            'rapidapi.proxy_secret' => 'expected-secret',
        ]);

        $result = $this->service->validateProxySecret(null);

        $this->assertFalse($result);
    }

    public function test_validate_proxy_secret_returns_true_for_valid_secret(): void
    {
        config([
            'rapidapi.verify_proxy_secret' => true,
            'rapidapi.proxy_secret' => 'expected-secret',
        ]);

        $result = $this->service->validateProxySecret('expected-secret');

        $this->assertTrue($result);
    }

    public function test_validate_proxy_secret_returns_false_for_invalid_secret(): void
    {
        config([
            'rapidapi.verify_proxy_secret' => true,
            'rapidapi.proxy_secret' => 'expected-secret',
        ]);

        $result = $this->service->validateProxySecret('wrong-secret');

        $this->assertFalse($result);
    }

    public function test_get_rapid_api_user_returns_user_id_from_header(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-RapidAPI-User', 'user-123');

        $result = $this->service->getRapidApiUser($request);

        $this->assertEquals('user-123', $result);
    }

    public function test_get_rapid_api_user_returns_null_when_header_missing(): void
    {
        $request = Request::create('/test', 'GET');

        $result = $this->service->getRapidApiUser($request);

        $this->assertNull($result);
    }

    public function test_get_rapid_api_subscription_returns_plan_from_header(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-RapidAPI-Subscription', 'pro');

        $result = $this->service->getRapidApiSubscription($request);

        $this->assertEquals('pro', $result);
    }

    public function test_get_rapid_api_subscription_returns_null_when_header_missing(): void
    {
        $request = Request::create('/test', 'GET');

        $result = $this->service->getRapidApiSubscription($request);

        $this->assertNull($result);
    }

    public function test_is_rapid_api_request_returns_true_when_user_header_present(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-RapidAPI-User', 'user-123');

        $result = $this->service->isRapidApiRequest($request);

        $this->assertTrue($result);
    }

    public function test_is_rapid_api_request_returns_true_when_subscription_header_present(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-RapidAPI-Subscription', 'pro');

        $result = $this->service->isRapidApiRequest($request);

        $this->assertTrue($result);
    }

    public function test_is_rapid_api_request_returns_true_when_proxy_secret_header_present(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-RapidAPI-Proxy-Secret', 'secret');

        $result = $this->service->isRapidApiRequest($request);

        $this->assertTrue($result);
    }

    public function test_is_rapid_api_request_returns_false_when_no_headers_present(): void
    {
        $request = Request::create('/test', 'GET');

        $result = $this->service->isRapidApiRequest($request);

        $this->assertFalse($result);
    }
}
