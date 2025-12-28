# Test Driven Development (TDD)

> **Source:** Migrated from `.cursor/rules/old/testing.mdc`  
> **Category:** reference

## Basic principle

**Always write tests before implementation. Red-Green-Refactor cycle.**

## TDD Cycle

1. **RED** - Write a test that defines expected behavior
2. **GREEN** - Write minimal code needed to pass the test
3. **REFACTOR** - Improve code while keeping tests passing

## ✅ Always

- Write tests before code - test first, then implementation
- Run `php artisan test` after each change
- New code requires tests
- Feature Tests for API endpoints
- Unit Tests for business logic

## ❌ NEVER

- Don't commit code without tests
- Don't skip tests "because it's a small change"
- Don't ignore failing tests
- **Don't write implementation code before writing tests** - this violates TDD principle

## Test types

- **Feature Tests** (`tests/Feature/`) - API endpoints and integration
- **Unit Tests** (`tests/Unit/`) - Business logic and services

## Testing Schools - Mock Limitation Strategy

### Principle: Minimize Mocks, Maximize Real Objects

**ALWAYS prefer Chicago School or Detroit School over London School (Mockist).**

### ✅ Mock ONLY

1. **External APIs** - TMDb, OpenAI (expensive, unstable, rate-limited)
2. **Event/Queue systems** - Use `Event::fake()`, `Queue::fake()` for async operations
3. **Cache** - Use `Cache::fake()` when testing cache logic specifically
4. **File system** - When testing file operations
5. **Time-dependent code** - Use `Carbon::setTestNow()` instead of mocking

### ❌ DO NOT Mock

1. **Repositories** - Use real repositories with SQLite `:memory:` database
2. **Eloquent Models** - Use real models with test database
3. **Business Services** - Use real services with real dependencies
4. **Internal dependencies** - Use real objects within your application
5. **Value Objects** - Use real value objects
6. **Data Transformations** - Use real transformation logic

### Testing School Preferences

**Priority order (highest to lowest):**

1. **Chicago School (Classical/Behavior-Based)** - Preferred for most tests
   - Test behavior, not implementation
   - Use real objects where possible
   - Mock only external dependencies
   - Verify side effects and interactions

2. **Detroit School (State-Based)** - Preferred for data transformations
   - Test state transformations (input → output)
   - Use real objects for transformations
   - Mock only external services
   - Verify data state changes

3. **London School (Mockist)** - Use ONLY when necessary
   - Only for external APIs (TMDb, OpenAI)
   - Only when testing is impossible without mocks
   - Avoid for internal dependencies

4. **Outside-In TDD** - For Feature Tests
   - Start with acceptance tests
   - Use real objects in the system
   - Mock only external boundaries

### Examples

#### ✅ Good - Chicago School (Real Objects)

```php
// Feature Test - uses real database, real models
public function test_list_movies_returns_ok(): void
{
    $response = $this->getJson('/api/v1/movies');
    $response->assertOk()->assertJsonStructure([...]);
}
```

#### ✅ Good - Detroit School (State Transformation)

```php
// Unit Test - tests data transformation with real service
public function test_slug_generation(): void
{
    $service = new SlugService();
    $slug = $service->generateSlug('The Matrix', 1999);
    $this->assertSame('the-matrix-1999', $slug);
}
```

#### ✅ Good - Framework-Agnostic Test Doubles (Preferred)

```php
// Use own test doubles implementing interfaces (framework-agnostic)
$fake = $this->fakeEntityVerificationService();
$fake->setMovie('annihilation', [
    'title' => 'Annihilation',
    'release_date' => '2018-02-23',
    // ...
]);
```

#### ⚠️ Acceptable - Limited Mock (External API Only)

```php
// Mockery ONLY for external libraries without interfaces
$this->mock(TmdbVerificationService::class, function ($mock) {
    $mock->shouldReceive('verifyMovie')->andReturn($movieData);
});
// Note: Prefer own test doubles for interfaces (EntityVerificationServiceInterface)
```

#### ❌ Bad - London School (Too Many Mocks)

```php
// DON'T mock internal dependencies
$repository = Mockery::mock(MovieRepository::class);
$service = Mockery::mock(MovieService::class);
// ... too many mocks
```

### Framework-Agnostic Test Doubles

**Prefer own test doubles over Mockery for interfaces:**

1. **For interfaces** - Use own test doubles (`FakeEntityVerificationService`, `FakeOpenAiClient`)
   - Located in `api/tests/Doubles/Services/`
   - Implement interfaces directly (type-safe, framework-agnostic)
   - Use helper methods: `$this->fakeEntityVerificationService()`, `$this->fakeOpenAiClient()`

2. **For external libraries without interfaces** - Mockery is acceptable
   - Only when library doesn't have interface (e.g., `TMDBClient` from external package)
   - Add comment explaining why Mockery is used

3. **For repositories** - Use real repository with SQLite (Chicago School)
   - Better than mocking - tests real behavior
   - Use `RefreshDatabase` trait

### Rules

1. **Before adding a mock, ask:** "Is this an external dependency?"
2. **If internal interface:** Use own test double (`Fake*` classes)
3. **If internal repository:** Use real repository with SQLite (Chicago School)
4. **If external API without interface:** Mockery is acceptable (with comment)
5. **If unsure:** Prefer real object, refactor if needed
6. **Test behavior, not implementation** - Chicago School
7. **Test transformations, not interactions** - Detroit School

### When to Use Each School

- **Chicago School:** Feature tests, service tests with side effects, integration tests
- **Detroit School:** Data transformation tests, calculation tests, validation tests
- **London School:** External API tests ONLY (TMDb, OpenAI)
- **Outside-In:** Feature tests, acceptance tests, end-to-end scenarios

---

## Test Structure Patterns (AAA vs GWT)

### Principle: Standardize Test Structure for Readability

**ALWAYS structure tests using explicit patterns to improve readability and maintainability.**

### Pattern Selection

**Hybrid Approach - Use different patterns for different test types:**

1. **Unit Tests** → **AAA Pattern (Arrange-Act-Assert)**
   - Simple, isolated tests
   - Clear technical focus
   - Minimal setup required

2. **Feature Tests** → **GWT Pattern (Given-When-Then)**
   - Complex scenarios
   - Behavior-focused
   - More readable for stakeholders

3. **Complex Feature Tests** → **Three-Line Test Pattern** (optional)
   - Multi-step workflows
   - Repeated patterns
   - High readability requirements

### AAA Pattern (Arrange-Act-Assert)

**Use for:** Unit tests, simple isolated logic

```php
public function test_track_request_creates_usage_record(): void
{
    // ARRANGE: Set up test data and preconditions
    $result = $this->apiKeyService->createKey('Test Key');
    $apiKey = $result['apiKey'];
    
    // ACT: Execute the behavior being tested
    $this->service->trackRequest($apiKey, '/api/v1/movies', method: 'GET', responseStatus: 200);
    
    // ASSERT: Verify the results
    $this->assertDatabaseHas('api_usage', [
        'api_key_id' => $apiKey->id,
        'endpoint' => '/api/v1/movies',
        'method' => 'GET',
        'response_status' => 200,
    ]);
}
```

### GWT Pattern (Given-When-Then)

**Use for:** Feature tests, complex scenarios, behavior-focused tests

```php
public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
{
    // GIVEN: AI generation is enabled and movie exists in TMDb
    Feature::activate('ai_description_generation');
    $fake = $this->fakeEntityVerificationService();
    $fake->setMovie('annihilation', [
        'title' => 'Annihilation',
        'release_date' => '2018-02-23',
        'id' => 300668,
    ]);
    
    // WHEN: Requesting a movie that doesn't exist locally
    $response = $this->getJson('/api/v1/movies/annihilation');
    
    // THEN: Should return 202 with job details
    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence'])
        ->assertJson(['locale' => 'en-US']);
    
    // THEN: Confidence fields should be properly set
    $this->assertNotNull($response->json('confidence'));
    $this->assertContains(
        $response->json('confidence_level'),
        ['high', 'medium', 'low', 'very_low']
    );
}
```

### Three-Line Test Pattern (Optional)

**Use for:** Complex feature tests with repeated patterns

```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

### Rules

1. **Always use explicit comments** - Mark phases with `// ARRANGE`, `// ACT`, `// ASSERT` or `// GIVEN`, `// WHEN`, `// THEN`
2. **Keep phases distinct** - Don't mix arrange/act/assert
3. **One act per test** - Test one behavior at a time
4. **Multiple assertions are OK** - But verify related outcomes
5. **Extract complex setup** - Use factories or helper methods for long arrange sections

### Documentation

- **Tutorial:** [`docs/knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](../../knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md)
- **Quick Guide:** [`docs/knowledge/tutorials/TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`](../../knowledge/tutorials/TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md)

