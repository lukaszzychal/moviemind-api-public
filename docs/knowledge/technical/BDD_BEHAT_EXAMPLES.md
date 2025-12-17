# BDD (Behat) vs Feature Tests - Przykłady i Wyjaśnienia

## 📋 Spis Treści

1. [Przykład migracji testu do Behat](#przykład-migracji)
2. [Living Documentation - Szczegółowe Wyjaśnienie](#living-documentation)
3. [Testy Akceptacyjne - Szczegółowe Wyjaśnienie](#testy-akceptacyjne)
4. [Porównanie praktyczne](#porównanie-praktyczne)

---

## Przykład Migracji Testu do Behat

### Obecny Test Feature (PHPUnit)

```php
// api/tests/Feature/MoviesApiTest.php
public function test_show_movie_returns_ok(): void
{
    $index = $this->getJson('/api/v1/movies');
    $slug = $index->json('data.0.slug');

    $response = $this->getJson('/api/v1/movies/'.$slug);
    $response->assertOk()
        ->assertJsonStructure(['id', 'slug', 'title', 'descriptions_count']);

    $this->assertIsInt($response->json('descriptions_count'));

    $response->assertJsonPath('_links.self.href', url('/api/v1/movies/'.$slug));

    $peopleLinks = $response->json('_links.people');
    $this->assertIsArray($peopleLinks);
    $this->assertNotEmpty($peopleLinks, 'Expected movie links to include people entries');
    $this->assertArrayHasKey('href', $peopleLinks[0]);
    $this->assertStringStartsWith(url('/api/v1/people/'), $peopleLinks[0]['href']);
}
```

### Wersja Behat (Gherkin)

#### 1. Plik Feature (`.feature`)

```gherkin
# features/movies/movie_details.feature
Feature: Movie Details API
  As an API consumer
  I want to retrieve movie details
  So that I can display complete movie information with AI-generated descriptions

  Background:
    Given the database is seeded with movies
    And I am an unauthenticated user

  Scenario: Retrieving movie details by slug
    Given there is a movie with slug "the-matrix-1999"
    When I send a GET request to "/api/v1/movies/the-matrix-1999"
    Then the response status should be 200
    And the response should contain JSON:
      """
      {
        "id": 1,
        "slug": "the-matrix-1999",
        "title": "The Matrix",
        "descriptions_count": 1
      }
      """
    And the "descriptions_count" field should be an integer
    And the response should contain HATEOAS links
    And the "self" link should point to "/api/v1/movies/the-matrix-1999"
    And the "people" links should point to "/api/v1/people/"

  Scenario: Movie details include HATEOAS links for related people
    Given there is a movie "The Matrix" with actors
    When I retrieve movie details for "the-matrix-1999"
    Then the response should contain "_links.people" array
    And each people link should have "href" field
    And all people links should start with "/api/v1/people/"
```

#### 2. Step Definitions (PHP)

```php
// features/bootstrap/FeatureContext.php
<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureContext extends TestCase implements Context
{
    use RefreshDatabase;

    private $response;
    private $baseUrl = 'http://localhost:8000';

    /**
     * @Given the database is seeded with movies
     */
    public function theDatabaseIsSeededWithMovies(): void
    {
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * @Given I am an unauthenticated user
     */
    public function iAmAnUnauthenticatedUser(): void
    {
        // No authentication needed for public API
    }

    /**
     * @Given there is a movie with slug :slug
     */
    public function thereIsAMovieWithSlug(string $slug): void
    {
        // Movie should exist from seeders
        $this->assertDatabaseHas('movies', ['slug' => $slug]);
    }

    /**
     * @When I send a GET request to :url
     */
    public function iSendAGetRequestTo(string $url): void
    {
        $this->response = $this->getJson($url);
    }

    /**
     * @Then the response status should be :statusCode
     */
    public function theResponseStatusShouldBe(int $statusCode): void
    {
        $this->response->assertStatus($statusCode);
    }

    /**
     * @Then the response should contain JSON:
     */
    public function theResponseShouldContainJson(PyStringNode $json): void
    {
        $expected = json_decode($json->getRaw(), true);
        $this->response->assertJson($expected);
    }

    /**
     * @Then the :field field should be an integer
     */
    public function theFieldShouldBeAnInteger(string $field): void
    {
        $value = $this->response->json($field);
        $this->assertIsInt($value, "Field '{$field}' should be an integer");
    }

    /**
     * @Then the response should contain HATEOAS links
     */
    public function theResponseShouldContainHateoasLinks(): void
    {
        $this->response->assertJsonStructure(['_links']);
    }

    /**
     * @Then the :linkType link should point to :url
     */
    public function theLinkShouldPointTo(string $linkType, string $url): void
    {
        $links = $this->response->json('_links');
        $this->assertArrayHasKey($linkType, $links);
        
        if ($linkType === 'self') {
            $this->assertEquals(url($url), $links[$linkType]['href']);
        }
    }

    /**
     * @Then the :linkType links should point to :urlPrefix
     */
    public function theLinksShouldPointTo(string $linkType, string $urlPrefix): void
    {
        $links = $this->response->json("_links.{$linkType}");
        $this->assertIsArray($links);
        $this->assertNotEmpty($links);
        
        foreach ($links as $link) {
            $this->assertArrayHasKey('href', $link);
            $this->assertStringStartsWith(url($urlPrefix), $link['href']);
        }
    }

    /**
     * @Given there is a movie :title with actors
     */
    public function thereIsAMovieWithActors(string $title): void
    {
        // Setup movie with related actors
        // Implementation depends on your factories
    }

    /**
     * @When I retrieve movie details for :slug
     */
    public function iRetrieveMovieDetailsFor(string $slug): void
    {
        $this->response = $this->getJson("/api/v1/movies/{$slug}");
    }

    /**
     * @Then the response should contain :path array
     */
    public function theResponseShouldContainArray(string $path): void
    {
        $value = $this->response->json($path);
        $this->assertIsArray($value);
    }

    /**
     * @Then each people link should have :field field
     */
    public function eachPeopleLinkShouldHaveField(string $field): void
    {
        $links = $this->response->json('_links.people');
        foreach ($links as $link) {
            $this->assertArrayHasKey($field, $link);
        }
    }

    /**
     * @Then all people links should start with :prefix
     */
    public function allPeopleLinksShouldStartWith(string $prefix): void
    {
        $links = $this->response->json('_links.people');
        foreach ($links as $link) {
            $this->assertStringStartsWith(url($prefix), $link['href']);
        }
    }
}
```

#### 3. Konfiguracja Behat

```yaml
# behat.yml
default:
  suites:
    default:
      contexts:
        - FeatureContext
      paths:
        - %paths.base%/features
  extensions:
    Laravel\Behat\Extension:
      base_path: api
      bootstrap: bootstrap/app.php
```

---

## Living Documentation - Szczegółowe Wyjaśnienie

### Co to jest Living Documentation?

**Living Documentation** (żywa dokumentacja) to dokumentacja generowana automatycznie z testów, która:
- **Zawsze jest aktualna** - jeśli test przechodzi, dokumentacja jest prawidłowa
- **Jest wykonywalna** - to nie tylko tekst, ale działający kod
- **Jest czytelna dla biznesu** - napisana w języku naturalnym (Gherkin)
- **Służy jako kontrakt** - definiuje oczekiwane zachowanie systemu

### Przykład w kontekście MovieMind API

#### 1. Scenariusz jako Dokumentacja

```gherkin
Feature: AI-Powered Movie Description Generation
  As a content consumer
  I want to receive AI-generated movie descriptions
  So that I can get unique, contextual content about movies

  Scenario: Generating description for new movie
    Given the feature flag "ai_description_generation" is enabled
    And there is no description for movie "inception-2010" in locale "pl-PL"
    When I request generation with:
      | entity_type | MOVIE        |
      | entity_id   | inception-2010 |
      | locale      | pl-PL        |
      | context_tag | modern       |
    Then the system should return status 202 Accepted
    And a generation job should be queued
    And the job status should be "PENDING"
    And the response should contain:
      | field    | value           |
      | job_id   | <uuid>          |
      | status   | PENDING         |
      | slug     | inception-2010 |
      | locale   | pl-PL          |
      | context_tag | modern      |

  Scenario: Feature flag blocks generation when disabled
    Given the feature flag "ai_description_generation" is disabled
    When I request generation for movie "inception-2010"
    Then the system should return status 403 Forbidden
    And the response should contain error "Feature not available"
    And no generation job should be created
```

#### 2. Jak to działa jako dokumentacja?

**Dla Programistów:**
- Widzą dokładnie, co system robi
- Mogą uruchomić testy, aby zweryfikować zachowanie
- Kod step definitions pokazuje implementację

**Dla Biznesu/Product Owner:**
- Mogą przeczytać scenariusze w języku naturalnym
- Rozumieją, co system robi bez znajomości kodu
- Mogą weryfikować, czy funkcjonalność działa zgodnie z oczekiwaniami

**Dla QA:**
- Scenariusze są gotowymi przypadkami testowymi
- Mogą dodać nowe scenariusze bez pisania kodu
- Testy są automatycznie wykonywane w CI/CD

#### 3. Generowanie HTML z dokumentacji

Behat może generować HTML z wszystkich scenariuszy:

```bash
vendor/bin/behat --format html --out docs/features.html
```

Wynik: piękna strona HTML z:
- Wszystkimi scenariuszami
- Statusem (passed/failed)
- Opisami funkcjonalności
- Przykładami użycia

#### 4. Przykład wygenerowanej dokumentacji

```html
<!-- docs/features.html -->
<h1>MovieMind API - Living Documentation</h1>

<h2>Feature: AI-Powered Movie Description Generation</h2>
<p>
  <strong>As a</strong> content consumer<br>
  <strong>I want to</strong> receive AI-generated movie descriptions<br>
  <strong>So that</strong> I can get unique, contextual content about movies
</p>

<h3>Scenario: Generating description for new movie</h3>
<ul>
  <li>✅ Given the feature flag "ai_description_generation" is enabled</li>
  <li>✅ And there is no description for movie "inception-2010" in locale "pl-PL"</li>
  <li>✅ When I request generation with parameters</li>
  <li>✅ Then the system should return status 202 Accepted</li>
  <li>✅ And a generation job should be queued</li>
</ul>

<p><strong>Last run:</strong> 2024-01-15 14:30:22</p>
<p><strong>Status:</strong> ✅ PASSED</p>
```

### Zalety Living Documentation

1. **Zawsze aktualna**
   - Jeśli test nie przechodzi, dokumentacja pokazuje problem
   - Nie ma "starej dokumentacji", która nie pasuje do kodu

2. **Jeden źródło prawdy**
   - Testy = Dokumentacja = Specyfikacja
   - Nie ma rozbieżności między dokumentacją a kodem

3. **Czytelna dla wszystkich**
   - Biznes rozumie scenariusze
   - Programiści widzą implementację
   - QA ma gotowe przypadki testowe

4. **Automatyczna weryfikacja**
   - CI/CD uruchamia testy
   - Dokumentacja jest automatycznie aktualizowana
   - Wszyscy widzą status funkcjonalności

---

## Testy Akceptacyjne - Szczegółowe Wyjaśnienie

### Co to są Testy Akceptacyjne?

**Testy akceptacyjne** (Acceptance Tests) to testy, które weryfikują, czy system spełnia **wymagania biznesowe** (nie tylko techniczne). Odpowiadają na pytanie: **"Czy system robi to, czego oczekuje biznes?"**

### Różnica: Testy Techniczne vs Akceptacyjne

#### Testy Techniczne (Feature Tests - PHPUnit)
```php
// Testuje implementację techniczną
public function test_movie_controller_returns_json(): void
{
    $response = $this->getJson('/api/v1/movies');
    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
}
```
**Pytanie:** Czy endpoint zwraca JSON?

#### Testy Akceptacyjne (BDD - Behat)
```gherkin
# Testuje wymaganie biznesowe
Scenario: User can search for movies
  Given I am a content consumer
  When I search for "Matrix"
  Then I should see movies matching "Matrix"
  And the results should be relevant to my search
```
**Pytanie:** Czy użytkownik może znaleźć filmy, których szuka?

### Przykłady Testów Akceptacyjnych dla MovieMind API

#### 1. Test Akceptacyjny: Generowanie Opisów Filmów

```gherkin
Feature: AI Description Generation (Business Acceptance)
  As a content platform owner
  I want to generate unique AI descriptions for movies
  So that I can provide original content to my users

  Scenario: Successfully generate description for new movie
    Given I want to add a new movie "Dune" to the platform
    And the AI generation feature is enabled
    When I request AI description generation for "Dune"
    Then the system should:
      | action                    | expected result                    |
      | Queue generation job      | Job ID returned                    |
      | Process in background     | Status: PENDING                    |
      | Generate unique content   | Description text created           |
      | Store in database         | Description available via API      |
    And the description should be:
      | property        | requirement                          |
      | Unique          | Not copied from IMDb/TMDb            |
      | Contextual      | Matches requested context_tag        |
      | Localized       | In requested locale (pl-PL/en-US)    |
      | AI-generated    | Origin: GENERATED, ai_model present  |

  Scenario: Handle generation failure gracefully
    Given I request generation for movie "NonExistentMovie"
    When the generation fails due to missing data
    Then the system should:
      | action                    | expected result                    |
      | Return error status       | Status: 404 or 422                |
      | Provide clear message     | Error explains what went wrong     |
      | Not create partial data   | No orphaned records in database    |
```

**Dlaczego to test akceptacyjny?**
- Testuje **wymaganie biznesowe**: "Platforma powinna generować unikalne opisy"
- Weryfikuje **wartość dla użytkownika**: "Użytkownik otrzymuje oryginalną treść"
- Sprawdza **zachowanie systemu** z perspektywy biznesu

#### 2. Test Akceptacyjny: Wyszukiwanie Filmów

```gherkin
Feature: Movie Search (Business Acceptance)
  As a content consumer
  I want to search for movies
  So that I can quickly find movies I'm interested in

  Scenario: Search returns relevant results
    Given the platform has movies:
      | title           | year | director          |
      | The Matrix      | 1999 | Wachowski Sisters |
      | Matrix Reloaded | 2003 | Wachowski Sisters |
      | Inception       | 2010 | Christopher Nolan |
    When I search for "matrix"
    Then I should see 2 movies
    And both should contain "Matrix" in the title
    And the results should be ordered by relevance

  Scenario: Search is case-insensitive
    Given the platform has movie "The Matrix"
    When I search for "MATRIX"
    Then I should find "The Matrix"
    When I search for "matrix"
    Then I should find "The Matrix"
    When I search for "MaTrIx"
    Then I should find "The Matrix"

  Scenario: Search handles special characters
    Given the platform has movie "Café de Flore"
    When I search for "cafe"
    Then I should find "Café de Flore"
```

**Dlaczego to test akceptacyjny?**
- Testuje **wymaganie biznesowe**: "Użytkownik powinien móc łatwo znaleźć filmy"
- Weryfikuje **doświadczenie użytkownika**: "Wyszukiwanie działa intuicyjnie"
- Sprawdza **zachowanie z perspektywy użytkownika końcowego**

#### 3. Test Akceptacyjny: Feature Flags

```gherkin
Feature: Feature Flag Management (Business Acceptance)
  As a platform administrator
  I want to control AI generation features
  So that I can manage costs and control rollout

  Scenario: Disable generation to control costs
    Given I am an administrator
    And the AI generation feature is currently enabled
    When I disable the "ai_description_generation" feature flag
    Then:
      | requirement                    | verification                      |
      | New generation requests blocked | POST /generate returns 403      |
      | Existing jobs continue         | Jobs in progress not cancelled   |
      | API remains functional         | GET /movies still works          |
      | Clear error message            | Users see "Feature not available"|

  Scenario: Enable generation for gradual rollout
    Given I am an administrator
    And the AI generation feature is currently disabled
    When I enable the "ai_description_generation" feature flag
    Then:
      | requirement                    | verification                      |
      | New requests accepted           | POST /generate returns 202       |
      | Jobs are queued                | Job ID returned in response      |
      | No disruption to existing API  | GET endpoints unaffected         |
```

**Dlaczego to test akceptacyjny?**
- Testuje **wymaganie biznesowe**: "Administrator powinien kontrolować funkcje"
- Weryfikuje **zarządzanie ryzykiem**: "Możliwość wyłączenia funkcji bez wpływu na resztę"
- Sprawdza **zachowanie z perspektywy administratora**

### Hierarchia Testów

```
┌─────────────────────────────────────┐
│   Testy Akceptacyjne (Behat)        │  ← "Czy system robi to, czego chce biznes?"
│   - Język naturalny (Gherkin)       │
│   - Czytelne dla wszystkich         │
│   - Testują wymagania biznesowe     │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│   Testy Feature (PHPUnit)            │  ← "Czy kod działa poprawnie?"
│   - Język programistyczny (PHP)      │
│   - Szybkie w pisaniu                │
│   - Testują implementację techniczną │
└─────────────────────────────────────┘
              ↓
┌─────────────────────────────────────┐
│   Testy Unit (PHPUnit)               │  ← "Czy pojedyncze klasy działają?"
│   - Bardzo szybkie                   │
│   - Izolowane                        │
│   - Testują logikę biznesową         │
└─────────────────────────────────────┘
```

### Kiedy pisać Testy Akceptacyjne?

**Pisz testy akceptacyjne gdy:**
1. ✅ Masz wymagania biznesowe do zweryfikowania
2. ✅ Potrzebujesz komunikacji z biznesem/QA
3. ✅ Chcesz living documentation
4. ✅ Testujesz user stories / use cases
5. ✅ Potrzebujesz testów end-to-end z perspektywy użytkownika

**NIE pisz testów akceptacyjnych gdy:**
1. ❌ Testujesz tylko implementację techniczną
2. ❌ Zespół jest wyłącznie techniczny
3. ❌ Potrzebujesz bardzo szybkich testów
4. ❌ Testujesz szczegóły implementacji (mocki, dependency injection)

---

## Porównanie Praktyczne

### Przykład: Test Generowania Opisu

#### Wersja Feature Test (PHPUnit)
```php
public function test_generate_movie_allowed_when_flag_on(): void
{
    Feature::activate('ai_description_generation');

    $resp = $this->postJson('/api/v1/generate', [
        'entity_type' => 'MOVIE',
        'entity_id' => 'the-matrix',
    ]);

    $resp->assertStatus(202)
        ->assertJsonStructure([
            'job_id', 'status', 'message', 'slug',
        ])
        ->assertJson([
            'status' => 'PENDING',
            'slug' => 'the-matrix',
            'locale' => 'en-US',
        ]);

    Event::assertDispatched(MovieGenerationRequested::class);
}
```

**Dla kogo:** Programiści  
**Czytelność:** ⭐⭐⭐ (wymaga znajomości PHP)  
**Szybkość pisania:** ⭐⭐⭐⭐⭐  
**Wartość dokumentacyjna:** ⭐⭐

#### Wersja BDD (Behat)
```gherkin
Scenario: Generate AI description for movie when feature enabled
  Given the feature flag "ai_description_generation" is enabled
  When I request generation for movie "the-matrix"
  Then the system should return status 202 Accepted
  And the response should contain:
    | field   | value        |
    | job_id  | <uuid>       |
    | status  | PENDING      |
    | slug    | the-matrix  |
    | locale  | en-US       |
  And a generation event should be dispatched
```

**Dla kogo:** Wszyscy (biznes, QA, dev)  
**Czytelność:** ⭐⭐⭐⭐⭐ (język naturalny)  
**Szybkość pisania:** ⭐⭐⭐ (wymaga step definitions)  
**Wartość dokumentacyjna:** ⭐⭐⭐⭐⭐

---

## Rekomendacja dla MovieMind API

### Obecna sytuacja: Feature Tests ✅

**Dlaczego to działa:**
- Zespół jest techniczny
- API jest RESTful (standardowe zachowania)
- Szybki rozwój
- Testy są już napisane i działają

### Kiedy rozważyć Behat:

1. **Gdy pojawi się potrzeba komunikacji z biznesem**
   - Product Owner chce czytać testy
   - Potrzebujesz akceptacji funkcjonalności

2. **Gdy potrzebujesz living documentation**
   - Dokumentacja API generowana z testów
   - Automatyczna aktualizacja

3. **Gdy piszesz testy akceptacyjne**
   - User stories wymagają weryfikacji
   - Testy end-to-end z perspektywy użytkownika

### Hybrydowe podejście (opcjonalne)

Możesz używać **obu** podejść:
- **Feature Tests** - dla szybkich testów technicznych
- **Behat** - dla wybranych testów akceptacyjnych / living documentation

---

## Podsumowanie

| Aspekt | Feature Tests (PHPUnit) | BDD (Behat) |
|--------|------------------------|-------------|
| **Język** | PHP (kod) | Gherkin (naturalny) |
| **Czytelność** | Dla programistów | Dla wszystkich |
| **Szybkość pisania** | Szybkie | Wolniejsze (step definitions) |
| **Dokumentacja** | Komentarze w kodzie | Living documentation |
| **Testy akceptacyjne** | Możliwe, ale techniczne | Idealne |
| **Komunikacja z biznesem** | Trudna | Łatwa |
| **Dla MovieMind API** | ✅ Obecnie idealne | ⚠️ Rozważyć w przyszłości |

---

**Aktualizacja:** 2024-01-15  
**Autor:** AI Assistant  
**Status:** Dokumentacja referencyjna

