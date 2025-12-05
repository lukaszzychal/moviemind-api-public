# Framework-Agnostic Testing: Własne Test Doubles vs Mockery

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Analiza i porównanie podejść do testowania - własne test doubles vs Mockery dla framework-agnostic testing  
> **Kategoria:** technical

## 🎯 Cel

Dokument analizuje różne podejścia do tworzenia test doubles w testach, porównując użycie Mockery z własnymi implementacjami test doubles (implementującymi interfejsy) w kontekście framework-agnostic testing.

---

## 📊 Obecna Sytuacja w Projekcie

### Użycie Mockery

Projekt używa Mockery w **3 plikach testowych**:

1. **`api/tests/Feature/MissingEntityGenerationTest.php`**
   - Używa `$this->mock(TmdbVerificationService::class)` (Laravel helper)
   - Mockuje `EntityVerificationServiceInterface` dla testów Feature

2. **`api/tests/Unit/Services/TmdbVerificationServiceTest.php`**
   - Używa `Mockery::mock(TMDBClient::class)` z reflection
   - Mockuje zewnętrzną bibliotekę TMDb Client

3. **`api/tests/Unit/Services/MovieDisambiguationServiceTest.php`**
   - Używa `Mockery::mock(MovieRepository::class)`
   - Mockuje wewnętrzne repozytorium

### Istniejące Interfejsy

Projekt ma już dobrze zdefiniowane interfejsy:

- **`EntityVerificationServiceInterface`** - używany w `MovieController` przez DI
- **`OpenAiClientInterface`** - używany w Jobs przez DI
- Bindowanie w `AppServiceProvider` do konkretnych implementacji

---

## 🔍 Porównanie Podejść

### Mockery (Obecne Podejście)

#### Przykład użycia:

```php
// MissingEntityGenerationTest.php
$this->mock(TmdbVerificationService::class, function ($mock) {
    $mock->shouldReceive('verifyMovie')
        ->with('annihilation')
        ->andReturn([
            'title' => 'Annihilation',
            'release_date' => '2018-02-23',
            // ...
        ]);
});
```

#### Zalety Mockery:

1. ✅ **Szybkie** - jedna linia kodu do stworzenia mocka
2. ✅ **Zaawansowane features** - partial mocks, expectations, spies
3. ✅ **Elastyczne** - łatwe do konfiguracji w testach
4. ✅ **Popularne** - szeroko używane w społeczności Laravel

#### Wady Mockery:

1. ❌ **Framework-dependent** - zależy od Laravel/Mockery
2. ❌ **Mniej czytelne** - `shouldReceive()` może być mylące dla nowych deweloperów
3. ❌ **Trudniejsze debugowanie** - błędy Mockery mogą być niejasne
4. ❌ **Reflection** - wymaga reflection w niektórych przypadkach (np. `TmdbVerificationServiceTest`)
5. ❌ **Type safety** - PHPStan/IDE nie zawsze wspierają dobrze
6. ❌ **Tight coupling** - testy zależą od konkretnej biblioteki

---

### Własne Test Doubles (Proponowane Podejście)

#### Przykład użycia:

```php
// MissingEntityGenerationTest.php
$fake = new FakeEntityVerificationService();
$fake->setMovie('annihilation', [
    'title' => 'Annihilation',
    'release_date' => '2018-02-23',
    'overview' => 'A biologist signs up for a dangerous expedition.',
    'id' => 300668,
    'director' => 'Alex Garland',
]);
$this->app->instance(EntityVerificationServiceInterface::class, $fake);
```

#### Zalety Własnych Test Doubles:

1. ✅ **Framework-agnostic** - zwykłe klasy PHP, nie zależy od Laravel/Mockery
2. ✅ **Prostsze** - łatwe do zrozumienia, zwykły kod PHP
3. ✅ **Type-safe** - implementują interfejsy, PHPStan/IDE wspierają w pełni
4. ✅ **Reużywalne** - można tworzyć różne warianty (stub, fake, spy)
5. ✅ **Czytelniejsze** - jasny kod zamiast `shouldReceive()`
6. ✅ **Testowalne** - można testować same test doubles
7. ✅ **Maintainable** - łatwiejsze do utrzymania, zmiany w interfejsach są widoczne od razu
8. ✅ **Explicit** - jasno widać co fake robi, bez magic methods

#### Wady Własnych Test Doubles:

1. ❌ **Więcej kodu** - trzeba pisać klasy zamiast jednej linii
2. ❌ **Brak zaawansowanych features** - Mockery ma więcej opcji (partial mocks, etc.)
3. ❌ **Maintenance** - trzeba aktualizować przy zmianie interfejsów
4. ❌ **Initial setup** - wymaga stworzenia struktury katalogów i klas

---

## 📋 Typy Test Doubles

### 1. Fake

**Pełna implementacja z konfiguracją** - działa jak prawdziwy obiekt, ale z uproszczoną logiką.

```php
class FakeEntityVerificationService implements EntityVerificationServiceInterface
{
    private array $movies = [];
    private array $people = [];
    
    public function setMovie(string $slug, ?array $data): void
    {
        $this->movies[$slug] = $data;
    }
    
    public function verifyMovie(string $slug): ?array
    {
        return $this->movies[$slug] ?? null;
    }
    
    // ... implementacja pozostałych metod
}
```

**Użycie:** Gdy potrzebujesz pełnej implementacji z możliwością konfiguracji.

### 2. Stub

**Minimalna implementacja zwracająca dane** - tylko zwraca dane, bez logiki.

```php
class StubEntityVerificationService implements EntityVerificationServiceInterface
{
    public function __construct(
        private readonly ?array $movieData = null,
        private readonly ?array $personData = null
    ) {}
    
    public function verifyMovie(string $slug): ?array
    {
        return $this->movieData;
    }
    
    // ... minimalna implementacja
}
```

**Użycie:** Gdy potrzebujesz tylko zwrócić dane, bez konfiguracji.

### 3. Spy

**Rejestruje wywołania** - zapisuje informacje o wywołaniach metod.

```php
class SpyEntityVerificationService implements EntityVerificationServiceInterface
{
    private array $calls = [];
    
    public function verifyMovie(string $slug): ?array
    {
        $this->calls['verifyMovie'][] = $slug;
        return null;
    }
    
    public function getCalls(): array
    {
        return $this->calls;
    }
}
```

**Użycie:** Gdy chcesz zweryfikować, że metody zostały wywołane.

---

## 🎯 Rekomendacja dla Projektu

### Strategia Hybrydowa (Zalecana)

**Użyj własnych test doubles dla interfejsów, Mockery tylko dla zewnętrznych bibliotek.**

#### Własne Test Doubles dla:

1. ✅ **Interfejsy aplikacji** (`EntityVerificationServiceInterface`, `OpenAiClientInterface`)
   - Framework-agnostic
   - Type-safe
   - Łatwe do utrzymania

2. ✅ **Repozytoria** (opcjonalnie - lepiej użyć prawdziwego z SQLite - Chicago School)
   - Jeśli mockujemy, użyj własnego fake
   - Lepsze: użyj prawdziwego repozytorium z test database

#### Mockery tylko dla:

1. ⚠️ **Zewnętrzne biblioteki** (np. `TMDBClient` z pakietu `lukaszzychal/tmdb-client-php`)
   - Gdy nie mamy interfejsu
   - Gdy biblioteka jest zbyt złożona do fake'owania

### Przykład Strategii

```php
// ✅ DOBRZE - Własny fake dla interfejsu
$fake = new FakeEntityVerificationService();
$fake->setMovie('annihilation', [...]);
$this->app->instance(EntityVerificationServiceInterface::class, $fake);

// ⚠️ OK - Mockery dla zewnętrznej biblioteki (gdy nie ma interfejsu)
$mockClient = Mockery::mock(TMDBClient::class);
// ... tylko gdy nie możemy użyć prawdziwego obiektu

// ✅ NAJLEPIEJ - Prawdziwy obiekt z test database (Chicago School)
$repository = new MovieRepository();
// Używa SQLite :memory: - prawdziwy obiekt, szybki test
```

---

## 📐 Implementacja: Struktura Katalogów

```
api/tests/
├── Doubles/
│   ├── Services/
│   │   ├── FakeEntityVerificationService.php
│   │   ├── FakeOpenAiClient.php
│   │   └── SpyEntityVerificationService.php
│   └── Repositories/
│       └── FakeMovieRepository.php (opcjonalnie)
├── Feature/
│   └── MissingEntityGenerationTest.php (refaktoryzowany)
└── Unit/
    └── Services/
        ├── TmdbVerificationServiceTest.php (refaktoryzowany)
        └── MovieDisambiguationServiceTest.php (refaktoryzowany)
```

---

## 🔄 Przykłady Refaktoryzacji

### Przykład 1: MissingEntityGenerationTest

#### Przed (Mockery):

```php
$this->mock(TmdbVerificationService::class, function ($mock) {
    $mock->shouldReceive('verifyMovie')
        ->with('annihilation')
        ->andReturn([
            'title' => 'Annihilation',
            'release_date' => '2018-02-23',
            'overview' => 'A biologist signs up for a dangerous expedition.',
            'id' => 300668,
            'director' => 'Alex Garland',
        ]);
});
```

#### Po (Własny Fake):

```php
$fake = new FakeEntityVerificationService();
$fake->setMovie('annihilation', [
    'title' => 'Annihilation',
    'release_date' => '2018-02-23',
    'overview' => 'A biologist signs up for a dangerous expedition.',
    'id' => 300668,
    'director' => 'Alex Garland',
]);
$this->app->instance(EntityVerificationServiceInterface::class, $fake);
```

**Zalety:**
- ✅ Czytelniejsze - jasno widać co fake robi
- ✅ Type-safe - IDE wspiera autocomplete
- ✅ Framework-agnostic - zwykły kod PHP

### Przykład 2: MovieDisambiguationServiceTest

#### Przed (Mockery):

```php
$repository = Mockery::mock(MovieRepository::class);
$repository->shouldReceive('findAllByTitleSlug')
    ->once()
    ->with('the-matrix')
    ->andReturn($otherMovies);
```

#### Po (Prawdziwy Repozytorium - Chicago School):

```php
// Użyj prawdziwego repozytorium z test database
$repository = new MovieRepository();
// Dane są w bazie (SQLite :memory:)
```

**Lub Po (Własny Fake - jeśli potrzebny):**

```php
$fake = new FakeMovieRepository();
$fake->setMoviesByTitleSlug('the-matrix', $otherMovies);
```

**Zalety:**
- ✅ Testuje prawdziwe zachowanie (Chicago School)
- ✅ Lepsze wykrywanie błędów integracyjnych
- ✅ Framework-agnostic

---

## 🎓 Framework-Agnostic Testing

### Co to znaczy?

**Framework-agnostic testing** oznacza testy, które nie zależą od konkretnego frameworka (Laravel, Mockery, etc.) i mogą działać w różnych środowiskach.

### Zalety:

1. ✅ **Przenośność** - testy mogą działać w różnych frameworkach
2. ✅ **Niezależność** - nie zależą od konkretnych bibliotek
3. ✅ **Prostota** - zwykły kod PHP, łatwy do zrozumienia
4. ✅ **Maintainability** - łatwiejsze do utrzymania

### Przykład Framework-Agnostic Test:

```php
// Framework-agnostic - nie używa Laravel helpers
class MovieServiceTest extends PHPUnit\Framework\TestCase
{
    public function test_creates_movie(): void
    {
        $fake = new FakeEntityVerificationService();
        $fake->setMovie('the-matrix', [...]);
        
        $service = new MovieService($fake);
        $movie = $service->create('the-matrix');
        
        $this->assertNotNull($movie);
    }
}
```

### Przykład Framework-Dependent Test:

```php
// Framework-dependent - używa Laravel helpers
class MovieServiceTest extends Tests\TestCase
{
    public function test_creates_movie(): void
    {
        $this->mock(TmdbVerificationService::class, function ($mock) {
            $mock->shouldReceive('verifyMovie')->andReturn([...]);
        });
        
        $service = $this->app->make(MovieService::class);
        // ...
    }
}
```

---

## 📊 Porównanie: Mockery vs Własne Doubles

| Aspekt | Mockery | Własne Doubles |
| ------ | ------- | -------------- |
| **Framework-agnostic** | ❌ Nie | ✅ Tak |
| **Type safety** | ⚠️ Częściowo | ✅ Pełne |
| **Czytelność** | ⚠️ Średnia | ✅ Wysoka |
| **Szybkość implementacji** | ✅ Szybka | ⚠️ Wolniejsza |
| **Maintenance** | ⚠️ Średnie | ✅ Łatwe |
| **Zaawansowane features** | ✅ Tak | ❌ Nie |
| **Debugowanie** | ⚠️ Trudne | ✅ Łatwe |
| **Reużywalność** | ⚠️ Ograniczona | ✅ Wysoka |
| **Testowalność** | ❌ Nie | ✅ Tak |

---

## 🎯 Rekomendacje

### Dla Projektu MovieMind API:

1. **Użyj własnych test doubles dla interfejsów**
   - `EntityVerificationServiceInterface` → `FakeEntityVerificationService`
   - `OpenAiClientInterface` → `FakeOpenAiClient`

2. **Użyj prawdziwych obiektów dla repozytoriów** (Chicago School)
   - `MovieRepository` → prawdziwy z SQLite `:memory:`
   - Lepsze wykrywanie błędów integracyjnych

3. **Mockery tylko dla zewnętrznych bibliotek** (gdy nie ma interfejsu)
   - `TMDBClient` z pakietu `lukaszzychal/tmdb-client-php`
   - Tylko gdy nie możemy użyć prawdziwego obiektu

4. **Stwórz helper methods w TestCase**
   - `fakeEntityVerificationService()` - zwraca skonfigurowany fake
   - `fakeOpenAiClient()` - zwraca skonfigurowany fake

---

## 🔗 Powiązane Dokumenty

- [Testing Schools Comparison](../technical/TESTING_SCHOOLS_COMPARISON.md) - Porównanie szkół testowania
- [Testing Strategy](../reference/TESTING_STRATEGY.md) - Strategia testowania projektu
- [TDD Rules](../../.cursor/rules/testing.mdc) - Zasady TDD w projekcie

---

**Ostatnia aktualizacja:** 2025-01-27

