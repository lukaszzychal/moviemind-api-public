# Zasady Kontekstu dla AI Agenta

## 📋 Przegląd

Ten dokument zawiera zasady i wytyczne, które AI Agent powinien stosować podczas pracy z kodem w projekcie MovieMind API. Celem jest zapewnienie wysokiej jakości kodu, zgodności z najlepszymi praktykami oraz utrzymanie spójności w całym projekcie.

> **💡 Uwaga:** 
> - **Reguły** są dostępne w `.cursor/rules/*.mdc` (nowy format, automatycznie wczytywany przez Cursor IDE)
> - **Stary format** `.cursorrules` jest przestarzały i został zastąpiony
> - **Kontekst projektu** jest w `CLAUDE.md` (wczytywany gdy opcja "Include CLAUDE.md in context" jest włączona)
> - **Szczegóły** - ten dokument zawiera szczegółowe wyjaśnienia i przykłady
> - **Wyjaśnienie różnic** - zobacz `docs/CURSOR_RULES_EXPLANATION.md`

### 📑 Spis treści

1. [🧪 Test Driven Development (TDD)](#-test-driven-development-tdd)
2. [🔧 Narzędzia Jakości Kodu](#-narzędzia-jakości-kodu)
3. [🔄 Workflow przed Commitem](#-workflow-przed-commitem)
4. [📝 Zasady Pisania Kodu](#-zasady-pisania-kodu)
   - [🏛️ SOLID](#️-solid---zasady-projektowania-obiektowego)
   - [🔄 DRY](#-dry-dont-repeat-yourself)
   - [🎯 GRASP](#-grasp-general-responsibility-assignment-software-patterns)
   - [💎 CUPID](#-cupid---właściwości-dobrego-kodu)
   - [👃 Code Smells](#-code-smells---zapachy-kodu)
5. [🚫 Co NIE robić](#-co-nie-robic)
6. [🔍 Checklist przed Commitem](#-checklist-przed-commitem)
7. [📚 Dodatkowe Zasoby](#-dodatkowe-zasoby)
8. [🎯 Priorytety](#-priorytety)
9. [📝 Uwagi końcowe](#-uwagi-końcowe)

---

## 🧪 Test Driven Development (TDD)

### Zasada podstawowa
**Zawsze pisz testy przed implementacją funkcjonalności. Zastosuj cykl Red-Green-Refactor.**

### Cykl TDD

1. **RED** - Napisz test, który definiuje oczekiwane zachowanie
2. **GREEN** - Napisz minimalny kod potrzebny do przejścia testu
3. **REFACTOR** - Popraw kod, zachowując przechodzące testy

### Wytyczne dla AI Agenta

#### ✅ Zawsze:
- **Pisz testy przed implementacją** - najpierw test, potem kod
- **Sprawdzaj testy po każdej zmianie** - uruchamiaj `php artisan test`
- **Utrzymuj pokrycie testami** - nowy kod musi mieć testy
- **Używaj Feature Tests** - dla endpointów API i integracji
- **Używaj Unit Tests** - dla logiki biznesowej i serwisów

#### ❌ Nigdy:
- Nie commituj kodu bez testów
- Nie pomijaj testów "bo to mała zmiana"
- Nie ignoruj failujących testów

### Przykład TDD Workflow

```php
// 1. RED - Test definiuje wymaganie
public function test_can_create_movie_with_valid_data(): void
{
    $response = $this->postJson('/api/v1/movies', [
        'title' => 'The Matrix',
        'release_year' => 1999,
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure(['id', 'title', 'release_year']);
}

// 2. GREEN - Minimalna implementacja
public function store(Request $request)
{
    return Movie::create($request->validated());
}

// 3. REFACTOR - Ulepszenie kodu
public function store(StoreMovieRequest $request)
{
    return Movie::create($request->validated());
}
```

### Rodzaje testów w projekcie

#### 1. Unit Tests (`tests/Unit/`)
- Testują pojedyncze klasy i metody
- Szybkie, izolowane
- Przykład: `MovieServiceTest`, `ValidationHelperTest`

#### 2. Feature Tests (`tests/Feature/`)
- Testują endpointy API i integrację
- Używają bazy testowej (SQLite `:memory:`)
- Przykład: `MovieControllerTest`, `GenerateApiTest`

#### 3. Integration Tests (gdy potrzebne)
- Testują specyficzne SQL features
- Używają PostgreSQL (jak produkcja)
- Uruchamiane rzadziej

### Uruchamianie testów

```bash
# Wszystkie testy
php artisan test

# Tylko feature tests
php artisan test --testsuite=Feature

# Tylko unit tests
php artisan test --testsuite=Unit

# Konkretny test
php artisan test tests/Feature/MovieControllerTest.php

# Z pokryciem (jeśli skonfigurowane)
php artisan test --coverage
```

---

## 🔧 Narzędzia Jakości Kodu

### Przed każdym commitem AI Agent MUSI uruchomić i naprawić:

#### 1. Laravel Pint (Formatowanie kodu)

**Cel:** Zapewnienie spójnego formatowania zgodnego z PSR-12.

**Przed commitem:**
```bash
cd api && vendor/bin/pint
```

**Lub przez Artisan:**
```bash
cd api && php artisan pint
```

**Co robi:**
- Formatuje kod zgodnie z PSR-12
- Usuwa nieużywane importy
- Poprawia wcięcia i odstępy
- Naprawia końce linii

**⚠️ Wymagane:** Wszystkie pliki PHP muszą być sformatowane przed commitem.

#### 2. PHPStan (Statyczna analiza kodu)

**Cel:** Wykrywanie błędów przed uruchomieniem kodu.

**Przed commitem:**
```bash
cd api && vendor/bin/phpstan analyse --memory-limit=2G
```

**Co robi:**
- Wykrywa błędy typów
- Sprawdza wywołania nieistniejących metod
- Wykrywa potencjalne null pointer exceptions
- Sprawdza zgodność typów

**Poziom:** 5 (dobra równowaga między ścisłością a praktycznością)

**⚠️ Wymagane:** Zero błędów PHPStan przed commitem. Jeśli nie można naprawić, użyj `@phpstan-ignore` z komentarzem wyjaśniającym (oszczędnie).

#### 3. PHPUnit (Testy)

**Cel:** Upewnienie się, że wszystkie testy przechodzą.

**Przed commitem:**
```bash
cd api && php artisan test
```

**Co robi:**
- Uruchamia wszystkie testy
- Sprawdza, czy nowy kod nie zepsuł istniejących testów
- Weryfikuje, czy nowe funkcjonalności mają testy

**⚠️ Wymagane:** Wszystkie testy muszą przechodzić. Zero failujących testów.

#### 4. GitLeaks (Wykrywanie sekretów)

**Cel:** Zapobieganie przypadkowemu commitowaniu kluczy API i haseł.

**Przed commitem:**
```bash
gitleaks protect --source . --verbose --no-banner
```

**Co wykrywa:**
- Klucze API (np. `sk-...` dla OpenAI)
- Hasła i tokeny
- Klucze prywatne
- Inne wrażliwe dane

**⚠️ Wymagane:** Zero wykrytych sekretów. Jeśli to false positive, dodaj do `.gitleaks.toml`.

#### 5. Composer Audit (Audyt bezpieczeństwa)

**Cel:** Sprawdzenie zależności pod kątem znanych luk bezpieczeństwa.

**Przed commitem:**
```bash
cd api && composer audit
```

**Co robi:**
- Skanuje `composer.lock` w poszukiwaniu znanych CVE
- Wykrywa podatne zależności
- Sugeruje aktualizacje

**⚠️ Zalecane:** Napraw krytyczne luki przed commitem. Średnie i niskie można zaplanować.

---

## 🔄 Workflow przed Commitem

### Standardowy proces dla AI Agenta:

1. **Implementacja lub zmiana kodu**
   - Stosuj TDD: najpierw test, potem kod
   - Pisz czysty, czytelny kod

2. **Uruchom Laravel Pint**
   ```bash
   cd api && vendor/bin/pint
   ```
   - Napraw wszystkie problemy z formatowaniem

3. **Uruchom PHPStan**
   ```bash
   cd api && vendor/bin/phpstan analyse --memory-limit=2G
   ```
   - Napraw wszystkie błędy
   - Jeśli niemożliwe, użyj `@phpstan-ignore` z komentarzem

4. **Uruchom testy**
   ```bash
   cd api && php artisan test
   ```
   - Wszystkie testy muszą przechodzić
   - Jeśli test failuje, napraw kod lub test

5. **Uruchom GitLeaks**
   ```bash
   gitleaks protect --source . --verbose --no-banner
   ```
   - Usuń wszystkie wykryte sekrety

6. **Uruchom Composer Audit**
   ```bash
   cd api && composer audit
   ```
   - Rozważ aktualizację podatnych zależności

7. **Dodaj zmiany do gita**
   ```bash
   git add .
   ```

8. **Sprawdź jeszcze raz przed commitem**
   - Czy wszystkie narzędzia przeszły?
   - Czy testy przechodzą?
   - Czy kod jest sformatowany?

9. **Commit**
   ```bash
   git commit -m "feat: dodaj nową funkcjonalność"
   ```

---

## 📝 Zasady Pisania Kodu

### 🎯 Filozofia: Pragmatyczne podejście do zasad

**Ważne:** Zasady i wzorce są narzędziami, nie celem samym w sobie. Kod ma być:
- ✅ **Czytelny** - łatwy do zrozumienia
- ✅ **Zrozumiały** - intencja jest jasna
- ✅ **Zrefaktoryzowany** - dobrze zorganizowany, bez niepotrzebnej złożoności
- ✅ **Praktyczny** - rozwiązuje problem, nie wprowadza nadmiernej abstrakcji

**Nie stosuj zasad na siłę!** Czasami prosty kod jest lepszy niż "idealny" kod zgodny ze wszystkimi zasadami.

---

### 🏛️ SOLID - Zasady projektowania obiektowego

Stosuj SOLID jako przewodnik, ale pamiętaj o kontekście i praktyczności.

#### S - Single Responsibility Principle (SRP)
**Jedna klasa = jedna odpowiedzialność**

```php
// ❌ Złe - klasa robi za dużo
class MovieController
{
    public function store() { /* tworzy film */ }
    public function validateEmail() { /* walidacja emaila */ }
    public function sendNotification() { /* wysyła email */ }
}

// ✅ Dobre - rozdzielone odpowiedzialności
class MovieController
{
    public function __construct(
        private MovieService $movieService,
        private NotificationService $notificationService
    ) {}
    
    public function store(Request $request): JsonResponse
    {
        $movie = $this->movieService->create($request->validated());
        $this->notificationService->notifyMovieCreated($movie);
        return response()->json($movie, 201);
    }
}
```

**Kiedy stosować:** Gdy klasa zaczyna robić więcej niż jedną rzecz i jest trudna do testowania.

#### O - Open/Closed Principle (OCP)
**Otwórz na rozszerzenia, zamknij na modyfikacje**

```php
// ✅ Dobre - łatwo dodać nowy typ generacji
interface DescriptionGenerator
{
    public function generate(Movie $movie): string;
}

class OpenAIGenerator implements DescriptionGenerator { /* ... */ }
class AnthropicGenerator implements DescriptionGenerator { /* ... */ }

class MovieService
{
    public function __construct(private DescriptionGenerator $generator) {}
}
```

**Kiedy stosować:** Gdy wiesz, że funkcjonalność będzie rozszerzana (np. różne generatory AI).

#### L - Liskov Substitution Principle (LSP)
**Podklasy muszą być zastępowalne przez klasę bazową**

```php
// ✅ Dobre - każda implementacja może zastąpić interfejs
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value): void;
}

class RedisCache implements CacheInterface { /* ... */ }
class FileCache implements CacheInterface { /* ... */ }
```

**Kiedy stosować:** Zawsze przy dziedziczeniu i implementacji interfejsów.

#### I - Interface Segregation Principle (ISP)
**Interfejsy powinny być specyficzne, nie ogólne**

```php
// ❌ Złe - interfejs wymusza metody, których nie potrzebujemy
interface Worker
{
    public function work(): void;
    public function eat(): void;
    public function sleep(): void;
}

// ✅ Dobre - podzielone interfejsy
interface Workable { public function work(): void; }
interface Eatable { public function eat(): void; }
interface Sleepable { public function sleep(): void; }
```

**Kiedy stosować:** Gdy klasa implementuje interfejs, ale nie używa wszystkich metod.

#### D - Dependency Inversion Principle (DIP)
**Zależność od abstrakcji, nie konkretnych implementacji**

```php
// ✅ Dobre - zależność od interfejsu
class MovieService
{
    public function __construct(
        private MovieRepositoryInterface $repository,
        private LoggerInterface $logger
    ) {}
}
```

**Kiedy stosować:** Zawsze przy zależnościach - ułatwia testowanie i zmiany implementacji.

---

### 🔄 DRY (Don't Repeat Yourself)

**Unikaj duplikacji kodu, ale nie przesadzaj z abstrakcją.**

#### ✅ Kiedy refaktoryzować duplikację:
- Gdy ten sam kod występuje w 3+ miejscach
- Gdy logika jest skomplikowana i duplikacja = ryzyko błędów
- Gdy zmiana wymaga aktualizacji wielu miejsc

#### ❌ Kiedy NIE refaktoryzować:
- Gdy kod jest podobny, ale ma różne cele (różne przyczyny zmiany)
- Gdy abstrakcja byłaby bardziej skomplikowana niż duplikacja
- Gdy duplikacja jest czytelniejsza

```php
// ❌ Przesadna abstrakcja (złoty młotek)
abstract class AbstractCRUDService
{
    abstract protected function getModelClass(): string;
    abstract protected function getValidatorClass(): string;
    // ... 50 linii abstrakcji
}

// ✅ Praktyczne podejście - refaktoryzuj, gdy ma to sens
class MovieService
{
    public function create(array $data): Movie
    {
        // Wspólna logika tworzenia
    }
}

class ActorService
{
    public function create(array $data): Actor
    {
        // Może być inna logika dla aktorów
    }
}
```

---

### 🎯 GRASP (General Responsibility Assignment Software Patterns)

Wzorce przydzielania odpowiedzialności - stosuj intuicyjnie.

#### Creator
**Klasa powinna tworzyć obiekty, które zna i używa**

```php
// ✅ MovieService tworzy MovieDescription
class MovieService
{
    public function createDescription(Movie $movie): MovieDescription
    {
        return MovieDescription::create([
            'movie_id' => $movie->id,
            'content' => $this->generator->generate($movie),
        ]);
    }
}
```

#### Information Expert
**Odpowiedzialność przydziel klasie, która ma najwięcej informacji potrzebnych do wykonania zadania**

```php
// ✅ Movie zna swoje relacje, więc może sprawdzić czy ma opis
class Movie extends Model
{
    public function hasDescription(): bool
    {
        return $this->description !== null;
    }
}
```

#### Low Coupling / High Cohesion
- **Low Coupling** - minimalizuj zależności między klasami
- **High Cohesion** - elementy klasy są ze sobą powiązane

---

### 💎 CUPID - Właściwości dobrego kodu

**C**omposable - łatwy do składania
- Kod może być używany w różnych kontekstach
- Małe, złożone funkcje/klasy

**U**nix philosophy - robi jedną rzecz dobrze
- Jeden problem, jedno rozwiązanie
- Prosty interfejs

**P**redictable - przewidywalny
- Zachowanie jest jasne z nazwy
- Brak ukrytych skutków ubocznych

**I**diomatic - zgodny z konwencjami
- Zgodny z Laravel conventions
- Zgodny z PSR standards

**D**omain-based - oparty na domenie
- Nazwy odzwierciedlają język biznesowy
- Struktura odpowiada modelowi domenowemu

```php
// ✅ CUPID w praktyce
class MovieDescriptionGenerator
{
    public function generateFor(Movie $movie, Language $language): string
    {
        // Composable - można użyć w różnych kontekstach
        // Unix - robi jedną rzecz: generuje opis
        // Predictable - nazwa mówi co robi
        // Idiomatic - zgodny z Laravel
        // Domain-based - Movie, Language to terminy z domeny
    }
}
```

---

### 👃 Code Smells - Zapachy kodu

Rozpoznawaj i naprawiaj code smells, ale nie przesadzaj.

#### 🚨 Najczęstsze code smells w Laravel:

##### 1. God Class / God Method
**Klasa/metoda robi za dużo**

```php
// ❌ God Method - 200 linii kodu
public function processMovie(Request $request)
{
    // walidacja
    // zapis do bazy
    // generowanie opisu
    // wysyłanie emaila
    // logowanie
    // cache invalidation
    // ... itd
}

// ✅ Podzielone na mniejsze metody
public function processMovie(Request $request)
{
    $movie = $this->createMovie($request);
    $this->generateDescription($movie);
    $this->notifyUser($movie);
}
```

##### 2. Long Parameter List
**Za dużo parametrów**

```php
// ❌ 7 parametrów
public function createMovie($title, $year, $director, $genre, $rating, $description, $poster)

// ✅ Użyj DTO/Request object
public function createMovie(CreateMovieRequest $request)
```

##### 3. Feature Envy
**Metoda używa więcej danych z innej klasy niż własnej**

```php
// ❌ Feature Envy - używa wielu metod z Movie
public function formatMovieInfo(Movie $movie): string
{
    return $movie->getTitle() . ' (' . $movie->getYear() . ') - ' . $movie->getDirector();
}

// ✅ Przenieś logikę do Movie
class Movie
{
    public function formatInfo(): string
    {
        return "{$this->title} ({$this->year}) - {$this->director}";
    }
}
```

##### 4. Data Clumps
**Grupy danych zawsze występują razem**

```php
// ❌ Data Clump - zawsze razem
function calculatePrice($amount, $currency, $taxRate)
function formatPrice($amount, $currency, $taxRate)

// ✅ Użyj Value Object
class Money
{
    public function __construct(
        private float $amount,
        private string $currency,
        private float $taxRate
    ) {}
}
```

##### 5. Primitive Obsession
**Używanie prostych typów zamiast Value Objects**

```php
// ❌ Primitive Obsession
public function createMovie(string $title, int $year, string $email)

// ✅ Value Objects
public function createMovie(Title $title, Year $year, Email $email)
```

**Kiedy refaktoryzować code smells:**
- Gdy utrudniają czytanie kodu
- Gdy utrudniają testowanie
- Gdy utrudniają wprowadzanie zmian
- **NIE** refaktoryzuj "dla zasady" - tylko gdy ma to praktyczny sens

---

### ✅ Praktyczne zasady jakości kodu

#### Czytelność i zrozumienie:
- **Czytelność > Zwięzłość** - kod ma być czytelny
- **Meaningful names** - nazwy zmiennych i funkcji opisują cel
- **Self-documenting code** - kod wyjaśnia się sam, komentarze dla "dlaczego", nie "co"
- **Consistent style** - spójny styl w całym projekcie

#### Złożoność:
- **KISS (Keep It Simple, Stupid)** - prostota przed złożonością
- **YAGNI (You Aren't Gonna Need It)** - nie dodawaj funkcji "na przyszłość"
- **Avoid premature optimization** - nie optymalizuj przedwcześnie

#### Organizacja:
- **Magic numbers** - używaj stałych
- **Głębokie zagnieżdżenia** - maksymalnie 3 poziomy
- **Zbyt długie metody** - maksymalnie 20-30 linii (ale to wskazówka, nie reguła!)
- **Zbyt długie klasy** - rozważ podział, gdy klasa ma >300-500 linii

#### Komentarze:
- ✅ **Dobre komentarze:** wyjaśniają "dlaczego", nie "co"
- ❌ **Złe komentarze:** duplikują kod, wyjaśniają oczywiste rzeczy

```php
// ✅ Dobry komentarz - wyjaśnia "dlaczego"
// Używamy UTC, ponieważ API jest używane globalnie
$timestamp = now()->utc();

// ❌ Zły komentarz - duplikuje kod
// Tworzymy nowy film
$movie = Movie::create($data);
```

---

### 📐 Standardy kodowania

- **PSR-12** - standard formatowania PHP (enforced przez Pint)
- **Laravel Conventions** - konwencje Laravel dla struktur i nazewnictwa
  - Controllers: `MovieController`, `MovieStoreRequest`
  - Models: `Movie`, `MovieDescription`
  - Services: `MovieService`, `DescriptionGeneratorService`
  - Jobs: `GenerateMovieDescriptionJob`
- **Type hints** - zawsze używaj typów dla parametrów i return types
- **Strict types** - `declare(strict_types=1);` w plikach PHP
- **Return types** - zawsze określaj zwracany typ

### 📖 Przykład dobrego kodu - wszystkie praktyki w działaniu

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Repositories\MovieRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Service odpowiedzialny za operacje na filmach.
 * 
 * Zastosowane zasady:
 * - SRP: Tylko operacje na filmach
 * - DIP: Zależność od interfejsu (MovieRepositoryInterface)
 * - CUPID: Composable, Predictable, Idiomatic, Domain-based
 */
class MovieService
{
    public function __construct(
        private MovieRepositoryInterface $repository
    ) {}
    
    /**
     * Znajduje filmy z danego roku.
     * 
     * @param int $year Rok produkcji
     * @return Collection<Movie>
     */
    public function findMoviesByYear(int $year): Collection
    {
        // Information Expert - repository wie jak szukać
        return $this->repository->findByYear($year);
    }
    
    /**
     * Tworzy nowy film.
     * 
     * Creator - ta klasa tworzy Movie, bo go używa
     */
    public function createMovie(array $data): Movie
    {
        // Walidacja - może być w Request, ale pokazujemy tu
        $this->validateMovieData($data);
        
        // Tworzenie - przez repository dla testowalności
        return $this->repository->create([
            'title' => $data['title'],
            'release_year' => $data['release_year'],
            'director' => $data['director'] ?? null,
        ]);
    }
    
    /**
     * Walidacja danych filmu.
     * 
     * Wysoka kohezja - logika walidacji jest powiązana z tworzeniem filmu
     */
    private function validateMovieData(array $data): void
    {
        // W praktyce użyj Request class, tu pokazujemy zasadę
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Title is required');
        }
        
        $currentYear = (int) date('Y');
        if (isset($data['release_year']) && $data['release_year'] > $currentYear) {
            throw new \InvalidArgumentException('Release year cannot be in the future');
        }
    }
}
```

**Zastosowane zasady w tym przykładzie:**
- ✅ **SOLID:** SRP (tylko filmy), DIP (interfejs repository)
- ✅ **GRASP:** Creator, Information Expert, High Cohesion
- ✅ **CUPID:** Predictable nazwy, Idiomatic Laravel, Domain-based
- ✅ **Type hints:** Wszystkie parametry i return types
- ✅ **Strict types:** `declare(strict_types=1)`
- ✅ **Czytelność:** Jasne nazwy, komentarze wyjaśniają "dlaczego"
- ✅ **Testowalność:** Zależność od interfejsu ułatwia mockowanie

---

### 🔧 Refaktoryzacja - kiedy i jak

#### Kiedy refaktoryzować:
- ✅ Gdy kod jest trudny do zrozumienia
- ✅ Gdy trudno dodać nową funkcjonalność
- ✅ Gdy testy są trudne do napisania
- ✅ Gdy zauważasz code smells
- ✅ Gdy znajdziesz duplikację podczas dodawania nowej funkcji

#### Kiedy NIE refaktoryzować:
- ❌ "Dla zasady" - bez konkretnego powodu
- ❌ Gdy nie masz testów (najpierw napisz testy!)
- ❌ Podczas naprawiania krytycznego błędu (napraw, potem refaktoryzuj)
- ❌ Gdy refaktoryzacja wprowadza ryzyko bez korzyści

#### Zasady refaktoryzacji:
1. **Najpierw testy** - upewnij się, że masz testy pokrywające kod
2. **Małe kroki** - refaktoryzuj stopniowo, commituj często
3. **Nie zmieniaj zachowania** - refaktoryzacja = zmiana struktury, nie funkcjonalności
4. **Po refaktoryzacji uruchom narzędzia** - Pint, PHPStan, testy

```php
// Przed refaktoryzacją - God Method
public function processMovie($data)
{
    // 50 linii kodu robiących wszystko
}

// Po refaktoryzacji - podzielone odpowiedzialności
public function processMovie(CreateMovieRequest $request): Movie
{
    $movie = $this->createMovie($request);
    $this->generateDescription($movie);
    $this->notifyUser($movie);
    return $movie;
}

// Każda metoda ma jedną odpowiedzialność i jest testowalna
```

---

## 🚫 Co NIE robić

### AI Agent NIE powinien:

1. **Commituje bez testów**
   - Każda nowa funkcjonalność wymaga testów
   - Refaktoring wymaga istniejących testów
   - Stosuj TDD - test przed kodem

2. **Ignoruje failujące testy**
   - Jeśli test failuje, napraw kod lub test
   - Nie wyłączaj testów bez powodu
   - Nie commituj kodu z failującymi testami

3. **Pomija narzędzia jakości kodu**
   - Pint, PHPStan, testy muszą przejść przed commitem
   - Nie używaj `--no-verify` bez uzasadnienia
   - Napraw wszystkie wykryte problemy

4. **Commituje sekretów**
   - Zawsze sprawdź GitLeaks
   - Używaj zmiennych środowiskowych dla kluczy API
   - Nie hardcoduj żadnych wrażliwych danych

5. **Commituje debugowego kodu**
   - Usuń `dd()`, `dump()`, `var_dump()`, `print_r()`
   - Usuń zakomentowany kod (chyba że wyjaśnia ważne "dlaczego")
   - Usuń console.log(), var_dump() itp.

6. **Tworzy zbyt duże commity**
   - Jeden commit = jedna logiczna zmiana
   - Rozbij duże zmiany na mniejsze commity
   - Commituj często, push regularnie

7. **Stosuje zasady na siłę**
   - Nie tworz nadmiernych abstrakcji "dla zasady"
   - Nie refaktoryzuj kodu, który działa dobrze
   - Pamiętaj: czytelność > "idealna" architektura

8. **Ignoruje code smells**
   - Rozpoznawaj i naprawiaj code smells, gdy utrudniają pracę
   - Ale nie refaktoryzuj wszystkiego "dla zasady"
   - Priorytetyzuj - najpierw to, co utrudnia pracę

9. **Tworzy kod bez myślenia o czytelności**
   - Kod ma być czytelny dla innych
   - Używaj znaczących nazw
   - Pisz kod tak, jakbyś go czytał za rok

10. **Pomija type hints i strict types**
    - Zawsze używaj type hints
    - Zawsze dodawaj `declare(strict_types=1);`
    - Type safety = mniej błędów

---

## 🔍 Checklist przed Commitem

Przed każdym commitem AI Agent powinien sprawdzić:

### Narzędzia jakości kodu:
- [ ] ✅ Kod jest sformatowany przez Pint
- [ ] ✅ PHPStan nie wykrywa błędów (lub są uzasadnione ignore)
- [ ] ✅ Wszystkie testy przechodzą (`php artisan test`)
- [ ] ✅ GitLeaks nie wykrywa sekretów
- [ ] ✅ Composer audit nie wykrywa krytycznych luk

### Testy:
- [ ] ✅ Nowy kod ma testy (jeśli to nowa funkcjonalność)
- [ ] ✅ Testy są czytelne i testują właściwe zachowania
- [ ] ✅ Stosowano TDD (test przed kodem)

### Jakość kodu:
- [ ] ✅ Kod jest czytelny i zgodny z konwencjami
- [ ] ✅ Zastosowano odpowiednie zasady SOLID (gdy mają sens)
- [ ] ✅ Usunięto duplikację kodu (gdy było to potrzebne)
- [ ] ✅ Rozpoznano i naprawiono code smells (gdy utrudniały pracę)
- [ ] ✅ Użyto type hints i `declare(strict_types=1)`

### Czytelność:
- [ ] ✅ Nazwy zmiennych i funkcji są znaczące
- [ ] ✅ Kod jest samowyjaśniający się
- [ ] ✅ Komentarze wyjaśniają "dlaczego", nie "co"

### Cleanup:
- [ ] ✅ Nie ma debugowego kodu (`dd()`, `dump()`, `var_dump()`)
- [ ] ✅ Nie ma nieużywanego kodu
- [ ] ✅ Nie ma zakomentowanego kodu (chyba że wyjaśnia ważne "dlaczego")

### Git:
- [ ] ✅ Commit message jest opisowy i zgodny z konwencją
- [ ] ✅ Commit zawiera jedną logiczną zmianę

---

## 📚 Dodatkowe Zasoby

### Konfiguracja Cursor IDE:
- **`.cursor/rules/*.mdc`** - nowy format reguł (8 modułów, automatycznie wczytywany przez Cursor):
  - `priorities.mdc` - Priorytety
  - `testing.mdc` - Test Driven Development
  - `workflow.mdc` - Workflow przed commitem
  - `coding-standards.mdc` - Zasady kodowania
  - `dont-do.mdc` - Co NIE robić
  - `task-management.mdc` - System zarządzania zadaniami
  - `checklist.mdc` - Checklist przed commitem
  - `philosophy.mdc` - Filozofia i kluczowe zasady
- **`CLAUDE.md`** - plik z kontekstem projektu (architektura, struktura, technologie) - wczytywany gdy opcja "Include CLAUDE.md in context" jest włączona
- **`docs/CURSOR_RULES_EXPLANATION.md`** - wyjaśnienie różnic między formatami
- ⚠️ **`.cursorrules`** - przestarzały format (zawiera tylko informację o migracji)
- Ten dokument (`AI_AGENT_CONTEXT_RULES.md`) zawiera szczegółowe wyjaśnienia i przykłady

### Dokumentacja projektu:
- **📋 Backlog Zadań:** [`docs/issue/TASKS.md`](issue/TASKS.md) - ⭐ **ZACZYNAJ OD TEGO** - główny plik z zadaniami
- **📋 System Zadań:** [`docs/issue/README.md`](issue/README.md) - instrukcje użycia systemu zadań
- **Testy:** [`docs/TESTING_STRATEGY.md`](TESTING_STRATEGY.md)
- **Narzędzia jakości:** [`docs/CODE_QUALITY_TOOLS.md`](CODE_QUALITY_TOOLS.md)
- **Pre-commit hooks:** [`docs/pre-commit-setup.md`](pre-commit-setup.md)
- **Architektura:** [`docs/ARCHITECTURE_ANALYSIS.md`](ARCHITECTURE_ANALYSIS.md)

### Komendy pomocnicze:

```bash
# Pełny check przed commitem (wszystko na raz)
cd api && \
  vendor/bin/pint && \
  vendor/bin/phpstan analyse --memory-limit=2G && \
  php artisan test && \
  gitleaks protect --source . --verbose --no-banner && \
  composer audit

# Formatowanie i testy (minimalny check)
cd api && vendor/bin/pint && php artisan test
```

---

## 🎯 Priorytety

W przypadku konfliktów, priorytety są następujące:

1. **Bezpieczeństwo** - sekrety, luki bezpieczeństwa (najwyższy priorytet)
2. **Testy** - wszystkie testy muszą przechodzić, TDD
3. **Jakość kodu** - PHPStan, Pint (wymagane przed commitem)
4. **Czytelność i zrozumienie** - kod musi być zrozumiały (wymagane)
5. **Dobre praktyki** - SOLID, DRY, GRASP, CUPID (stosuj pragmatycznie)
6. **Code smells** - naprawiaj, gdy utrudniają pracę (nie na siłę)

**Pamiętaj:** Czytelność i praktyczność są ważniejsze niż "idealna" architektura zgodna ze wszystkimi zasadami.

---

## 📋 System Zarządzania Zadaniami

### ⭐ **WAŻNE: Zawsze zaczynaj od `docs/issue/TASKS.md`**

Przed rozpoczęciem pracy AI Agent powinien:

1. **Przeczytać `docs/issue/TASKS.md`** - znajdź zadanie ze statusem `⏳ PENDING`
2. **Zmień status na `🔄 IN_PROGRESS`** - zaznacz że zaczynasz pracę
3. **Przeczytaj szczegóły zadania** - jeśli jest link do szczegółowego opisu, przeczytaj ten plik
4. **Wykonaj zadanie** - implementuj zgodnie z opisem
5. **Po zakończeniu:**
   - Zmień status na `✅ COMPLETED`
   - Przenieś zadanie do sekcji "Zakończone Zadania"
   - Zaktualizuj datę "Ostatnia aktualizacja"
   - Dodaj notatkę o zakończeniu (opcjonalnie)

### Struktura systemu zadań:

- **`docs/issue/TASKS.md`** - główny backlog zadań (zaczynaj zawsze od tego)
- **`docs/issue/README.md`** - instrukcje użycia systemu
- **`docs/issue/TASK_TEMPLATE.md`** - szablon dla nowych zadań
- **`docs/issue/*.md`** - szczegółowe opisy zadań (jeśli dostępne)

### Priorytety zadań:

- 🔴 **Wysoki** - krytyczne, wykonaj jak najszybciej
- 🟡 **Średni** - ważne, ale nie krytyczne
- 🟢 **Niski** - można wykonać później (często roadmap items)

**Więcej informacji:** [`docs/issue/README.md`](issue/README.md)

---

## 📝 Uwagi końcowe

Te zasady mają na celu zapewnienie wysokiej jakości kodu i łatwości utrzymania projektu. AI Agent powinien stosować się do nich konsekwentnie, co pozwoli na:

- ✅ Szybsze code review
- ✅ Mniej bugów w produkcji
- ✅ Łatwiejsze utrzymanie kodu
- ✅ Lepsze doświadczenie dla współpracowników
- ✅ Wyższą jakość całego projektu

### Kluczowe zasady w pigułce:

1. **TDD** - Test przed kodem, zawsze
2. **Narzędzia** - Pint, PHPStan, testy przed commitem
3. **SOLID** - Stosuj pragmatycznie, nie na siłę
4. **DRY** - Usuwaj duplikację, ale nie przesadzaj z abstrakcją
5. **Code Smells** - Rozpoznawaj i naprawiaj, gdy utrudniają pracę
6. **Czytelność** - Kod ma być zrozumiały dla innych
7. **Refaktoryzacja** - Gdy kod jest trudny do utrzymania
8. **Bezpieczeństwo** - Zawsze sprawdzaj sekrety przed commitem

### Filozofia:

**Zasady są narzędziami, nie celem samym w sobie.** 

- Kod ma być **czytelny** i **zrozumiały**
- Stosuj zasady **pragmatycznie**, nie fanatycznie
- **Prostota** jest lepsza niż nadmierna abstrakcja
- **Czytelność** jest ważniejsza niż "idealna" architektura

**Pamiętaj:** Lepszy kod to mniej problemów w przyszłości. Czas poświęcony na jakość kodu zawsze się zwraca, ale nie przesadzaj - czasem prosty kod jest najlepszy.
