# MovieMind API - Test Strategy

> **For:** QA Engineers, Developers, Testers  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document outlines the comprehensive testing strategy for MovieMind API, including test pyramid, coverage requirements, test data management, and CI/CD integration.

**Note:** This is a portfolio/demo project. For production deployment, see [Production Testing](#production-testing).

---

## 📊 Test Pyramid

### Structure

```
        /\
       /  \      E2E Tests (Playwright)
      /____\     - Few, critical user flows
     /      \    
    /________\   Feature Tests (PHPUnit)
   /          \  - API endpoints, integrations
  /____________\ Unit Tests (PHPUnit)
                - Services, Actions, Helpers
```

### Distribution

- **Unit Tests:** ~60% of tests
- **Feature Tests:** ~35% of tests
- **E2E Tests:** ~5% of tests

---

## 🧪 Test Types

### 1. Unit Tests

**Location:** `api/tests/Unit/`

**Purpose:**
- Test individual classes and methods in isolation
- Fast execution
- High coverage

**Examples:**
- `SlugValidatorTest` - Slug validation logic
- `MovieRepositoryTest` - Repository methods
- `MovieRetrievalServiceTest` - Service business logic
- `TvmazeVerificationServiceTest` - External service integration

**Characteristics:**
- Mock external dependencies
- Fast execution (< 1 second per test)
- No database (or PostgreSQL test DB)
- No HTTP requests

---

### 2. Feature Tests

**Location:** `api/tests/Feature/`

**Purpose:**
- Test API endpoints end-to-end
- Test integrations between components
- Verify business logic flows

**Examples:**
- `MoviesApiTest` - Movie endpoints
- `PeopleApiTest` - People endpoints
- `GenerateApiTest` - AI generation flow
- `ApiKeyAuthenticationTest` - Authentication
- `PlanBasedRateLimitTest` - Rate limiting

**Characteristics:**
- Use test database (PostgreSQL, e.g. Docker/CI)
- Real HTTP requests (via Laravel test client)
- Mock external APIs (OpenAI, TMDB, TVmaze)
- Slower execution (1-5 seconds per test)

---

### 3. E2E Tests

**Location:** `tests/e2e/specs/`

**Purpose:**
- Test critical user flows
- Verify UI interactions (Admin Panel)
- Test real browser behavior

**Examples:**
- `admin-flow.spec.ts` - Admin panel authentication flow
- `admin-generate.spec.ts` - AI generation via admin panel
- `admin-flags.spec.ts` - Feature flag management

**Characteristics:**
- Real browser (Playwright)
- Real HTTP requests
- Slower execution (10-30 seconds per test)
- Fewer tests (critical paths only)

---

## 📈 Coverage Requirements

### Target Coverage

- **Overall:** > 80%
- **Critical Paths:** 100%
- **Business Logic:** > 90%
- **Controllers:** > 70%

### Coverage Tools

**PHPUnit Coverage:**
```bash
docker compose exec php php artisan test --coverage
```

**Coverage Reports:**
- HTML: `api/coverage/index.html`
- Clover: `api/coverage/clover.xml`

---

## 🗄️ Test Data Management

### Database

**Test Database:**
- PostgreSQL test DB (fast, isolated via RefreshDatabase)
- PostgreSQL (for PostgreSQL-specific tests)

**Migrations:**
- Run migrations before each test
- Use `RefreshDatabase` trait

**Seeders:**
- `SubscriptionPlanSeeder` - Creates plans
- `ApiKeySeeder` - Creates demo API keys (non-production only)
- `GenreSeeder` - Creates genres
- `MovieSeeder` - Creates sample movies
- `PeopleSeeder` - Creates sample people

### Factories

**Location:** `api/database/factories/`

**Examples:**
- `MovieFactory` - Create test movies
- `PersonFactory` - Create test people
- `ApiKeyFactory` - Create test API keys

**Usage:**
```php
$movie = Movie::factory()->create();
$person = Person::factory()->create();
```

---

## 🎭 Mock vs Real Services

### Mock Services

**When to Mock:**
- External APIs (OpenAI, TMDB, TVmaze)
- Expensive operations
- Unreliable services
- Rate-limited services

**Mock Implementations:**
- `FakeOpenAiClient` - Mock OpenAI API
- `FakeEntityVerificationService` - Mock TMDB/TVmaze
- `Http::fake()` - Mock HTTP requests

**Examples:**
```php
Http::fake([
    'api.openai.com/*' => Http::response(['choices' => [...]], 200),
]);
```

---

### Real Services

**When to Use Real:**
- Integration tests (optional)
- Health check tests
- Manual testing

**Configuration:**
```env
AI_SERVICE=real  # Use real OpenAI API
AI_SERVICE=mock  # Use mock (default for tests)
```

---

## 🔄 CI/CD Integration

### Continuous Integration

**GitHub Actions:**
- `.github/workflows/ci.yml` - Main CI pipeline

**Pipeline Steps:**
1. **Code Quality:**
   - Laravel Pint (formatting)
   - PHPStan (static analysis)

2. **Tests:**
   - PHPUnit (unit + feature tests)
   - Coverage reporting

3. **Security:**
   - GitLeaks (secret detection)
   - Composer audit (dependency vulnerabilities)

4. **E2E Tests:**
   - Playwright (browser tests)

### Test Execution

**Local:**
```bash
docker compose exec php php artisan test
```

**CI:**
```bash
composer test
# Runs: pint, phpstan, phpunit
```

---

## 📋 Test Organization

### Directory Structure

```
api/tests/
├── Feature/          # Feature tests
│   ├── Admin/        # Admin panel tests
│   ├── Console/      # Console command tests
│   └── Helpers/      # Test helpers
├── Unit/             # Unit tests
├── Doubles/          # Test doubles (fakes, mocks)
└── Manual/           # Manual test scripts

tests/e2e/
├── specs/            # Playwright test specs
└── global-setup.ts   # E2E test setup
```

### Naming Conventions

**Test Classes:**
- `*Test.php` - PHPUnit tests
- `*.spec.ts` - Playwright tests

**Test Methods:**
- `test_*()` - PHPUnit (snake_case)
- `test('...')` - Playwright

**Examples:**
- `test_can_create_movie()`
- `test('Admin can generate movie description')`

---

## ✅ Test Best Practices

### 1. TDD (Test-Driven Development)

**Workflow:**
1. **RED:** Write failing test
2. **GREEN:** Write minimal code to pass
3. **REFACTOR:** Improve code while keeping tests passing

**Benefits:**
- Better test coverage
- Clearer requirements
- Safer refactoring

---

### 2. Arrange-Act-Assert (AAA)

**Structure:**
```php
public function test_can_retrieve_movie(): void
{
    // Arrange
    $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);
    
    // Act
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // Assert
    $response->assertOk()
        ->assertJson(['data' => ['slug' => 'the-matrix-1999']]);
}
```

---

### 3. Test Isolation

**Principles:**
- Each test is independent
- No shared state between tests
- Use `RefreshDatabase` trait
- Clear cache between tests

---

### 4. Meaningful Test Names

**Good:**
- `test_can_retrieve_movie_by_slug()`
- `test_returns_404_when_movie_not_found()`
- `test_rate_limit_enforced_per_plan()`

**Bad:**
- `test1()`
- `test_movie()`
- `test_works()`

---

### 5. Test One Thing

**Good:**
```php
public function test_can_retrieve_movie(): void { ... }
public function test_returns_404_when_not_found(): void { ... }
```

**Bad:**
```php
public function test_movie_endpoint(): void
{
    // Tests multiple things in one test
}
```

---

## 🚨 Error Handling Tests

### Test Error Scenarios

**Common Errors:**
- `400 Bad Request` - Invalid input
- `401 Unauthorized` - Missing/invalid API key
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server errors

**Examples:**
```php
public function test_returns_401_without_api_key(): void
{
    $response = $this->getJson('/api/v1/movies');
    $response->assertUnauthorized();
}

public function test_returns_404_when_movie_not_found(): void
{
    $response = $this->getJson('/api/v1/movies/non-existent-1999');
    $response->assertNotFound();
}
```

---

## 🔐 Security Testing

### Test Security Features

**Authentication:**
- API key validation
- Invalid API key handling
- Missing API key handling

**Authorization:**
- Plan-based access control
- Feature flag gating
- Admin-only endpoints

**Input Validation:**
- SQL injection prevention
- XSS prevention
- Prompt injection prevention

**Examples:**
- `ApiKeyAuthenticationTest` - Authentication tests
- `PromptInjectionSecurityTest` - AI prompt injection tests

---

## 📊 Performance Testing

### Load Tests

**Location:** `api/tests/Feature/AdaptiveRateLimitingLoadTest.php`

**Purpose:**
- Test rate limiting under load
- Verify system stability
- Measure response times

**Characteristics:**
- Multiple concurrent requests
- Measure throughput
- Verify rate limit enforcement

---

## 🎯 Test Reporting

### PHPUnit Reports

**JUnit XML:**
```bash
docker compose exec php php artisan test --log-junit results.xml
```

**Coverage HTML:**
```bash
docker compose exec php php artisan test --coverage-html coverage/
```

### Playwright Reports

**Location:** `playwright-report/`

**Access:**
- HTML report: `playwright-report/index.html`
- Trace files: `test-results/*/trace.zip`

---

## 🔮 Production Testing

### Additional Tests for Production

**Security:**
- Penetration testing
- Vulnerability scanning
- Security audit

**Performance:**
- Load testing (realistic traffic)
- Stress testing (peak load)
- Endurance testing (long duration)

**Reliability:**
- Chaos engineering
- Failure injection
- Disaster recovery testing

---

## 📚 Related Documentation

- [Manual Test Plans](MANUAL_TEST_PLANS.md) - Manual testing guide
- [Automated Tests](AUTOMATED_TESTS.md) - Automated testing guide
- [API Testing Guide](../../API_TESTING_GUIDE.md) - API testing examples
- [Architecture](../../technical/ARCHITECTURE.md) - System architecture

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project
