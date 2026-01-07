# Three-Line Test Technique - Guide

> **Documentation for TASK-030**  
> **Topic:** Technique for structuring tests with three helper method calls (Given/When/Then)  
> **Target audience:** PHP/Laravel developers working on MovieMind API  
> **Created:** 2025-01-07

---

## 📚 Table of Contents

1. [Introduction](#introduction)
2. [Test Checklist at Level L](#test-checklist-at-level-l)
3. [AAA Test Structure - Cheat Sheet](#aaa-test-structure---cheat-sheet)
4. [Three-Line Test Technique](#three-line-test-technique)
5. [PHPUnit Implementation](#phpunit-implementation)
6. [Examples from MovieMind API](#examples-from-moviemind-api)
7. [Advantages and Disadvantages](#advantages-and-disadvantages)
8. [When to Use](#when-to-use)
9. [Best Practices](#best-practices)
10. [References](#references)

---

## Introduction

### What is the "Three-Line Test" Technique?

The **"Three-Line Test"** technique is an approach to writing tests that uses **three helper method calls** to express the entire test scenario:

```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued();
}
```

### Philosophy

This technique is based on the principle that **tests should read like specifications**:

- **Line 1 (Given):** Establishes context and preconditions
- **Line 2 (When):** Executes the action under test
- **Line 3 (Then):** Verifies the expected result

### Relationship with AAA and GWT Patterns

The "Three-Line Test" technique combines:

- **AAA structure (Arrange-Act-Assert)** - three test phases
- **GWT semantics (Given-When-Then)** - readability and business context
- **Helper methods** - abstraction of implementation details

---

## Test Checklist at Level L

> **Source:** Test Checklist at Level L (diagnostic script)  
> **Version:** 0.3.0-pps-2025

### 1. True TDD

**PM:** "Do you write tests?"  
**D:** "We write!"

**PM:** "Seriously, do you start with a test?"  
**D:** "Obviously!"

✅ **True TDD guarantees that the test tests what it's supposed to test!**

### 2. Classicist TDD (Detroit School)

**PM:** "Classicist? (Detroit school)"  
**D:** "[shining eyes of being understood]"

✅ **Avoid mocks where possible** - test real objects and behaviors

### 3. Independent Tests

**After measurement, we make sure that:**

- ✅ **[x] Independent test**
- ✅ **Can be run in any order**
- ✅ **Enables 5 min CI/CD**

### 4. Fail Fast

✅ **[x] When writing production code src/no catch section was added silencing Exception. So-called Fail Fast. Code with a number of bugs close to 0!**

**Principle:** Don't silence exceptions - let them propagate to quickly detect problems.

---

## AAA Test Structure - Cheat Sheet

> **Source:** Cheat sheet for test structure (Arrange-Act-Assert pattern)

### Detailed Test Structure

```php
public function test_movie_generation_creates_director(): void  // 1a/1b: Test name
{
    // ARRANGE                                                      // 2: Comment introducing focus state
    // Preparation of data and components                           // 3: ARRANGE Part
    
    $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);
    Feature::activate('ai_description_generation');
    
    // ACT                                                          // 5: ACT comment
    // In this line, we run the code (subject of the test)         // 6: ACT Part
    
    $response = $this->getJson("/api/v1/movies/the-matrix-1999");
    
    // ASSERT                                                       // 8: ASSERT comment
    // Actual verification of code operation                       // 9: ASSERT Part
    
    $response->assertStatus(202);
    $this->assertDatabaseHas('people', [
        'name' => 'Lana Wachowski',
        'slug' => 'lana-wachowski',
    ]);
}                                                                   // 10: Closing brace
```

### Structure Elements

1. **1a/1b: Test name**
   - Grammatical sentence stating what the subject of the test is
   - In PHP/C# may have opening brace

2. **2: `//arrange`**
   - Comment introducing the coder into a state of focus

3. **3: ARRANGE Part**
   - Preparation of data and components

4. **4: Tactical empty line**
   - For readability

5. **5: `//act`**
   - Comment marking the execution phase

6. **6: ACT Part**
   - In this line, we run the code (subject of the test)

7. **7: Tactical empty line**
   - "Because we can afford it" - improves readability

8. **8: `//assert`**
   - Comment marking the verification phase

9. **9: ASSERT Part**
   - Actual verification of code operation

10. **10: `}`**
    - Closing brace (in Python, empty line)

---

## Three-Line Test Technique

### Concept

The "Three-Line Test" technique **simplifies the AAA structure** by using **helper methods**:

```php
// Instead of:
public function test_movie_generation_creates_director(): void
{
    // ARRANGE
    Feature::activate('ai_description_generation');
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // ACT
    $response = $this->getJson("/api/v1/movies/the-matrix-1999");
    
    // ASSERT
    $response->assertStatus(202);
    $this->assertDatabaseHas('people', [
        'name' => 'Lana Wachowski',
        'slug' => 'lana-wachowski',
    ]);
}

// We use:
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

### Helper Method Structure

#### 1. `given*()` Methods - Establish Context

```php
private function givenMovieDoesNotExist(string $slug): self
{
    $this->assertDatabaseMissing('movies', ['slug' => $slug]);
    Feature::activate('ai_description_generation');
    return $this;
}

private function givenMovieExists(string $slug, array $attributes = []): self
{
    $this->movie = Movie::factory()->create(array_merge(['slug' => $slug], $attributes));
    return $this;
}

private function givenFeatureIsEnabled(string $feature): self
{
    Feature::activate($feature);
    return $this;
}
```

#### 2. `when*()` Methods - Execute Action

```php
private function whenRequestingMovie(?string $slug = null): self
{
    $slug = $slug ?? $this->movie?->slug ?? 'the-matrix-1999';
    $this->response = $this->getJson("/api/v1/movies/{$slug}");
    return $this;
}

private function whenGeneratingMovie(string $slug): self
{
    $this->response = $this->postJson('/api/v1/generate', [
        'entity_type' => 'MOVIE',
        'entity_id' => $slug,
    ]);
    return $this;
}
```

#### 3. `then*()` Methods - Verify Result

```php
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

private function thenResponseShouldBe(int $statusCode): self
{
    $this->response->assertStatus($statusCode);
    return $this;
}
```

#### 4. `and*()` Methods - Additional Verifications

```php
private function andDirectorShouldBeCreated(string $name): self
{
    return $this->thenDirectorShouldBeCreated($name);
}

private function andResponseShouldContain(array $data): self
{
    $this->response->assertJson($data);
    return $this;
}
```

---

## PHPUnit Implementation

### Complete Test Class Example

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateMovieJob;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

    public function test_movie_generation_with_existing_movie(): void
    {
        $this->givenMovieExists('the-matrix-1999', ['title' => 'The Matrix'])
            ->whenRequestingMovie()
            ->thenResponseShouldBe(200)
            ->andResponseShouldContain(['title' => 'The Matrix']);
    }

    // ============================================
    // GIVEN helpers - Establish context
    // ============================================

    private function givenMovieDoesNotExist(string $slug): self
    {
        $this->assertDatabaseMissing('movies', ['slug' => $slug]);
        Feature::activate('ai_description_generation');
        return $this;
    }

    private function givenMovieExists(string $slug, array $attributes = []): self
    {
        $this->movie = Movie::factory()->create(
            array_merge(['slug' => $slug], $attributes)
        );
        return $this;
    }

    private function givenFeatureIsEnabled(string $feature): self
    {
        Feature::activate($feature);
        return $this;
    }

    // ============================================
    // WHEN helpers - Execute action
    // ============================================

    private function whenRequestingMovie(?string $slug = null): self
    {
        $slug = $slug ?? $this->movie?->slug ?? 'the-matrix-1999';
        $this->response = $this->getJson("/api/v1/movies/{$slug}");
        return $this;
    }

    private function whenGeneratingMovie(string $slug): self
    {
        $this->response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'entity_id' => $slug,
        ]);
        return $this;
    }

    // ============================================
    // THEN helpers - Verify result
    // ============================================

    private function thenGenerationJobShouldBeQueued(): self
    {
        Queue::assertPushed(GenerateMovieJob::class);
        return $this;
    }

    private function thenResponseShouldBe(int $statusCode): self
    {
        $this->response->assertStatus($statusCode);
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

    // ============================================
    // AND helpers - Additional verifications
    // ============================================

    private function andDirectorShouldBeCreated(string $name): self
    {
        return $this->thenDirectorShouldBeCreated($name);
    }

    private function andResponseShouldContain(array $data): self
    {
        $this->response->assertJson($data);
        return $this;
    }
}
```

### Naming Conventions

#### `given*()` Methods

- `givenMovieExists(string $slug): self`
- `givenMovieDoesNotExist(string $slug): self`
- `givenFeatureIsEnabled(string $feature): self`
- `givenUserIsAuthenticated(): self`
- `givenDatabaseIsEmpty(): self`

#### `when*()` Methods

- `whenRequestingMovie(?string $slug = null): self`
- `whenGeneratingMovie(string $slug): self`
- `whenCallingApi(string $endpoint): self`
- `whenUpdatingMovie(string $slug, array $data): self`

#### `then*()` Methods

- `thenResponseShouldBe(int $statusCode): self`
- `thenMovieShouldBeCreated(): self`
- `thenDirectorShouldBeCreated(string $name): self`
- `thenJobShouldBeQueued(string $jobClass): self`

#### `and*()` Methods

- `andResponseShouldContain(array $data): self`
- `andMovieShouldHaveAttribute(string $key, $value): self`
- `andDatabaseShouldHave(string $table, array $data): self`

---

## Examples from MovieMind API

### Example 1: Movie Generation Test

**Before (traditional AAA):**

```php
public function test_movie_generation_creates_director(): void
{
    // ARRANGE
    Feature::activate('ai_description_generation');
    $this->assertDatabaseMissing('movies', ['slug' => 'the-matrix-1999']);
    
    // ACT
    $response = $this->getJson("/api/v1/movies/the-matrix-1999");
    
    // ASSERT
    $response->assertStatus(202);
    Queue::assertPushed(GenerateMovieJob::class);
    $this->assertDatabaseHas('people', [
        'name' => 'Lana Wachowski',
        'slug' => 'lana-wachowski',
    ]);
}
```

**After (Three-Line Test):**

```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

### Example 2: Test with Multiple Verifications

```php
public function test_movie_generation_creates_full_cast(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski')
        ->andActorShouldBeCreated('Keanu Reeves')
        ->andActorShouldBeCreated('Laurence Fishburne')
        ->andResponseShouldContain(['status' => 'queued']);
}
```

### Example 3: Test with Existing Movie

```php
public function test_existing_movie_returns_immediately(): void
{
    $this->givenMovieExists('the-matrix-1999', [
        'title' => 'The Matrix',
        'release_year' => 1999,
    ])
        ->whenRequestingMovie()
        ->thenResponseShouldBe(200)
        ->andResponseShouldContain(['title' => 'The Matrix'])
        ->andResponseShouldContain(['release_year' => 1999]);
}
```

---

## Advantages and Disadvantages

### ✅ Advantages

1. **Extreme readability**
   - Tests read like specifications
   - No comments needed - code is self-documenting

2. **Reusability**
   - Helper methods can be used in many tests
   - Building a library of common operations

3. **Less duplication**
   - Common setups extracted to helpers
   - Easier maintenance

4. **Fluent interface**
   - Method chaining improves readability
   - Natural test flow

5. **TDD compliance**
   - Supports true TDD (test before code)
   - Makes writing tests first easier

### ❌ Disadvantages

1. **Requires discipline**
   - Need to maintain helper methods
   - Risk of "helper hell" (too many abstractions)

2. **Can be complex**
   - Many helpers can be overwhelming
   - Harder debugging (more layers)

3. **Additional abstraction**
   - Adds another layer to understand
   - New developers must learn helpers

4. **Not for simple tests**
   - Overkill for trivial cases
   - Better to use simple AAA for simple tests

---

## When to Use

### ✅ Use when:

- **Complex feature tests** with many steps
- **Repeated patterns** across multiple tests
- **Tests need to be very readable** (documentation, demos)
- **Team values readability** over simplicity
- **Integration tests** with multiple components

### ❌ Avoid when:

- **Simple unit tests** (overkill)
- **One-off scenarios** (not worth creating helpers)
- **Team prefers explicit code** over abstraction
- **Very simple assertions** (better to use simple AAA)

---

## Best Practices

### 1. Helper Method Organization

```php
class MovieGenerationTest extends TestCase
{
    // ============================================
    // GIVEN helpers
    // ============================================
    
    private function givenMovieExists(...): self { }
    private function givenMovieDoesNotExist(...): self { }
    
    // ============================================
    // WHEN helpers
    // ============================================
    
    private function whenRequestingMovie(...): self { }
    private function whenGeneratingMovie(...): self { }
    
    // ============================================
    // THEN helpers
    // ============================================
    
    private function thenResponseShouldBe(...): self { }
    private function thenMovieShouldBeCreated(...): self { }
    
    // ============================================
    // AND helpers
    // ============================================
    
    private function andResponseShouldContain(...): self { }
}
```

### 2. Naming Conventions

- **`given*()`** - always start with `given`
- **`when*()`** - always start with `when`
- **`then*()`** - always start with `then`
- **`and*()`** - always start with `and`

### 3. Fluent Interface

- All helper methods return `self`
- Enables method chaining
- Last method can return `void` if there are no further verifications

### 4. Test Independence

- Each test should be independent
- Use `RefreshDatabase` trait
- Don't rely on test execution order

### 5. Fail Fast

- Don't silence exceptions in helpers
- Let exceptions propagate
- Use PHPUnit assertions instead of try-catch

### 6. Documentation

- Add comments to complex helpers
- Use PHPDoc for parameters and return values
- Usage examples in comments

---

## References

### MovieMind API Documentation

- [`TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](./TEST_PATTERNS_AAA_GWT_TUTORIAL.md) - Full tutorial on test patterns
- [`TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`](./TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md) - Quick guide

### External Sources

- **TDD (Test-Driven Development)** - Kent Beck, "Test-Driven Development: By Example"
- **Detroit School TDD** - Classicist TDD, avoiding mocks
- **AAA Pattern** - Arrange-Act-Assert pattern
- **GWT Pattern** - Given-When-Then (BDD)

### Test Checklist

- **Test Checklist at Level L** (v. 0.3.0-pps-2025)
- **AAA Cheat Sheet** - Test structure with comments

---

**Last updated:** 2025-01-07  
**Author:** MovieMind API Team  
**Task:** TASK-030

