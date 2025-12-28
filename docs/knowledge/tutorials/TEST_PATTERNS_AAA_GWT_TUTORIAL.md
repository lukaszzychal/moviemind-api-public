# Test Patterns Tutorial: AAA vs GWT

> **Educational document for TASK-029**  
> **Topic:** Standardizing test structure with Arrange-Act-Assert (AAA) or Given-When-Then (GWT) patterns  
> **Target audience:** PHP/Laravel developers working on MovieMind API

---

## 📚 Table of Contents

1. [Introduction](#introduction)
2. [Arrange-Act-Assert (AAA) Pattern](#arrange-act-assert-aaa-pattern)
3. [Given-When-Then (GWT) Pattern](#given-when-then-gwt-pattern)
4. [Comparison: AAA vs GWT](#comparison-aaa-vs-gwt)
5. [Three-Line Test Technique](#three-line-test-technique)
6. [Examples from MovieMind API](#examples-from-moviemind-api)
7. [Migration Guide](#migration-guide)
8. [Recommendations](#recommendations)
9. [Best Practices](#best-practices)
10. [References](#references)

---

## Introduction

### What are Test Patterns?

Test patterns provide **structural guidelines** for organizing test code to improve **readability**, **maintainability**, and **clarity**. They help developers write tests that are:

- ✅ **Easy to read** - clear structure makes intent obvious
- ✅ **Easy to understand** - consistent format reduces cognitive load
- ✅ **Easy to maintain** - standardized structure simplifies refactoring
- ✅ **Self-documenting** - structure itself explains the test's purpose

### Why Standardize Test Structure?

In a growing codebase like MovieMind API, **consistent test structure**:

1. **Reduces onboarding time** - new developers understand tests faster
2. **Improves code reviews** - reviewers can focus on logic, not structure
3. **Enables tooling** - IDEs and linters can better analyze test patterns
4. **Prevents bugs** - clear structure helps catch missing test steps

### Two Main Patterns

This tutorial covers two primary test patterns:

1. **AAA (Arrange-Act-Assert)** - Three-phase structure focusing on setup, execution, and verification
2. **GWT (Given-When-Then)** - BDD-style structure emphasizing behavior and context

---

## Arrange-Act-Assert (AAA) Pattern

### Overview

**Arrange-Act-Assert** is a simple, widely-adopted pattern that divides a test into three distinct phases:

```php
public function test_something(): void
{
    // ARRANGE: Set up test data and preconditions
    $movie = Movie::factory()->create(['title' => 'The Matrix']);
    
    // ACT: Execute the behavior being tested
    $result = $this->service->retrieveMovie('the-matrix-1999');
    
    // ASSERT: Verify the results
    $this->assertEquals('The Matrix', $result->title);
}
```

### Phase Breakdown

#### 1. Arrange (Setup)
- Create test data (models, factories, fixtures)
- Configure dependencies (mocks, stubs, fakes)
- Set up environment (config, cache, database state)
- Prepare input parameters

**Goal:** Establish all preconditions needed for the test

#### 2. Act (Execution)
- Call the method/function being tested
- Execute the behavior under test
- Trigger the action (single method call or operation)

**Goal:** Perform the specific operation being verified

#### 3. Assert (Verification)
- Check return values
- Verify side effects (database changes, cache updates)
- Validate behavior (exceptions, state changes)
- Confirm interactions (mocks called correctly)

**Goal:** Validate that the expected outcome occurred

### AAA Examples

#### Example 1: Simple Unit Test

```php
<?php

namespace Tests\Unit\Services;

use App\Services\PreGenerationValidator;
use Tests\TestCase;

class PreGenerationValidatorTest extends TestCase
{
    private PreGenerationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PreGenerationValidator();
    }

    public function test_should_generate_movie_with_valid_slug_and_year(): void
    {
        // ARRANGE
        $slug = 'the-matrix-1999';
        
        // ACT
        $result = $this->validator->shouldGenerateMovie($slug);
        
        // ASSERT
        $this->assertTrue($result['should_generate']);
        $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
        $this->assertArrayHasKey('reason', $result);
    }
}
```

#### Example 2: Feature Test with Database

```php
<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_movies_returns_ok(): void
    {
        // ARRANGE
        Movie::factory()->count(5)->create();
        
        // ACT
        $response = $this->getJson('/api/v1/movies');
        
        // ASSERT
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'release_year', 'director'],
                ],
            ]);
    }
}
```

#### Example 3: Complex Test with Multiple Assertions

```php
public function test_show_movie_response_is_cached(): void
{
    // ARRANGE
    $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);
    Cache::flush(); // Ensure cache is empty
    
    // ACT
    $firstResponse = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // ASSERT - Verify response
    $firstResponse->assertOk();
    
    // ASSERT - Verify cache was created
    $this->assertTrue(Cache::has('movie:the-matrix-1999:desc:default'));
    
    // ASSERT - Verify cached content matches response
    $this->assertSame(
        $firstResponse->json(),
        Cache::get('movie:the-matrix-1999:desc:default')
    );
    
    // ACT - Modify data
    $movie->update(['title' => 'Changed Title']);
    
    // ACT - Second request
    $secondResponse = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // ASSERT - Verify cache was not invalidated (expected behavior)
    $this->assertSame($firstResponse->json(), $secondResponse->json());
}
```

### AAA Advantages

✅ **Simple and intuitive** - Easy to understand and apply  
✅ **Universal** - Works for any testing framework  
✅ **Clear separation** - Distinct phases are easy to identify  
✅ **Widely known** - Most developers are familiar with it  
✅ **Tool-friendly** - IDEs can easily identify test phases  

### AAA Disadvantages

❌ **Can become verbose** - Complex arrange sections can be long  
❌ **Less readable for non-developers** - Technical terminology  
❌ **May mix concerns** - Multiple assertions can blur single responsibility  
❌ **Doesn't enforce structure** - Easy to skip phases or mix them  

---

## Given-When-Then (GWT) Pattern

### Overview

**Given-When-Then** is a **BDD (Behavior-Driven Development)** pattern that structures tests using natural language:

```php
public function test_movie_generation_is_queued_when_movie_not_exists(): void
{
    // GIVEN: A movie does not exist in the database
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // WHEN: A request is made to retrieve the movie
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // THEN: A generation job should be queued
    Queue::assertPushed(GenerateMovieJob::class);
    
    // THEN: The response should indicate job is queued
    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status']);
}
```

### Phase Breakdown

#### 1. Given (Precondition/Context)
- Describe the initial state
- Define prerequisites
- Set up the scenario context
- Establish the "world" before the action

**Goal:** Clearly describe the starting conditions

#### 2. When (Action/Trigger)
- Describe the action being performed
- Execute the behavior under test
- Trigger the event or operation

**Goal:** Clearly identify what action is being tested

#### 3. Then (Outcome/Verification)
- Describe the expected outcome
- Verify the results
- Check side effects and state changes
- Validate behavior matches expectations

**Goal:** Clearly state what should happen as a result

### GWT Examples

#### Example 1: Feature Test with GWT Comments

```php
<?php

namespace Tests\Feature;

use App\Events\MovieGenerationRequested;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MissingEntityGenerationTest extends TestCase
{
    use RefreshDatabase;

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
}
```

#### Example 2: Unit Test with GWT Structure

```php
public function test_validator_rejects_suspicious_slug_pattern(): void
{
    // GIVEN: A validator instance
    $validator = new PreGenerationValidator();
    
    // WHEN: Validating a suspicious slug pattern
    $result = $validator->shouldGenerateMovie('test-123-random');
    
    // THEN: Should reject the slug
    $this->assertFalse($result['should_generate']);
    
    // THEN: Should provide a reason explaining the rejection
    $this->assertStringContainsString('Suspicious', $result['reason']);
}
```

### GWT with Helper Methods (Three-Line Test)

The **"three-line test"** technique combines GWT with helper methods:

```php
public function test_movie_generation_creates_director_person(): void
{
    $this->givenMovieGenerationRequest()
        ->whenGeneratingMovie('the-matrix-1999')
        ->thenDirectorPersonShouldBeCreated('Lana Wachowski');
}

// Helper methods in test class
private function givenMovieGenerationRequest(): self
{
    Feature::activate('ai_description_generation');
    return $this;
}

private function whenGeneratingMovie(string $slug): self
{
    $this->response = $this->getJson("/api/v1/movies/{$slug}");
    return $this;
}

private function thenDirectorPersonShouldBeCreated(string $name): void
{
    $this->response->assertStatus(202);
    $this->assertDatabaseHas('people', [
        'name' => $name,
        'slug' => Str::slug($name),
    ]);
}
```

### GWT Advantages

✅ **Natural language** - Readable by non-developers (stakeholders, QA)  
✅ **Behavior-focused** - Emphasizes "what" over "how"  
✅ **BDD integration** - Works with BDD tools (Behat, Cucumber)  
✅ **Clear intent** - Comments explain the scenario clearly  
✅ **Documentation** - Tests read like specifications  

### GWT Disadvantages

❌ **Can be verbose** - Requires more comments/documentation  
❌ **Less universal** - Not all developers know BDD terminology  
❌ **May encourage long comments** - Can become overly descriptive  
❌ **Framework-dependent** - Best with BDD-supporting frameworks  

---

## Comparison: AAA vs GWT

### Side-by-Side Comparison

| Aspect | AAA (Arrange-Act-Assert) | GWT (Given-When-Then) |
|--------|-------------------------|----------------------|
| **Origin** | Traditional unit testing | BDD (Behavior-Driven Development) |
| **Terminology** | Technical (Arrange, Act, Assert) | Natural language (Given, When, Then) |
| **Focus** | Code structure | Behavior and scenarios |
| **Readability** | Developer-friendly | Business-friendly |
| **Comments** | Optional (structure is clear) | Recommended (enhances clarity) |
| **Complexity** | Simple, straightforward | Slightly more conceptual |
| **Adoption** | Very widespread | Common in BDD teams |
| **Tool Support** | Universal | Best with BDD tools |
| **Test Types** | All types (unit, feature, integration) | Best for feature/integration |

### When to Use AAA

**Use AAA when:**
- ✅ Writing unit tests (simple, isolated logic)
- ✅ Team prefers technical terminology
- ✅ Tests are already clear without comments
- ✅ Quick, straightforward tests
- ✅ Testing pure functions or utilities

**Example scenario:**
```php
// AAA is perfect for this simple unit test
public function test_calculator_adds_numbers(): void
{
    // ARRANGE
    $calculator = new Calculator();
    
    // ACT
    $result = $calculator->add(2, 3);
    
    // ASSERT
    $this->assertEquals(5, $result);
}
```

### When to Use GWT

**Use GWT when:**
- ✅ Writing feature/integration tests (complex scenarios)
- ✅ Working with BDD tools (Behat, Cucumber)
- ✅ Tests need to be readable by non-developers
- ✅ Documenting business requirements
- ✅ Complex workflows with multiple steps

**Example scenario:**
```php
// GWT is better for this complex feature test
public function test_movie_generation_flow_with_disambiguation(): void
{
    // GIVEN: Multiple movies exist with similar titles
    Movie::factory()->create(['title' => 'The Matrix', 'release_year' => 1999]);
    Movie::factory()->create(['title' => 'The Matrix', 'release_year' => 2021]);
    
    // WHEN: Requesting a movie by ambiguous slug
    $response = $this->getJson('/api/v1/movies/the-matrix');
    
    // THEN: Should return disambiguation options
    $response->assertStatus(300)
        ->assertJsonStructure(['data' => [['title', 'release_year', 'slug']]]);
    
    // WHEN: Selecting a specific movie
    $response = $this->getJson('/api/v1/movies/the-matrix?tmdb_id=603');
    
    // THEN: Should return the selected movie
    $response->assertStatus(200)
        ->assertJson(['release_year' => 1999]);
}
```

### Hybrid Approach

You can combine both patterns:

```php
public function test_complex_scenario(): void
{
    // GIVEN: Initial context (GWT terminology)
    Feature::activate('ai_description_generation');
    $movie = Movie::factory()->create();
    
    // ARRANGE: Additional setup (AAA terminology)
    Cache::flush();
    Event::fake();
    
    // WHEN: Action is triggered (GWT terminology)
    $response = $this->getJson("/api/v1/movies/{$movie->slug}");
    
    // ACT: Additional execution (AAA terminology)
    $this->artisan('queue:work', ['--once' => true]);
    
    // THEN: Verify outcome (GWT terminology)
    $response->assertOk();
    
    // ASSERT: Additional assertions (AAA terminology)
    $this->assertDatabaseHas('movie_descriptions', ['movie_id' => $movie->id]);
}
```

---

## Three-Line Test Technique

### Overview

The **"three-line test"** technique is a variant of GWT that uses **helper methods** to create extremely readable tests:

```php
public function test_movie_generation_creates_cast(): void
{
    $this->givenMovieExists('the-matrix-1999')
        ->whenGeneratingDescription()
        ->thenCastShouldBeCreated();
}
```

### Structure

1. **`given*()` methods** - Set up preconditions
2. **`when*()` methods** - Execute actions
3. **`then*()` methods** - Verify outcomes

### Implementation Example

```php
<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MovieGenerationTest extends TestCase
{
    use RefreshDatabase;
    
    private ?Movie $movie = null;
    private $response = null;

    public function test_movie_generation_creates_director(): void
    {
        $this->givenMovieDoesNotExist('the-matrix-1999')
            ->whenRequestingMovie()
            ->thenGenerationJobShouldBeQueued()
            ->andDirectorShouldBeCreated('Lana Wachowski');
    }

    // GIVEN helpers
    private function givenMovieExists(string $slug): self
    {
        $this->movie = Movie::factory()->create(['slug' => $slug]);
        return $this;
    }

    private function givenMovieDoesNotExist(string $slug): self
    {
        $this->assertDatabaseMissing('movies', ['slug' => $slug]);
        Feature::activate('ai_description_generation');
        return $this;
    }

    private function givenFeatureIsEnabled(string $feature): self
    {
        Feature::activate($feature);
        return $this;
    }

    // WHEN helpers
    private function whenRequestingMovie(): self
    {
        $this->response = $this->getJson("/api/v1/movies/{$this->movie?->slug ?? 'the-matrix-1999'}");
        return $this;
    }

    private function whenGeneratingDescription(): self
    {
        $this->response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $this->movie->slug,
        ]);
        return $this;
    }

    // THEN helpers
    private function thenGenerationJobShouldBeQueued(): self
    {
        Queue::assertPushed(GenerateMovieJob::class);
        return $this;
    }

    private function thenDirectorShouldBeCreated(string $name): self
    {
        $this->assertDatabaseHas('people', [
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
        return $this;
    }

    private function andDirectorShouldBeCreated(string $name): self
    {
        return $this->thenDirectorShouldBeCreated($name);
    }
}
```

### Advantages

✅ **Extremely readable** - Tests read like specifications  
✅ **Reusable helpers** - Build a library of common operations  
✅ **Less duplication** - Common setup extracted to helpers  
✅ **Self-documenting** - Method names explain intent  
✅ **Fluent interface** - Method chaining for readability  

### Disadvantages

❌ **Requires discipline** - Need to maintain helper methods  
❌ **Can become complex** - Many helpers can be overwhelming  
❌ **Additional abstraction** - Adds another layer to understand  
❌ **Not suitable for simple tests** - Overkill for trivial cases  

### When to Use Three-Line Tests

**Use when:**
- ✅ Complex feature tests with many steps
- ✅ Repeated test patterns across multiple tests
- ✅ Tests need to be very readable (documentation, demos)
- ✅ Team values readability over simplicity

**Avoid when:**
- ❌ Simple unit tests (overkill)
- ❌ One-off test scenarios (not worth helpers)
- ❌ Team prefers explicit code over abstraction

---

## Examples from MovieMind API

### Current State Analysis

Looking at existing tests in MovieMind API, most follow a **loose AAA structure** without explicit comments:

```php
// Current style (implicit AAA)
public function test_list_movies_returns_ok(): void
{
    $response = $this->getJson('/api/v1/movies'); // ACT (implicit)
    
    $response->assertOk() // ASSERT
        ->assertJsonStructure([...]);
}
```

### Refactored Examples

#### Example 1: Simple AAA Test

**Before:**
```php
public function test_should_generate_movie_with_valid_slug_and_year(): void
{
    $result = $this->validator->shouldGenerateMovie('the-matrix-1999');
    
    $this->assertTrue($result['should_generate']);
    $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
}
```

**After (explicit AAA):**
```php
public function test_should_generate_movie_with_valid_slug_and_year(): void
{
    // ARRANGE
    $slug = 'the-matrix-1999';
    
    // ACT
    $result = $this->validator->shouldGenerateMovie($slug);
    
    // ASSERT
    $this->assertTrue($result['should_generate']);
    $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
    $this->assertArrayHasKey('reason', $result);
}
```

#### Example 2: Feature Test with GWT

**Before:**
```php
public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
{
    Feature::activate('ai_description_generation');
    $fake = $this->fakeEntityVerificationService();
    $fake->setMovie('annihilation', [...]);
    
    $res = $this->getJson('/api/v1/movies/annihilation');
    
    $res->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence']);
}
```

**After (explicit GWT):**
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
    
    // THEN: Should return 202 with job queued
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

#### Example 3: Three-Line Test Pattern

**New test using three-line pattern:**

```php
public function test_movie_generation_creates_director_and_actors(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenMovieGenerationIsTriggered()
        ->thenMovieShouldBeCreated()
        ->andDirectorShouldBeCreated('Lana Wachowski')
        ->andActorsShouldBeCreated(['Keanu Reeves', 'Laurence Fishburne']);
}

// Helper methods
private function givenMovieDoesNotExist(string $slug): self
{
    $this->assertDatabaseMissing('movies', ['slug' => $slug]);
    Feature::activate('ai_description_generation');
    return $this;
}

private function whenMovieGenerationIsTriggered(): self
{
    $this->response = $this->getJson("/api/v1/movies/the-matrix-1999");
    return $this;
}

private function thenMovieShouldBeCreated(): self
{
    $this->response->assertStatus(202);
    // Wait for job to complete in test environment
    $this->artisan('queue:work', ['--once' => true]);
    return $this;
}

private function andDirectorShouldBeCreated(string $name): self
{
    $this->assertDatabaseHas('people', [
        'name' => $name,
        'slug' => Str::slug($name),
    ]);
    return $this;
}
```

---

## Migration Guide

### Step-by-Step Migration

#### Step 1: Choose Your Pattern

**Decision criteria:**
- **Unit tests** → Prefer **AAA** (simpler, more direct)
- **Feature tests** → Prefer **GWT** (more readable, behavior-focused)
- **Complex scenarios** → Consider **three-line test** with helpers

**Recommendation for MovieMind API:**
- **Unit tests**: Use **AAA** with explicit comments
- **Feature tests**: Use **GWT** with explicit comments
- **Complex feature tests**: Consider **three-line test** pattern

#### Step 2: Update New Tests First

Start applying the chosen pattern to **new tests**:

```php
// New test following AAA pattern
public function test_new_feature(): void
{
    // ARRANGE
    // ... setup code ...
    
    // ACT
    // ... execution code ...
    
    // ASSERT
    // ... verification code ...
}
```

#### Step 3: Refactor Existing Tests Incrementally

Refactor existing tests **when you touch them** (during bug fixes, feature additions):

```php
// Old test (no explicit structure)
public function test_existing_feature(): void
{
    $response = $this->getJson('/api/v1/movies');
    $response->assertOk();
}

// Refactored test (explicit AAA)
public function test_existing_feature(): void
{
    // ARRANGE
    Movie::factory()->count(5)->create();
    
    // ACT
    $response = $this->getJson('/api/v1/movies');
    
    // ASSERT
    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title']]]);
}
```

#### Step 4: Add Helper Methods (Optional)

For tests with **repeated patterns**, extract helper methods:

```php
// Base test class or trait
trait MovieTestHelpers
{
    protected function givenMovieExists(string $slug, array $attributes = []): Movie
    {
        return Movie::factory()->create(array_merge(['slug' => $slug], $attributes));
    }
    
    protected function whenRequestingMovie(string $slug): TestResponse
    {
        return $this->getJson("/api/v1/movies/{$slug}");
    }
    
    protected function thenMovieShouldBeReturned(TestResponse $response, string $expectedTitle): void
    {
        $response->assertOk()
            ->assertJson(['title' => $expectedTitle]);
    }
}

// Usage in test class
class MoviesApiTest extends TestCase
{
    use RefreshDatabase, MovieTestHelpers;
    
    public function test_show_movie(): void
    {
        // GIVEN
        $movie = $this->givenMovieExists('the-matrix-1999', ['title' => 'The Matrix']);
        
        // WHEN
        $response = $this->whenRequestingMovie('the-matrix-1999');
        
        // THEN
        $this->thenMovieShouldBeReturned($response, 'The Matrix');
    }
}
```

### Migration Checklist

- [ ] Choose pattern(s) for your project
- [ ] Document pattern choice in coding standards
- [ ] Update test guidelines/templates
- [ ] Apply pattern to new tests
- [ ] Refactor existing tests incrementally
- [ ] Create helper methods for common patterns
- [ ] Review and update during code reviews

---

## Recommendations

### For MovieMind API

Based on the analysis of existing tests and project needs:

#### Primary Recommendation: **Hybrid Approach**

1. **Unit Tests** → **AAA Pattern**
   - Simple, isolated tests
   - Clear technical focus
   - Minimal setup required

2. **Feature Tests** → **GWT Pattern**
   - Complex scenarios
   - Behavior-focused
   - More readable for stakeholders

3. **Complex Feature Tests** → **Three-Line Test Pattern**
   - Multi-step workflows
   - Repeated patterns
   - High readability requirements

#### Implementation Strategy

1. **Phase 1: Documentation**
   - Add pattern guidelines to coding standards
   - Create test templates with pattern examples
   - Document helper methods in test utilities

2. **Phase 2: New Tests**
   - Apply patterns to all new tests
   - Use code review to enforce patterns
   - Gradually build helper method library

3. **Phase 3: Refactoring (Optional)**
   - Refactor existing tests when touched
   - Focus on high-value tests first
   - Don't force complete migration (too costly)

### Pattern Selection Matrix

| Test Type | Complexity | Recommended Pattern |
|-----------|-----------|-------------------|
| Unit Test | Simple | AAA |
| Unit Test | Complex | AAA (with helper methods) |
| Feature Test | Simple | GWT |
| Feature Test | Complex | GWT or Three-Line Test |
| Integration Test | Any | GWT |

---

## Best Practices

### AAA Best Practices

1. **Keep phases distinct** - Don't mix arrange/act/assert
2. **One act per test** - Test one behavior at a time
3. **Multiple assertions are OK** - But verify related outcomes
4. **Extract complex arrange** - Use factories or helper methods
5. **Use comments when helpful** - Especially for complex setup

```php
// ✅ GOOD: Clear phases, helpful comments
public function test_complex_scenario(): void
{
    // ARRANGE: Create movie with descriptions in multiple locales
    $movie = Movie::factory()->create();
    MovieDescription::factory()->create([
        'movie_id' => $movie->id,
        'locale' => 'en-US',
    ]);
    MovieDescription::factory()->create([
        'movie_id' => $movie->id,
        'locale' => 'pl-PL',
    ]);
    
    // ACT: Request movie with specific locale
    $response = $this->getJson("/api/v1/movies/{$movie->slug}?locale=pl-PL");
    
    // ASSERT: Verify correct description is returned
    $response->assertOk()
        ->assertJsonPath('description.locale', 'pl-PL');
}
```

### GWT Best Practices

1. **Use natural language** - Write comments as if describing to a non-developer
2. **Multiple THEN clauses are OK** - Verify multiple outcomes
3. **Be specific in GIVEN** - Clearly state preconditions
4. **One WHEN per test** - Test one action/trigger
5. **Group related assertions** - Use multiple THEN comments for clarity

```php
// ✅ GOOD: Natural language, clear scenario
public function test_movie_generation_creates_cast_when_not_exists(): void
{
    // GIVEN: A movie does not exist and AI generation is enabled
    Feature::activate('ai_description_generation');
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // WHEN: Requesting the movie triggers generation
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');
    
    // THEN: Generation job should be queued
    Queue::assertPushed(GenerateMovieJob::class);
    
    // THEN: Response should indicate job is processing
    $response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status']);
    
    // THEN: After job completes, cast should be created
    $this->artisan('queue:work', ['--once' => true]);
    $this->assertDatabaseHas('people', ['name' => 'Keanu Reeves']);
}
```

### Three-Line Test Best Practices

1. **Naming conventions** - Use `given*`, `when*`, `then*`, `and*` prefixes
2. **Return self** - Enable method chaining
3. **Keep helpers focused** - One responsibility per helper
4. **Reuse across tests** - Build a library of common operations
5. **Document helpers** - Add PHPDoc comments for complex helpers

```php
// ✅ GOOD: Clear naming, method chaining, reusable
/**
 * Sets up a movie that does not exist locally but exists in TMDb
 */
private function givenMovieExistsInTmdbOnly(string $slug, array $tmdbData): self
{
    $this->assertDatabaseMissing('movies', ['slug' => $slug]);
    $fake = $this->fakeEntityVerificationService();
    $fake->setMovie($slug, $tmdbData);
    return $this;
}

/**
 * Triggers movie retrieval which should queue generation
 */
private function whenRequestingNonExistentMovie(string $slug): self
{
    $this->response = $this->getJson("/api/v1/movies/{$slug}");
    return $this;
}

/**
 * Verifies generation job was queued and response indicates processing
 */
private function thenGenerationShouldBeQueued(): self
{
    Queue::assertPushed(GenerateMovieJob::class);
    $this->response->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status']);
    return $this;
}
```

### Anti-Patterns to Avoid

#### ❌ Mixing patterns without structure

```php
// ❌ BAD: No clear structure
public function test_something(): void
{
    $movie = Movie::factory()->create();
    $response = $this->getJson('/api/v1/movies');
    $this->assertOk($response);
    Cache::flush();
    $this->assertTrue(true);
}
```

#### ❌ Too many acts in one test

```php
// ❌ BAD: Multiple actions make test unclear
public function test_multiple_actions(): void
{
    // ARRANGE
    $movie = Movie::factory()->create();
    
    // ACT (multiple actions)
    $this->getJson("/api/v1/movies/{$movie->slug}");
    $this->postJson('/api/v1/generate', [...]);
    $this->getJson("/api/v1/movies/{$movie->slug}");
    
    // ASSERT
    // ... unclear what's being tested
}
```

#### ❌ Overly complex arrange section

```php
// ❌ BAD: Too much setup makes test hard to understand
public function test_complex(): void
{
    // ARRANGE (50+ lines of setup)
    $movie = Movie::factory()->create();
    // ... 50 more lines ...
    
    // ACT
    $result = $service->doSomething();
    
    // ASSERT
    $this->assertTrue($result);
}
```

**Solution:** Extract complex setup to helper methods or factories.

---

## References

### Books

- **"Test-Driven Development: By Example"** by Kent Beck
- **"The Art of Unit Testing"** by Roy Osherove
- **"Specification by Example"** by Gojko Adzic
- **"Growing Object-Oriented Software, Guided by Tests"** by Freeman & Pryce

### Articles

- [AAA Pattern - Arrange Act Assert](https://github.com/testdouble/contributing-tests/wiki/Arrange-Act-Assert)
- [Given-When-Then Pattern](https://martinfowler.com/bliki/GivenWhenThen.html)
- [Three-Line Tests](https://blog.testdouble.com/posts/2019-09-10-three-line-tests/)
- [BDD with PHP](https://www.phptesting.org/)

### Laravel-Specific

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Best Practices](https://phpunit.readthedocs.io/en/9.5/writing-tests-for-phpunit.html)
- [Laravel Test Traits](https://laravel.com/docs/testing#available-assertions)

### Tools

- **PHPUnit** - PHP testing framework
- **Behat** - BDD framework for PHP
- **Codeception** - BDD testing framework
- **Pest** - Modern PHP testing framework with BDD syntax

---

## Summary

### Key Takeaways

1. **AAA (Arrange-Act-Assert)** - Simple, technical, universal pattern
   - Best for: Unit tests, simple scenarios
   - Focus: Code structure and phases

2. **GWT (Given-When-Then)** - Natural language, behavior-focused pattern
   - Best for: Feature tests, complex scenarios
   - Focus: Behavior and business requirements

3. **Three-Line Test** - GWT variant with helper methods
   - Best for: Complex feature tests, repeated patterns
   - Focus: Extreme readability and reusability

4. **Hybrid Approach** - Use different patterns for different test types
   - Unit tests → AAA
   - Feature tests → GWT
   - Complex tests → Three-line pattern

### Next Steps

1. ✅ Review this tutorial with the team
2. ✅ Decide on pattern(s) for MovieMind API
3. ✅ Update coding standards with chosen pattern(s)
4. ✅ Apply pattern(s) to new tests
5. ✅ Refactor existing tests incrementally
6. ✅ Build helper method library for common patterns

---

**Document created:** 2025-01-27  
**Last updated:** 2025-01-27  
**Related task:** TASK-029  
**Author:** AI Agent (Claude Sonnet 4.5)

