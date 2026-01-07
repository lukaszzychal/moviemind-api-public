# Technika "Three-Line Test" - Przewodnik

> **Dokumentacja dla TASK-030**  
> **Temat:** Technika strukturyzacji testów z trzema wywołaniami pomocniczymi  
> **Grupa docelowa:** Programiści PHP/Laravel pracujący nad MovieMind API  
> **Data utworzenia:** 2025-01-07

---

## 📚 Spis treści

1. [Wprowadzenie](#wprowadzenie)
2. [Checklista testu na poziomie L](#checklista-testu-na-poziomie-l)
3. [Struktura testu AAA - Cheat Sheet](#struktura-testu-aaa---cheat-sheet)
4. [Technika Three-Line Test](#technika-three-line-test)
5. [Implementacja w PHPUnit](#implementacja-w-phpunit)
6. [Przykłady z MovieMind API](#przykłady-z-moviemind-api)
7. [Zalety i wady](#zalety-i-wady)
8. [Kiedy używać](#kiedy-używać)
9. [Best Practices](#best-practices)
10. [Referencje](#referencje)

---

## Wprowadzenie

### Czym jest technika "Three-Line Test"?

**Technika "Three-Line Test"** (test trójlinijkowy) to podejście do pisania testów,
które wykorzystuje **trzy wywołania metod pomocniczych** do wyrażenia całego scenariusza testowego:

```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued();
}
```

### Filozofia

Technika ta opiera się na założeniu, że **test powinien być czytelny jak specyfikacja**:

- **Linia 1 (Given):** Ustala kontekst i warunki wstępne
- **Linia 2 (When):** Wykonuje akcję będącą przedmiotem testu
- **Linia 3 (Then):** Weryfikuje oczekiwany rezultat

### Związek z wzorcami AAA i GWT

Technika "Three-Line Test" łączy w sobie:

- **Strukturę AAA (Arrange-Act-Assert)** - trzy fazy testu
- **Semantykę GWT (Given-When-Then)** - czytelność i kontekst biznesowy
- **Helper methods** - abstrakcja szczegółów implementacji

---

## Checklista testu na poziomie L

> **Źródło:** Checklista Testu Na Poziomie L (skrypt diagnostyczny)  
> **Wersja:** 0.3.0-pps-2025

### 1. Prawdziwy TDD

**PM:** "Czy piszesz testy?"  
**D:** "Piszemy!"

**PM:** "Poważnie, czy zaczynasz od testu?"  
**D:** "Oczywiście!"

✅ **Prawdziwy TDD gwarantuje, że test testuje to, co powinien testować!**

### 2. Klasycystyczny TDD (Detroit School)

**PM:** "Klasycysta? (Detroit school)"  
**D:** "[błyszczące oczy zrozumienia]"

✅ **Unikaj mocków gdzie możliwe** - testuj prawdziwe obiekty i zachowania

### 3. Niezależne testy

**Po pomiarze upewniamy się, że:**

- ✅ **[x] Niezależny test**
- ✅ **Może być uruchamiany w dowolnej kolejności**
- ✅ **Umożliwia 5 min CI/CD**

### 4. Fail Fast

✅ **[x] Przy pisaniu kodu produkcyjnego src/nie dodano sekcji catch wyciszającej Exception.
Tak zwane Fail Fast. Kod z liczbą błędów bliską 0!**

**Zasada:** Nie wyciszaj wyjątków - pozwól im się propagować, aby szybko wykryć problemy.

---

## Struktura testu AAA - Cheat Sheet

> **Źródło:** Cheat sheet dla struktury testów (wzorzec Arrange-Act-Assert)

### Szczegółowa struktura testu

```php
public function test_movie_generation_creates_director(): void  // 1a/1b: Nazwa testu
{
    // ARRANGE                                                      // 2: Komentarz wprowadzający w stan skupienia
    // Przygotowanie danych i komponentów                           // 3: Część ARRANGE
    
    $movie = Movie::factory()->create(['slug' => 'the-matrix-1999']);
    Feature::activate('ai_description_generation');
    
    // ACT                                                          // 5: Komentarz ACT
    // W tej linii uruchamiamy kod (przedmiot testu)               // 6: Część ACT
    
    $response = $this->getJson("/api/v1/movies/the-matrix-1999");
    
    // ASSERT                                                       // 8: Komentarz ASSERT
    // Faktyczna weryfikacja działania kodu                        // 9: Część ASSERT
    
    $response->assertStatus(202);
    $this->assertDatabaseHas('people', [
        'name' => 'Lana Wachowski',
        'slug' => 'lana-wachowski',
    ]);
}                                                                   // 10: Klamra zamykająca
```

### Elementy struktury

1. **1a/1b: Nazwa testu**
   - Gramatyczne zdanie mówiące, co jest przedmiotem testu
   - W PHP/C# może być klamra otwierająca

2. **2: `//arrange`**
   - Komentarz wprowadzający kodującego w stan skupienia

3. **3: Część ARRANGE**
   - Przygotowanie danych i komponentów

4. **4: Pusta linia taktyczna**
   - Dla czytelności

5. **5: `//act`**
   - Komentarz oznaczający fazę wykonania

6. **6: Część ACT**
   - W tej linii uruchamiamy kod (przedmiot testu)

7. **7: Pusta linia taktyczna**
   - "Bo nas stać" - poprawia czytelność

8. **8: `//assert`**
   - Komentarz oznaczający fazę weryfikacji

9. **9: Część ASSERT**
   - Faktyczna weryfikacja działania kodu

10. **10: `}`**
    - Klamra zamykająca (w Pythonie pusta linia)

---

## Technika Three-Line Test

### Koncepcja

Technika "Three-Line Test" **upraszcza strukturę AAA** poprzez użycie **metod pomocniczych**:

```php
// Zamiast:
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

// Używamy:
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

### Struktura metod pomocniczych

#### 1. Metody `given*()` - Ustalenie kontekstu

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

#### 2. Metody `when*()` - Wykonanie akcji

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

#### 3. Metody `then*()` - Weryfikacja rezultatu

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

#### 4. Metody `and*()` - Dodatkowe weryfikacje

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

## Implementacja w PHPUnit

### Pełny przykład klasy testowej

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
    // GIVEN helpers - Ustalenie kontekstu
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
    // WHEN helpers - Wykonanie akcji
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
    // THEN helpers - Weryfikacja rezultatu
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
    // AND helpers - Dodatkowe weryfikacje
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

### Konwencje nazewnictwa

#### Metody `given*()`

- `givenMovieExists(string $slug): self`
- `givenMovieDoesNotExist(string $slug): self`
- `givenFeatureIsEnabled(string $feature): self`
- `givenUserIsAuthenticated(): self`
- `givenDatabaseIsEmpty(): self`

#### Metody `when*()`

- `whenRequestingMovie(?string $slug = null): self`
- `whenGeneratingMovie(string $slug): self`
- `whenCallingApi(string $endpoint): self`
- `whenUpdatingMovie(string $slug, array $data): self`

#### Metody `then*()`

- `thenResponseShouldBe(int $statusCode): self`
- `thenMovieShouldBeCreated(): self`
- `thenDirectorShouldBeCreated(string $name): self`
- `thenJobShouldBeQueued(string $jobClass): self`

#### Metody `and*()`

- `andResponseShouldContain(array $data): self`
- `andMovieShouldHaveAttribute(string $key, $value): self`
- `andDatabaseShouldHave(string $table, array $data): self`

---

## Przykłady z MovieMind API

### Przykład 1: Test generacji filmu

**Przed (tradycyjny AAA):**

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

**Po (Three-Line Test):**

```php
public function test_movie_generation_creates_director(): void
{
    $this->givenMovieDoesNotExist('the-matrix-1999')
        ->whenRequestingMovie()
        ->thenGenerationJobShouldBeQueued()
        ->andDirectorShouldBeCreated('Lana Wachowski');
}
```

### Przykład 2: Test z wieloma weryfikacjami

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

### Przykład 3: Test z istniejącym filmem

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

## Zalety i wady

### ✅ Zalety

1. **Ekstremalna czytelność**
   - Testy czytają się jak specyfikacje
   - Nie wymagają komentarzy - kod jest samodokumentujący

2. **Reużywalność**
   - Metody pomocnicze mogą być używane w wielu testach
   - Budowanie biblioteki wspólnych operacji

3. **Mniej duplikacji**
   - Wspólne setupy wyodrębnione do helperów
   - Łatwiejsze utrzymanie

4. **Fluent interface**
   - Łańcuchowe wywołania metod poprawiają czytelność
   - Naturalny przepływ testu

5. **Zgodność z TDD**
   - Wspiera prawdziwy TDD (test przed kodem)
   - Ułatwia pisanie testów najpierw

### ❌ Wady

1. **Wymaga dyscypliny**
   - Trzeba utrzymywać metody pomocnicze
   - Ryzyko "helper hell" (zbyt wiele abstrakcji)

2. **Może być złożone**
   - Wiele helperów może być przytłaczające
   - Trudniejsze debugowanie (więcej warstw)

3. **Dodatkowa abstrakcja**
   - Dodaje kolejną warstwę do zrozumienia
   - Nowi deweloperzy muszą poznać helpery

4. **Nie dla prostych testów**
   - Overkill dla trywialnych przypadków
   - Lepiej użyć prostego AAA dla prostych testów

---

## Kiedy używać

### ✅ Używaj gdy

- **Złożone testy funkcjonalne** z wieloma krokami
- **Powtarzające się wzorce** w wielu testach
- **Testy muszą być bardzo czytelne** (dokumentacja, demos)
- **Zespół ceni czytelność** nad prostotę
- **Testy integracyjne** z wieloma komponentami

### ❌ Unikaj gdy

- **Proste testy jednostkowe** (overkill)
- **Jednorazowe scenariusze** (nie warto tworzyć helperów)
- **Zespół preferuje jawny kod** nad abstrakcję
- **Bardzo proste asercje** (lepiej użyć prostego AAA)

---

## Best Practices

### 1. Organizacja metod pomocniczych

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

### 2. Konwencje nazewnictwa

- **`given*()`** - zawsze zaczynają się od `given`
- **`when*()`** - zawsze zaczynają się od `when`
- **`then*()`** - zawsze zaczynają się od `then`
- **`and*()`** - zawsze zaczynają się od `and`

### 3. Fluent interface

- Wszystkie metody pomocnicze zwracają `self`
- Umożliwia łańcuchowe wywołania
- Ostatnia metoda może zwracać `void` jeśli nie ma dalszych weryfikacji

### 4. Niezależność testów

- Każdy test powinien być niezależny
- Używaj `RefreshDatabase` trait
- Nie polegaj na kolejności wykonywania testów

### 5. Fail Fast

- Nie wyciszaj wyjątków w helperach
- Pozwól wyjątkom się propagować
- Używaj asercji PHPUnit zamiast try-catch

### 6. Dokumentacja

- Dodaj komentarze do złożonych helperów
- Użyj PHPDoc dla parametrów i zwracanych wartości
- Przykłady użycia w komentarzach

---

## Referencje

### Dokumentacja MovieMind API

- [`TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](./TEST_PATTERNS_AAA_GWT_TUTORIAL.md) - Pełny tutorial o wzorcach testów
- [`TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md`](./TEST_PATTERNS_AAA_VS_GWT_QUICK_GUIDE.md) - Szybki przewodnik

### Zewnętrzne źródła

- **TDD (Test-Driven Development)** - Kent Beck, "Test-Driven Development: By Example"
- **Detroit School TDD** - Klasycystyczny TDD, unikanie mocków
- **AAA Pattern** - Arrange-Act-Assert pattern
- **GWT Pattern** - Given-When-Then (BDD)

### Checklista testu

- **Checklista Testu Na Poziomie L** (v. 0.3.0-pps-2025)
- **Cheat Sheet AAA** - Struktura testu z komentarzami

---

**Ostatnia aktualizacja:** 2025-01-07  
**Autor:** MovieMind API Team  
**Zadanie:** TASK-030
