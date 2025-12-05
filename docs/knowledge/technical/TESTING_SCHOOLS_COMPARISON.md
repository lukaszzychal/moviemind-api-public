# Szkoły Testowania: Porównanie i Praktyczne Zastosowanie

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Wyjaśnienie różnych szkół testowania z naciskiem na różnice między Chicago School a Detroit School  
> **Kategoria:** technical

## 🎯 Cel

Dokument wyjaśnia różne szkoły testowania jednostkowego, ze szczególnym naciskiem na różnice między **Chicago School** a **Detroit School**, oraz praktyczne zastosowanie w projekcie MovieMind API.

---

## 📚 Główne Szkoły Testowania

---

## 📚 Główne Szkoły Testowania

### 1. **London School (Mockist / Interaction-Based Testing)**

**Charakterystyka:**

- Testuje w **izolacji** - każda klasa osobno
- **Mockuje wszystkie zależności** (nawet wewnętrzne)
- Weryfikuje **interakcje** (czy metody zostały wywołane)
- Skupia się na **implementacji**, nie tylko na wyniku

**Przykład z projektu:**

```php
// api/tests/Unit/Services/TmdbVerificationServiceTest.php
$mockClient = Mockery::mock(TMDBClient::class);
$mockSearchClient = Mockery::mock();
$mockResponse = Mockery::mock(ResponseInterface::class);
$mockBody = Mockery::mock(StreamInterface::class);

$mockClient->shouldReceive('search')
    ->andReturn($mockSearchClient);

$mockSearchClient->shouldReceive('movies')
    ->with('test movie')
    ->andReturn($mockResponse);

// ... więcej mocków

$service = new TmdbVerificationService($apiKey);
// Wstrzyknięcie mocka przez reflection
$reflection = new \ReflectionClass($service);
$clientProperty = $reflection->getProperty('client');
$clientProperty->setAccessible(true);
$clientProperty->setValue($service, $mockClient);

$result = $service->verifyMovie('test-movie');
```

**Zalety:**

- ⚡ Szybkie testy (bez zewnętrznych zależności)
- ✅ Wysoka izolacja
- ✅ Łatwe testowanie edge cases
- ✅ Nie wymaga setupu bazy danych

**Wady:**

- ❌ Testy mogą być kruche (zmiana implementacji = zmiana testów)
- ❌ Możliwe przetestowanie implementacji zamiast zachowania
- ❌ Dużo boilerplate code (mockowanie wielu zależności)
- ❌ Może nie wykryć błędów integracyjnych

**Kiedy używać:**

- Testowanie zewnętrznych API (TMDb, OpenAI) - kosztowne i niestabilne
- Testowanie edge cases bez setupu bazy danych
- Testowanie logiki, która nie zależy od stanu

---

### 2. **Chicago School (Classical / Behavior-Based Testing)**

**Charakterystyka:**

- Testuje **zachowanie**, nie implementację
- Używa **prawdziwych obiektów** gdzie to możliwe
- Mockuje **tylko zewnętrzne zależności** (API, baza danych, pliki)
- Weryfikuje **stan końcowy** i **efekty uboczne**

**Kluczowa różnica od Detroit School:**

- Chicago School skupia się na **zachowaniu systemu** (co system robi)
- Testuje **interakcje między obiektami** w systemie
- Weryfikuje **efekty uboczne** (np. zapis do bazy, wysłanie eventu)

**Przykład z projektu:**

```php
// api/tests/Feature/MoviesApiTest.php
public function test_list_movies_returns_ok(): void
{
    // Używa prawdziwej bazy danych (SQLite :memory:)
    // Używa prawdziwych modeli Eloquent
    $response = $this->getJson('/api/v1/movies');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'release_year', 'director', 'descriptions_count',
                ],
            ],
        ]);

    // Weryfikuje stan końcowy (struktura odpowiedzi)
    $this->assertIsInt($response->json('data.0.descriptions_count'));
}
```

**Inny przykład - testowanie zachowania z prawdziwymi obiektami:**

```php
// Przykład: Testowanie serwisu z prawdziwymi zależnościami
public function test_movie_service_creates_movie_with_descriptions(): void
{
    // Używa prawdziwego repozytorium (nie mock)
    $repository = new MovieRepository();
    $service = new MovieService($repository);
    
    $movie = $service->create([
        'title' => 'The Matrix',
        'release_year' => 1999,
    ]);
    
    // Weryfikuje zachowanie - czy film został utworzony
    $this->assertNotNull($movie);
    $this->assertSame('The Matrix', $movie->title);
    
    // Weryfikuje efekt uboczny - czy opis został utworzony
    $this->assertTrue($movie->descriptions()->exists());
}
```

**Zalety:**

- ✅ Testy bardziej odporne na refaktoryzację
- ✅ Testują rzeczywiste zachowanie systemu
- ✅ Lepsze wykrywanie błędów integracyjnych
- ✅ Testują efekty uboczne (zapis do bazy, eventy)

**Wady:**

- 🐢 Wolniejsze (prawdziwe obiekty)
- ⚠️ Większa złożoność setupu
- ⚠️ Trudniejsze testowanie edge cases bez mocków

**Kiedy używać:**

- Testy Feature (API endpoints)
- Testowanie logiki biznesowej z prawdziwymi zależnościami
- Testowanie efektów ubocznych (zapis, eventy)

---

### 3. **Detroit School (State-Based Testing)**

**Charakterystyka:**

- Podobna do Chicago School, ale z **innym fokusem**
- Skupia się na **stanie obiektów** (transformacje danych)
- Testuje **transformacje danych** (input → output)
- Mockuje tylko **zewnętrzne serwisy** (API, baza danych)

**Kluczowa różnica od Chicago School:**

- Detroit School skupia się na **stanie obiektów** (jak dane się zmieniają)
- Chicago School skupia się na **zachowaniu systemu** (co system robi)
- Detroit School weryfikuje **transformacje danych**
- Chicago School weryfikuje **efekty uboczne i interakcje**

**Przykład - Detroit School (stan obiektu):**

```php
// Przykład: Testowanie transformacji danych
public function test_movie_slug_generation(): void
{
    $service = new SlugService();
    
    // Input
    $title = 'The Matrix';
    $year = 1999;
    
    // Transformacja
    $slug = $service->generateSlug($title, $year);
    
    // Weryfikuje stan końcowy (transformacja danych)
    $this->assertSame('the-matrix-1999', $slug);
}

// Przykład: Testowanie transformacji z cache
public function test_verify_movie_uses_cache_when_available(): void
{
    $apiKey = 'test-api-key';
    config(['services.tmdb.api_key' => $apiKey]);

    // Stan początkowy - dane w cache
    $cachedData = [
        'title' => 'Cached Movie',
        'release_date' => '2000-01-01',
        'overview' => 'Cached overview',
        'id' => 456,
    ];

    Cache::put('tmdb:movie:test-movie', $cachedData, now()->addHours(24));

    $service = new TmdbVerificationService($apiKey);

    // Transformacja - weryfikacja filmu
    $result = $service->verifyMovie('test-movie');

    // Weryfikuje stan końcowy (transformacja danych)
    $this->assertNotNull($result);
    $this->assertSame($cachedData, $result);
}
```

**Przykład - Chicago School (zachowanie systemu):**

```php
// Przykład: Testowanie zachowania z efektami ubocznymi
public function test_movie_creation_triggers_event(): void
{
    Event::fake(); // Mock tylko dla eventów (zewnętrzna zależność)
    
    $repository = new MovieRepository(); // Prawdziwy obiekt
    $service = new MovieService($repository); // Prawdziwy obiekt
    
    $movie = $service->create([
        'title' => 'The Matrix',
        'release_year' => 1999,
    ]);
    
    // Weryfikuje zachowanie - czy event został wysłany (efekt uboczny)
    Event::assertDispatched(MovieCreated::class, function ($event) use ($movie) {
        return $event->movie->id === $movie->id;
    });
    
    // Weryfikuje stan końcowy
    $this->assertNotNull($movie);
}
```

**Zalety:**

- ✅ Testy odporne na refaktoryzację
- ✅ Skupia się na transformacjach danych
- ✅ Łatwe do zrozumienia (input → output)
- ✅ Testuje logikę biznesową bez efektów ubocznych

**Wady:**

- 🐢 Wolniejsze niż London School
- ⚠️ Może nie wykryć problemów z efektami ubocznymi
- ⚠️ Wymaga setupu dla transformacji danych

**Kiedy używać:**

- Testowanie transformacji danych (slug generation, formatowanie)
- Testowanie logiki biznesowej bez efektów ubocznych
- Testowanie cache i transformacji danych

---

### 4. **Outside-In TDD (Acceptance Test-Driven Development)**

**Charakterystyka:**

- Zaczyna od **testów akceptacyjnych** (Feature Tests)
- Schodzi w dół do testów jednostkowych
- Używa mocków **strategicznie** (tylko zewnętrzne zależności)
- Testuje **cały flow** od góry do dołu

**Przykład z projektu:**

```php
// api/tests/Feature/MissingEntityGenerationTest.php
public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
{
    Feature::activate('ai_description_generation');

    // Mock tylko zewnętrznej zależności (TMDb API)
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

    // Test całego flow (endpoint → service → repository)
    $res = $this->getJson('/api/v1/movies/annihilation');
    
    // Weryfikuje zachowanie całego systemu
    $res->assertStatus(202);
}
```

**Zalety:**

- ✅ Testuje cały flow
- ✅ Najlepsze wykrywanie błędów
- ✅ Testy odporne na refaktoryzację
- ✅ Zaczyna od wymagań biznesowych

**Wady:**

- 🐢 Najwolniejsze
- ⚠️ Wymaga pełnego setupu
- ⚠️ Trudniejsze debugowanie

**Kiedy używać:**

- Testy Feature (API endpoints)
- Testowanie całego flow
- Testy akceptacyjne

---

## 🔍 Kluczowe Różnice: Chicago School vs Detroit School

### Chicago School - Zachowanie Systemu

**Fokus:** Co system **robi** (zachowanie)

**Weryfikuje:**

- Efekty uboczne (zapis do bazy, eventy, logi)
- Interakcje między obiektami
- Zachowanie systemu jako całości

**Przykład:**

```php
// Testuje ZACHOWANIE - czy system wysyła event
public function test_movie_creation_sends_event(): void
{
    Event::fake();
    
    $service = new MovieService(new MovieRepository());
    $movie = $service->create(['title' => 'The Matrix']);
    
    // Weryfikuje ZACHOWANIE (efekt uboczny)
    Event::assertDispatched(MovieCreated::class);
}
```

### Detroit School - Stan Obiektów

**Fokus:** Jak dane się **zmieniają** (transformacje)

**Weryfikuje:**

- Transformacje danych (input → output)
- Stan obiektów przed i po operacji
- Logikę biznesową bez efektów ubocznych

**Przykład:**

```php
// Testuje STAN - transformację danych
public function test_slug_generation(): void
{
    $service = new SlugService();
    
    // Input
    $title = 'The Matrix';
    $year = 1999;
    
    // Transformacja
    $slug = $service->generateSlug($title, $year);
    
    // Weryfikuje STAN (transformacja danych)
    $this->assertSame('the-matrix-1999', $slug);
}
```

### Porównanie Praktyczne

| Aspekt                      | Chicago School                | Detroit School               |
| --------------------------- | ----------------------------- | ---------------------------- |
| **Fokus**                   | Zachowanie systemu            | Transformacje danych         |
| **Weryfikuje**              | Efekty uboczne, interakcje   | Stan obiektów, transformacje |
| **Przykład**                | Czy event został wysłany?     | Jak slug został wygenerowany? |
| **Testuje**                  | Co system robi                 | Jak dane się zmieniają        |
| **Mockuje**                  | Tylko zewnętrzne zależności   | Tylko zewnętrzne zależności   |
| **Używa prawdziwych obiektów** | ✅ Tak                        | ✅ Tak                         |

**W praktyce:**

- **Chicago School** - "Czy system poprawnie reaguje na akcję?"
- **Detroit School** - "Czy dane zostały poprawnie przetworzone?"

---

## 📊 Porównanie Wszystkich Szkół

| Aspekt                      | London School        | Chicago School       | Detroit School       | Outside-In            |
| --------------------------- | -------------------- | -------------------- | -------------------- | --------------------- |
| **Mockowanie**               | Wszystkie zależności | Tylko zewnętrzne     | Tylko zewnętrzne     | Strategiczne          |
| **Fokus**                    | Implementacja        | Zachowanie           | Stan                 | Akceptacja            |
| **Szybkość**                 | ⚡ Szybkie            | 🐢 Wolniejsze         | 🐢 Wolniejsze         | 🐢 Wolniejsze          |
| **Izolacja**                 | ✅ Wysoka             | ⚠️ Średnia            | ⚠️ Średnia            | ⚠️ Średnia             |
| **Odporność na refaktoryzację** | ❌ Niska        | ✅ Wysoka             | ✅ Wysoka             | ✅ Wysoka              |
| **Wykrywanie błędów**        | ⚠️ Ograniczone        | ✅ Dobre              | ✅ Dobre              | ✅ Najlepsze           |
| **Testuje efekty uboczne**   | ❌ Nie                | ✅ Tak                | ⚠️ Częściowo          | ✅ Tak                 |
| **Testuje transformacje**   | ⚠️ Częściowo         | ⚠️ Częściowo         | ✅ Tak                | ✅ Tak                |

---

## 🎯 Rekomendacja dla Projektu MovieMind API

### Obecne Podejście (Hybrydowe)

Projekt używa **hybrydowego podejścia**:

1. **Unit Tests** → **London School** (mocki dla zewnętrznych API)
   - TMDb API - kosztowne i niestabilne
   - OpenAI API - kosztowne i niestabilne

2. **Feature Tests** → **Chicago School / Outside-In** (prawdziwe obiekty, mocki tylko dla zewnętrznych serwisów)
   - Prawdziwa baza danych (SQLite `:memory:`)
   - Prawdziwe modele Eloquent
   - Mocki tylko dla zewnętrznych API

### Zasady Mockowania

**Mockuj TYLKO:**

- ✅ Zewnętrzne API (TMDb, OpenAI) - kosztowne i niestabilne
- ✅ Eventy i Queue (Event::fake(), Queue::fake()) - asynchroniczne operacje
- ✅ Cache (Cache::fake()) - jeśli testujesz logikę cache

**NIE mockuj:**

- ❌ Repozytoriów (używaj prawdziwych z SQLite)
- ❌ Modeli Eloquent (używaj prawdziwych)
- ❌ Serwisów biznesowych (używaj prawdziwych)
- ❌ Wewnętrznych zależności (używaj prawdziwych)

### Przykłady z Projektu

#### ✅ Dobry Przykład - Chicago School

```php
// api/tests/Feature/MoviesApiTest.php
public function test_list_movies_returns_ok(): void
{
    // Prawdziwa baza danych (SQLite :memory:)
    // Prawdziwe modele Eloquent
    $response = $this->getJson('/api/v1/movies');
    
    // Weryfikuje zachowanie systemu
    $response->assertOk()
        ->assertJsonStructure([...]);
}
```

#### ✅ Dobry Przykład - Detroit School

```php
// api/tests/Unit/Services/TmdbVerificationServiceTest.php
public function test_verify_movie_uses_cache_when_available(): void
{
    // Prawdziwy serwis
    $service = new TmdbVerificationService($apiKey);
    
    // Stan początkowy - dane w cache
    Cache::put('tmdb:movie:test-movie', $cachedData, now()->addHours(24));
    
    // Transformacja
    $result = $service->verifyMovie('test-movie');
    
    // Weryfikuje transformację danych (stan)
    $this->assertSame($cachedData, $result);
}
```

#### ⚠️ Przykład do Refaktoryzacji - London School (za dużo mocków)

```php
// Obecny przykład - za dużo mocków
$mockClient = Mockery::mock(TMDBClient::class);
$mockSearchClient = Mockery::mock();
$mockResponse = Mockery::mock(ResponseInterface::class);
$mockBody = Mockery::mock(StreamInterface::class);
// ... więcej mocków

// Lepsze podejście - mock tylko zewnętrznego API
$this->mock(TMDBClient::class, function ($mock) {
    $mock->shouldReceive('search')
        ->andReturn($mockSearchClient);
});
```

---

## 📝 Praktyczne Zasady

### 1. Zasada "Mock Only External"

**Mockuj tylko zewnętrzne zależności:**

- API (TMDb, OpenAI)
- Baza danych (w testach jednostkowych)
- Pliki systemowe
- Eventy i Queue (Event::fake(), Queue::fake())

**Używaj prawdziwych obiektów dla:**

- Repozytoriów
- Modeli
- Serwisów biznesowych
- Wewnętrznych zależności

### 2. Zasada "Test Behavior, Not Implementation"

**Dobrze (Chicago School):**

```php
// Testuje zachowanie - czy film został utworzony
$movie = $service->create(['title' => 'The Matrix']);
$this->assertNotNull($movie);
$this->assertSame('The Matrix', $movie->title);
```

**Źle (London School - za dużo mocków):**

```php
// Testuje implementację - czy metoda została wywołana
$repository->shouldReceive('create')->once()->andReturn($movie);
$service->create(['title' => 'The Matrix']);
```

### 3. Zasada "Test Transformations, Not Interactions"

**Dobrze (Detroit School):**

```php
// Testuje transformację danych
$slug = $service->generateSlug('The Matrix', 1999);
$this->assertSame('the-matrix-1999', $slug);
```

**Źle (London School - testuje interakcje):**

```php
// Testuje interakcje zamiast transformacji
$formatter->shouldReceive('format')->once()->andReturn('the-matrix-1999');
$slug = $service->generateSlug('The Matrix', 1999);
```

---

## 🔧 Framework-Agnostic Testing

### Własne Test Doubles zamiast Mockery

Projekt używa **własnych test doubles** (implementujących interfejsy) zamiast Mockery dla większości testów.

#### Przykład: Własny Fake

```php
// Użycie własnego fake zamiast Mockery
$fake = $this->fakeEntityVerificationService();
$fake->setMovie('annihilation', [
    'title' => 'Annihilation',
    'release_date' => '2018-02-23',
    // ...
]);
```

**Zalety:**

- ✅ Framework-agnostic - zwykły kod PHP
- ✅ Type-safe - implementuje interfejsy
- ✅ Czytelniejsze - jasny kod zamiast `shouldReceive()`
- ✅ Reużywalne - można używać w różnych testach

#### Kiedy używać Mockery?

Mockery jest używany **tylko dla zewnętrznych bibliotek bez interfejsów**:

```php
// TmdbVerificationServiceTest.php - Mockery dla zewnętrznej biblioteki
$mockClient = Mockery::mock(TMDBClient::class); // Zewnętrzna biblioteka bez interfejsu
```

**Zasada:** Mockery tylko dla zewnętrznych zależności bez interfejsów, własne doubles dla interfejsów aplikacji.

#### Struktura Test Doubles

```text
api/tests/Doubles/
├── Services/
│   ├── FakeEntityVerificationService.php
│   └── FakeOpenAiClient.php
└── Repositories/
    └── (opcjonalnie - lepiej użyć prawdziwego z SQLite)
```

#### Helper Methods w TestCase

```php
// api/tests/TestCase.php
$fake = $this->fakeEntityVerificationService();
$fake->setMovie('slug', [...]);
```

**Więcej informacji:** Zobacz [Framework-Agnostic Testing](../technical/FRAMEWORK_AGNOSTIC_TESTING.md)

---

## 🔗 Powiązane Dokumenty

- [Framework-Agnostic Testing](../technical/FRAMEWORK_AGNOSTIC_TESTING.md) - Własne test doubles vs Mockery
- [Testing Strategy](../reference/TESTING_STRATEGY.md) - Strategia testowania projektu
- [Mock vs Real Jobs](../technical/MOCK_VS_REAL_JOBS.md) - Konfiguracja mock/real jobs
- [TDD Rules](../../.cursor/rules/testing.mdc) - Zasady TDD w projekcie

---

**Ostatnia aktualizacja:** 2025-01-27
