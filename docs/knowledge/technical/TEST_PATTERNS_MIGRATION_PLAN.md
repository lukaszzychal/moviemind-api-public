# Test Patterns Migration Plan

> **Related Task:** TASK-029  
> **Status:** Implementation in progress  
> **Created:** 2025-01-27

---

## Overview

This document outlines the migration plan for standardizing test structure across MovieMind API using AAA (Arrange-Act-Assert) and GWT (Given-When-Then) patterns.

## Decision: Hybrid Approach

**Pattern Selection:**
- **Unit Tests** → **AAA Pattern** (Arrange-Act-Assert)
- **Feature Tests** → **GWT Pattern** (Given-When-Then)
- **Complex Feature Tests** → **Three-Line Test Pattern** (optional)

**Rationale:**
- AAA is simpler and more technical - perfect for unit tests
- GWT is more readable and behavior-focused - perfect for feature tests
- Three-line pattern provides extreme readability for complex scenarios

## Migration Strategy

### Phase 1: Documentation ✅ COMPLETED

- [x] Created comprehensive tutorial: `TEST_PATTERNS_AAA_GWT_TUTORIAL.md`
- [x] Created quick guide: `TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`
- [x] Updated `TESTING_POLICY.md` with pattern recommendations
- [x] Documented pattern selection criteria

### Phase 2: Example Refactoring ✅ COMPLETED

**Unit Test Example (AAA):**
- [x] Refactored `UsageTrackerTest` to use explicit AAA pattern with comments
- [x] All test methods now have `// ARRANGE`, `// ACT`, `// ASSERT` comments

**Feature Test Example (GWT):**
- [x] Refactored `MissingEntityGenerationTest` to use explicit GWT pattern
- [x] All test methods now have `// GIVEN`, `// WHEN`, `// THEN` comments

### Phase 3: Incremental Migration (Ongoing)

**Strategy:** Refactor tests **when you touch them** (during bug fixes, feature additions)

**Priority Order:**
1. **High-value tests** - Frequently modified, complex scenarios
2. **New tests** - Apply patterns immediately to all new tests
3. **Existing tests** - Refactor when touched during development

**Guidelines:**
- ✅ Apply patterns to **all new tests** immediately
- ✅ Refactor existing tests **when you modify them**
- ❌ Don't force complete migration (too costly, low ROI)
- ✅ Use code reviews to enforce patterns on new tests

### Phase 4: Helper Methods (Optional)

**For complex feature tests with repeated patterns:**
- Consider creating helper methods with `given*()`, `when*()`, `then*()` naming
- Build library of common operations
- Use three-line test pattern for complex scenarios

**Example:**
```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

## Current Status

### ✅ Completed

1. **Documentation:**
   - Tutorial created (`TEST_PATTERNS_AAA_GWT_TUTORIAL.md`)
   - Quick guide created (`TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`)
   - Testing policy updated (`TESTING_POLICY.md`)

2. **Example Refactoring:**
   - `UsageTrackerTest` - All methods refactored to AAA pattern
   - `MissingEntityGenerationTest` - Key methods refactored to GWT pattern

### 🔄 In Progress

- Applying patterns to new tests (ongoing)
- Refactoring existing tests when touched (ongoing)

### 📋 Remaining Work

- Monitor new tests for pattern compliance
- Refactor existing tests incrementally
- Consider helper methods for complex scenarios (optional)

## Test Files Status

### Unit Tests (Should use AAA)

| File | Status | Notes |
|------|--------|-------|
| `UsageTrackerTest.php` | ✅ Refactored | All methods use AAA pattern |
| `PlanServiceTest.php` | ⏳ Pending | Refactor when touched |
| `JobErrorFormatterTest.php` | ⏳ Pending | Refactor when touched |
| `ExampleTest.php` | ⏳ Pending | Refactor when touched |

### Feature Tests (Should use GWT)

| File | Status | Notes |
|------|--------|-------|
| `MissingEntityGenerationTest.php` | ✅ Partially refactored | Key methods use GWT pattern |
| `MoviesApiTest.php` | ⏳ Pending | Refactor when touched |
| `PeopleApiTest.php` | ⏳ Pending | Refactor when touched |
| `GenerateApiTest.php` | ⏳ Pending | Refactor when touched |
| `AdminFlagsTest.php` | ⏳ Pending | Refactor when touched |
| `HateoasTest.php` | ⏳ Pending | Refactor when touched |

## Code Review Checklist

When reviewing new tests, ensure:

- [ ] **Unit tests** use AAA pattern with explicit `// ARRANGE`, `// ACT`, `// ASSERT` comments
- [ ] **Feature tests** use GWT pattern with explicit `// GIVEN`, `// WHEN`, `// THEN` comments
- [ ] Phases are distinct and not mixed
- [ ] One act per test (single behavior being tested)
- [ ] Complex setup is extracted to helper methods or factories

## Examples

### ✅ Good - Unit Test (AAA)

```php
public function test_track_request_creates_usage_record(): void
{
    // ARRANGE: Create API key for tracking
    $result = $this->apiKeyService->createKey('Test Key');
    $apiKey = $result['apiKey'];

    // ACT: Track a request
    $this->service->trackRequest($apiKey, '/api/v1/movies', method: 'GET', responseStatus: 200);

    // ASSERT: Verify usage record was created
    $this->assertDatabaseHas('api_usage', [
        'api_key_id' => $apiKey->id,
        'endpoint' => '/api/v1/movies',
        'method' => 'GET',
        'response_status' => 200,
    ]);
}
```

### ✅ Good - Feature Test (GWT)

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
    $res = $this->getJson('/api/v1/movies/annihilation');

    // THEN: Should return 202 with job details
    $res->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level'])
        ->assertJson(['locale' => 'en-US']);
}
```

## References

- **Tutorial:** [`docs/knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](../../knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md)
- **Quick Guide:** [`docs/knowledge/tutorials/TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`](../../knowledge/tutorials/TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md)
- **Testing Policy:** [`docs/knowledge/reference/TESTING_POLICY.md`](../../knowledge/reference/TESTING_POLICY.md)

---

**Last updated:** 2025-01-27  
**Next review:** When significant progress is made on test refactoring

