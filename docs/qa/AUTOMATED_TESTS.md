# MovieMind API - Automated Tests Guide

> **For:** Developers, QA Engineers  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides a comprehensive guide to automated testing in MovieMind API, including test structure, examples, best practices, and how to run tests.

**Note:** This is a portfolio/demo project. For production deployment, see [Production Testing](#production-testing).

---

## 📁 Test Structure

### Directory Organization

```
api/tests/
├── Feature/              # Feature tests (API endpoints, integrations)
│   ├── Admin/            # Admin panel tests
│   ├── Console/          # Console command tests
│   └── Helpers/          # Test helpers
├── Unit/                 # Unit tests (classes, services, helpers)
├── Doubles/              # Test doubles (fakes, mocks)
│   └── Services/         # Fake service implementations
└── Manual/               # Manual test scripts

tests/e2e/
├── specs/                # Playwright test specs
└── global-setup.ts       # E2E test setup
```

---

## 🧪 Unit Tests

### Location

`api/tests/Unit/`

### Purpose

Test individual classes and methods in isolation.

### Characteristics

- Fast execution (< 1 second per test)
- No database (or in-memory SQLite)
- No HTTP requests
- Mock external dependencies

### Example: Service Test

```php
<?php

namespace Tests\Unit\Services;

use App\Services\TvmazeVerificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TvmazeVerificationServiceTest extends TestCase
{
    public function test_verify_tv_series_returns_data_when_found(): void
    {
        Http::fake([
            'api.tvmaze.com/singlesearch/shows*' => Http::response([
                'id' => 169,
                'name' => 'Breaking Bad',
                'premiered' => '2008-01-20',
            ], 200),
        ]);

        $service = new TvmazeVerificationService();
        $result = $service->verifyTvSeries('breaking-bad-2008');

        $this->assertNotNull($result);
        $this->assertSame('Breaking Bad', $result['name']);
    }
}
```

### Example: Repository Test

```php
<?php

namespace Tests\Unit\Repositories;

use App\Models\Movie;
use App\Repositories\MovieRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_by_slug_returns_movie(): void
    {
        $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);

        $repository = new MovieRepository();
        $result = $repository->findBySlug('the-matrix-1999');

        $this->assertNotNull($result);
        $this->assertSame($movie->id, $result->id);
    }
}
```

---

## 🔌 Feature Tests

### Location

`api/tests/Feature/`

### Purpose

Test API endpoints end-to-end and integrations between components.

### Characteristics

- Use test database (SQLite in-memory)
- Real HTTP requests (via Laravel test client)
- Mock external APIs
- Slower execution (1-5 seconds per test)

### Example: API Endpoint Test

```php
<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_movie_by_slug(): void
    {
        // Arrange
        $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);

        // Act
        $response = $this->getJson('/api/v1/movies/the-matrix-1999');

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'slug' => 'the-matrix-1999',
                    'title' => $movie->title,
                ],
            ]);
    }
}
```

### Example: Authentication Test

```php
<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/movies');

        $response->assertUnauthorized()
            ->assertJson(['error' => 'unauthorized']);
    }

    public function test_returns_200_with_valid_api_key(): void
    {
        $apiKey = ApiKey::factory()->create();

        $response = $this->getJson('/api/v1/movies', [
            'X-API-Key' => $apiKey->key,
        ]);

        $response->assertOk();
    }
}
```

### Example: Rate Limiting Test

```php
<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanBasedRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_limit_enforced_per_plan(): void
    {
        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $apiKey = ApiKey::factory()->create(['plan_id' => $freePlan->id]);

        // Send 10 requests (limit)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/v1/movies', [
                'X-API-Key' => $apiKey->key,
            ]);
            $response->assertOk();
        }

        // 11th request should be rate limited
        $response = $this->getJson('/api/v1/movies', [
            'X-API-Key' => $apiKey->key,
        ]);

        $response->assertStatus(429)
            ->assertJson(['error' => 'rate_limit_exceeded']);
    }
}
```

---

## 🎭 E2E Tests (Playwright)

### Location

`tests/e2e/specs/`

### Purpose

Test critical user flows with real browser.

### Characteristics

- Real browser (Playwright)
- Real HTTP requests
- Slower execution (10-30 seconds per test)
- Fewer tests (critical paths only)

### Example: Admin Panel Flow

```typescript
import { test, expect } from '@playwright/test';

test('Admin can generate movie description', async ({ page }) => {
  // Navigate to admin panel
  await page.goto('http://localhost:8000/admin/login');
  
  // Login
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  
  // Navigate to movies
  await page.click('text=Movies');
  
  // Select movie
  await page.click('text=The Matrix');
  
  // Generate description
  await page.click('button:has-text("Generate Description")');
  
  // Wait for success message
  await expect(page.locator('text=Description generated')).toBeVisible();
});
```

---

## 🛠️ Test Helpers

### TestCase Base Class

**Location:** `api/tests/TestCase.php`

**Features:**
- Fake service bindings
- Common test utilities

**Usage:**
```php
class MyTest extends TestCase
{
    public function test_something(): void
    {
        $fake = $this->fakeOpenAiClient();
        $fake->setResponse('Generated description');
        
        // Test code
    }
}
```

### Test Helpers

**Location:** `api/tests/Feature/Helpers/`

**Examples:**
- `MovieTestHelper` - Movie test utilities
- `PersonTestHelper` - Person test utilities

**Usage:**
```php
use Tests\Feature\Helpers\MovieTestHelper;

$movie = MovieTestHelper::createMovieWithDescription();
```

---

## 🎭 Mocking External Services

### HTTP Mocking

**Laravel HTTP Facade:**
```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.openai.com/*' => Http::response([
        'choices' => [
            [
                'message' => [
                    'content' => 'Generated description'
                ]
            ]
        ]
    ], 200),
]);
```

### Service Mocking

**Fake Implementations:**
```php
use Tests\Doubles\Services\FakeOpenAiClient;

$fake = $this->fakeOpenAiClient();
$fake->setResponse('Generated description');
```

---

## 🚀 Running Tests

### All Tests

```bash
docker compose exec php php artisan test
```

### Specific Test File

```bash
docker compose exec php php artisan test tests/Feature/MoviesApiTest.php
```

### Specific Test Method

```bash
docker compose exec php php artisan test --filter test_can_retrieve_movie
```

### With Coverage

```bash
docker compose exec php php artisan test --coverage
```

### E2E Tests

```bash
cd tests/e2e
npm test
```

---

## 📊 Test Coverage

### Coverage Requirements

- **Overall:** > 80%
- **Critical Paths:** 100%
- **Business Logic:** > 90%
- **Controllers:** > 70%

### Generating Coverage Report

```bash
docker compose exec php php artisan test --coverage-html coverage/
```

**View Report:**
- Open `api/coverage/index.html` in browser

---

## ✅ Best Practices

### 1. Test Naming

**Good:**
```php
public function test_can_retrieve_movie_by_slug(): void
public function test_returns_404_when_movie_not_found(): void
public function test_rate_limit_enforced_per_plan(): void
```

**Bad:**
```php
public function test1(): void
public function test_movie(): void
public function test_works(): void
```

---

### 2. Arrange-Act-Assert Pattern

```php
public function test_can_create_movie(): void
{
    // Arrange
    $data = ['title' => 'The Matrix', 'release_year' => 1999];
    
    // Act
    $response = $this->postJson('/api/v1/movies', $data);
    
    // Assert
    $response->assertCreated()
        ->assertJson(['data' => ['title' => 'The Matrix']]);
}
```

---

### 3. Test Isolation

- Each test is independent
- Use `RefreshDatabase` trait
- Clear cache between tests
- No shared state

---

### 4. Meaningful Assertions

**Good:**
```php
$response->assertOk()
    ->assertJson([
        'data' => [
            'slug' => 'the-matrix-1999',
            'title' => 'The Matrix',
        ],
    ]);
```

**Bad:**
```php
$response->assertOk();
// No specific assertions
```

---

### 5. Test Data Management

**Use Factories:**
```php
$movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);
```

**Use Seeders:**
```php
$this->artisan('db:seed', ['--class' => 'MovieSeeder']);
```

---

## 🔍 Debugging Tests

### Verbose Output

```bash
docker compose exec php php artisan test --verbose
```

### Stop on Failure

```bash
docker compose exec php php artisan test --stop-on-failure
```

### Filter Tests

```bash
docker compose exec php php artisan test --filter MoviesApi
```

---

## 📚 Related Documentation

- [Test Strategy](TEST_STRATEGY.md) - Testing strategy overview
- [Manual Test Plans](MANUAL_TEST_PLANS.md) - Manual testing guide
- [API Testing Guide](../../API_TESTING_GUIDE.md) - API testing examples

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project
