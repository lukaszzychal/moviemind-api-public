# MovieMind API – Podsumowanie Nauki

> **Dla:** Przygotowanie do rozmowy kwalifikacyjnej, samoocena, odniesienie edukacyjne  
> **Ostatnia aktualizacja:** 2026-04-19  
> **Status:** Projekt portfolio / Demo

---

## 1. Podsumowanie Wykonawcze

Ten dokument podsumowuje to, czego nauczyłem się podczas budowania MovieMind API — usługi RESTful API dla generowanych przez AI opisów filmów, seriali i aktorów. Obejmuje architekturę, wzorce, uzasadnienie implementacji oraz punkty do dyskusji gotowe na rozmowę kwalifikacyjną.

**Główny wniosek:** Oryginalna treść generowana przez AI (nie kopiowana z IMDb/TMDb), zbudowana z użyciem Laravel, architektury Event-Driven i praktyk gotowych na środowisko produkcyjne.

---

## 2. Architektura i Wzorce Projektowe

### Thin Controllers (Cienkie Kontrolery)

**Co:** Kontrolery ograniczone do ~20–30 linii na metodę. Służą jedynie do walidacji wejścia, delegowania zadań do Akcji/Serwisów i formatowania odpowiedzi.

**Jak:** Wstrzykiwanie przez konstruktor (`Constructor injection`) klas: `MovieRepository`, `MovieRetrievalService`, `MovieResponseFormatter`. Każda metoda deleguje zadania do jednej Akcji lub Serwisu.

**Dlaczego:** Zasada Pojedynczej Odpowiedzialności (Single Responsibility Principle). Kontrolery zajmują się tylko warstwą HTTP. Logika biznesowa pozostaje w Serwisach/Akcjach — możliwa do ponownego wykorzystania z API, CLI lub Jobów. Łatwiejsze testowanie (mockowanie zależności) i możliwość łatwej zmiany warstwy transportowej (np. REST → GraphQL).

**Przykład:**

```php
public function show(Request $request, string $slug): JsonResponse
{
    $descriptionId = $this->normalizeDescriptionId($request->query('description_id'));
    if ($descriptionId === false) {
        return $this->responseFormatter->formatError('Invalid description_id', 422);
    }
    $result = $this->movieRetrievalService->retrieveMovie($slug, $descriptionId);
    return $this->responseFormatter->formatFromResult($result, $slug, $descriptionId);
}
```

---

### Service Layer (Warstwa Serwisów)

**Co:** Serwisy hermetyzują logikę biznesową i koordynują repozytoria, zewnętrzne API oraz cache.

**Jak:** `MovieRetrievalService` wykorzystuje `MovieRepository` i `EntityVerificationServiceInterface` (TMDB/TVmaze). Obsługuje pobieranie, proces disambiguation (rozstrzyganie niejednoznaczności) oraz tworzenie "w locie" na podstawie danych z zewnętrznych API.

**Dlaczego:** Scentralizowana logika biznesowa, możliwa do ponownego użycia w API i Jobach. Jasne granice i testowalność dzięki mockom.

**Przykłady:** `MovieRetrievalService`, `PlanService`, `UsageTracker`, `TmdbVerificationService`.

---

### Repository Pattern (Wzorzec Repozytorium)

**Co:** Abstrakcja nad warstwą dostępu do danych. Repozytoria hermetyzują zapytania.

**Jak:** `MovieRepository::findBySlug()`, `MovieRepository::searchMovies()`, itp. Zwracają modele lub kolekcje.

**Dlaczego:** Łatwiejsze testowanie (mockowanie repozytoriów), łatwiejsza zmiana magazynu danych (PostgreSQL → MongoDB) i utrzymywanie logiki zapytań w jednym miejscu.

**Przykład:**

```php
public function findBySlug(string $slug): ?Movie
{
    return Movie::where('slug', $slug)->first();
}
```

---

### Action Pattern (Wzorzec Akcji)

**Co:** Pojedyncze, spójne operacje biznesowe. Jedna akcja = jeden workflow.

**Jak:** `QueueMovieGenerationAction::handle()` koordynuje walidację, `JobStatusService` oraz wysyłanie eventów. Zwraca tablicę z ID zadania i statusem.

**Dlaczego:** Pojedyncza odpowiedzialność, jasne wejście/wyjście, łatwość testowania i komponowania.

**Przykłady:** `QueueMovieGenerationAction`, `QueuePersonGenerationAction`, `VerifyMovieReportAction`.

---

### Response Formatter (Formatter Odpowiedzi)

**Co:** Dedykowane klasy, które formatują odpowiedzi API w spójny format JSON.

**Jak:** `MovieResponseFormatter::formatSuccess()`, `formatError()`, `formatNotFound()` itd. Obsługują linki HATEOAS i strukturę odpowiedzi.

**Dlaczego:** Jednolite odpowiedzi API. Kontrolery pozostają "cienkie"; logika formatowania jest scentralizowana.

---

### Event-Driven Architecture (Architektura sterowana zdarzeniami)

**Co:** Przepływ: Event → Listener → Job. Eventy oddzielają producentów od konsumentów.

**Jak:** Event `MovieGenerationRequested`; listenery `QueueMovieGenerationJob` i `SendOutgoingWebhookListener`; joby `RealGenerateMovieJob` lub `MockGenerateMovieJob`.

**Dlaczego:** Luźne powiązania (loose coupling), rozszerzalność (dodawanie listenerów bez zmiany producentów), skalowalność (wielu workerów).

**Przepływ (Flow):**

```
Controller → Action → Event::dispatch()
    → Listener (QueueMovieGenerationJob) → Job::dispatch()
    → Horizon Worker → OpenAI/TMDB → Database
```

---

## 3. Przetwarzanie Asynchroniczne i Horizon

**Co:** Długotrwałe generowanie AI działa w zadaniach w tle za pośrednictwem Laravel Horizon (kolejki Redis).

**Flow:** `Controller` → `QueueMovieGenerationAction` → Event `MovieGenerationRequested` → Listener `QueueMovieGenerationJob` → `RealGenerateMovieJob` (lub Mock) → Horizon Worker → OpenAI API → DB.

**Dlaczego:** Unikanie timeoutów HTTP, lepsze UX (polling asynchroniczny), horyzontalne skalowanie workerów.

**ADR-007 (Blokady dwupoziomowe):**

1.  **Poziom 1 – In-flight token (`Cache::add`):** `JobStatusService::acquireGenerationSlot()` zapobiega wysyłaniu wielu zadań dla tego samego sluga. Oszczędza zasoby (brak duplikowania wywołań OpenAI).

2.  **Poziom 2 – Unikalny indeks + wyjątek:** Unikalny indeks `movies.slug` + przechwytywanie `QueryException`, gdy dwóch workerów próbuje utworzyć ten sam rekord. Zapewnia deterministyczne zachowanie nawet w warunkach race conditions.

**Dlaczego oba?** Poziom 1 unika niepotrzebnych jobów; Poziom 2 obsługuje przypadki brzegowe, gdy slot cache wygaśnie.

---

## 4. Subskrypcje i Rate Limiting

**Plany:**

- **Free:** 100 żądań/miesiąc, dostęp tylko do odczytu.
- **Pro:** 10,000/miesiąc, generowanie AI, tagi kontekstowe.
- **Enterprise:** Nielimitowane, webhooks, analityka, priorytetowe wsparcie.

**Komponenty:**

- **Middleware `PlanBasedRateLimit`** – sprawdza limity miesięczne i minutowe.
- **`UsageTracker`** – `hasExceededMonthlyLimit()`, `hasExceededRateLimit()`, `getRemainingQuota()`.
- **`PlanService`** – `canUseFeature()`, `getMonthlyLimit()`, `getRateLimit()`.
- **Model `SubscriptionPlan`** – `hasFeature()`, `isUnlimited()`.

**Flow:** `ApiKeyAuth` → `PlanBasedRateLimit` → `UsageTracker` checks → increment usage.

---

## 5. Integracje zewnętrzne

| Serwis | Cel | Licencjonowanie |
| :--- | :--- | :--- |
| **OpenAI** | Opisy generowane przez AI (gpt-4o-mini) | API key, biling oparty na zużyciu |
| **TMDB** | Weryfikacja filmów/osób, metadane | Wymagana licencja komercyjna na prod |
| **TVmaze** | Weryfikacja seriali/programów TV | CC BY-SA, komercyjne użycie dozwolone |

**AiServiceSelector:** Wybiera `mock` vs `real` AI na podstawie zmiennej środowiskowej `AI_SERVICE`. Publiczne repozytorium używa mocka; prywatne repozytorium może używać prawdziwego OpenAI API.

---

## 6. Feature Flags (Flagi funkcjonalności)

**Stack:** Laravel Pennant + własny `BaseFeature` + `config/features.php`.

**Kolejność rozstrzygania:** Environment Force (`_FORCE`) > Database Toggle (Filament) > Environment Default (`_DEFAULT`) > Code default.

**Przypadki użycia:**

- Specjalizacja instancji (węzły API vs Worker).
- Stopniowe wdrażanie (gradual rollouts).
- Testy A/B.

**Filament Admin:** Zarządzanie flagami pod adresem `/admin/features`. Ikona kłódki, gdy flaga jest kontrolowana przez `_FORCE`.

**Flagi deweloperskie:** Tymczasowe (kategoria `experiments`). Muszą zostać usunięte po wdrożeniu funkcji.

---

## 7. Strategia Testowania

**Piramida:**

- **Unit (~60%):** Serwisy, Akcje, Helpery. Szybkie, izolowane.
- **Feature (~35%):** Endpointy API, integracje. SQLite w pamięci (in-memory).
- **E2E (~5%):** Playwright dla krytycznych flow (np. panel admina).

**TDD:** Red → Green → Refactor. Najpierw testy, potem implementacja.

**Mockowanie:** Zewnętrzne API (OpenAI, TMDB, TVmaze) są mockowane w testach. Wewnętrzne serwisy zazwyczaj nie są mockowane.

---

## 8. Bezpieczeństwo

- **Klucze API:** Hashowane, przechowywane bezpiecznie. Walidowane przez middleware `ApiKeyAuth`.
- **Rate limiting:** Limity oparte na planach; wspierane przez Redis.
- **Walidacja wejścia:** Form Requests, rygorystyczne reguły.
- **Sekrety:** GitLeaks w pre-commit; Composer Audit.
- **Bezpieczeństwo AI:** Sanityzacja promptów, rygorystyczne formaty wyjściowe.

---

## 9. Kluczowe Decyzje Architektoniczne (ADRs)

| ADR | Decyzja | Uzasadnienie |
| :--- | :--- | :--- |
| 001 | Laravel zamiast Symfony | Szybszy MVP, Horizon, Eloquent, lepszy DX |
| 003 | Dual-repository (public portfolio / private) | Bezpieczeństwo, prezentacja portfolio, elastyczne licencje |
| 004 | generation-first vs translate-then-adapt | Unikalna treść, adaptacja kulturowa |
| 006 | Pennant dla feature flags | Rozwiązanie natywne Laravel, proste, wsparcie DB + env |
| 007 | Blokady dwupoziomowe | Unikanie duplikowania zadań + obsługa race conditions |
| 008 | Strategia UUID v7/v4/v5 | Sortowalne ID, kompatybilność |

---

## 10. DevOps i Narzędzia

- **Docker:** Obowiązkowy dla lokalnego dev (PostgreSQL, Redis, Nginx, PHP-FPM).
- **Pint:** Formatowanie PSR-12.
- **PHPStan:** Analiza statyczna (poziom 5).
- **GitLeaks:** Wykrywanie sekretów.
- **Pre-commit:** Pint, PHPStan, testy, GitLeaks.

---

## 11. Rozwój wspomagany przez AI

### IDE i Narzędzia

- **Cursor** – Główne IDE z integracją Claude/GPT.
- **Antigravity** – Alternatywne IDE wspomagane przez AI.
- **Modele LLM:** Claude (Sonnet, Opus), Gemini.

### Serwery MCP (Model Context Protocol)

MCP rozszerza asystentów AI o narzędzia i zasoby. Nauczyłem się tworzyć własne serwery MCP do generowania dokumentacji ([mcp-doc-generator](https://github.com/lukaszzychal/mcp-doc-generator)).

| Serwer | Przypadek użycia |
| :--- | :--- |
| **GitHub MCP** | Taski, PR-y, commity, wyszukiwanie, review |
| **Firecrawl MCP** | Scrapping stron, wyszukiwanie, crawl |
| **Filesystem MCP** | Odczyt/zapis plików projektu |
| **Playwright MCP** | Automatyzacja przeglądarki, testy E2E |
| **Postman MCP** | Testowanie API, kolekcje |
| **Sequential Thinking MCP** | Rozumowanie wieloetapowe |
| **Memory Bank MCP** | Długofalowa pamięć o projekcie |

### Skills (Cursor)

Niestandardowe "Skills" automatyzują powtarzalne zadania. Projekt zawiera prosty przykład: `php-pre-commit` (`.cursor/skills/php-pre-commit/`) – przypomnienie o uruchomieniu Pint, PHPStan, testów i GitLeaks przed commitem.

### Praktyki

- Prompt engineering dla generowania kodu.
- Wspomagane przez AI review i refaktoryzacja.
- Dokumentacja generowana przez AI z ludzką weryfikacją.
- TDD z AI (generowanie testów, potem implementacja).

---

## 12. "Talking Points" na rozmowę rekrutacyjną

### "Opowiedz mi o projekcie"

> MovieMind API to usługa REST API, która generuje unikalne, oparte na AI opisy filmów, seriali i aktorów. W przeciwieństwie do IMDb czy TMDb, tworzy ono oryginalną treść przy użyciu OpenAI zamiast kopiowania metadanych. Wykorzystuje Laravel, architekturę sterowaną zdarzeniami (Event-Driven), Horizon do zadań asynchronicznych oraz subskrypcje oparte na planach z rate limitingiem. Zbudowany jako projekt portfolio z zachowaniem TDD, Dockera i praktyk gotowych na produkcję.

### "Jak rozwiązałeś problem duplikatów opisów przy równoległych zadaniach?"

> Stosujemy strategię dwupoziomową (ADR-007). Poziom 1: `Cache::add` jako token "in-flight" poprzez `JobStatusService::acquireGenerationSlot()`, dzięki czemu nie wysyłamy wielu zadań dla tego samego sluga. Poziom 2: unikalny indeks na `movies.slug` plus obsługa `QueryException`, gdy dwóch workerów spróbuje utworzyć ten sam rekord. Poziom 1 redukuje niepotrzebne zadania; Poziom 2 obsługuje wyścigi (races), gdy slot cache wygaśnie.

### "Dlaczego Cienkie Kontrolery (Thin Controllers)?"

> Dla zachowania Pojedynczej Odpowiedzialności. Kontrolery zajmują się tylko warstwą HTTP — walidacją, delegowaniem i formatowaniem odpowiedzi. Logika biznesowa żyje w Serwisach i Akcjach, więc można jej używać ponownie z API, CLI i Jobów. To sprawia, że kod jest łatwiejszy do testowania (mockowanie zależności) i pozwala na zmianę warstwy transportowej (np. REST na GraphQL) bez dotykania logiki rdzenia.

### "Jak działa Rate Limiting?"

> Opiera się na planach. Middleware `PlanBasedRateLimit` używa `UsageTracker` do sprawdzania miesięcznych i minutowych limitów dla każdego klucza API. Plany (Free, Pro, Enterprise) definiują te limity. Zużycie jest przechowywane w DB; sprawdzenia minutowe wykorzystują Redis. Jeśli limity zostaną przekroczone, zwracamy kod 429.

### "Co to jest Event-Driven i po co go używać?"

> Eventy oddzielają producentów od konsumentów. Na przykład, wysyłany jest event `MovieGenerationRequested`; reagują na niego listenery takie jak `QueueMovieGenerationJob` i `SendOutgoingWebhookListener`. Możemy dodawać kolejne listenery bez zmiany kodu producenta. Joby działają w workerach Horizon, więc skalujemy aplikację poprzez dodawanie kolejnych workerów.

### "Jak wykorzystałeś AI w procesie deweloperskim?"

> Używałem Cursora z modelami Claude/Gemini do kodowania, refaktoryzacji i dokumentacji. Serwery MCP (GitHub, Firecrawl, Playwright, Postman, mcp-doc-generator) rozszerzają możliwości asystenta o konkretne narzędzia. Dodałem przykładowy Skill `php-pre-commit` jako przypomnienie o jakości kodu. AI pomaga mi w TDD (generowanie testów) i code review, zawsze pod nadzorem człowieka.

### "Co to jest serwer MCP?"

> Model Context Protocol (MCP) pozwala asystentom AI wywoływać zewnętrzne narzędzia. Serwer MCP wystawia narzędzia (np. GitHub API, automatyzacja przeglądarki) i zasoby (np. pliki). Asystent może przeszukiwać kod, tworzyć PR-y, uruchamiać testy E2E czy edytować pliki poprzez te narzędzia, a nie tylko generować tekst.

---

## 13. Szybka referencja

### Kluczowe pliki

| Warstwa | Lokalizacja | Przykłady |
| :--- | :--- | :--- |
| Kontrolery | `api/app/Http/Controllers/` | MovieController, GenerateController |
| Serwisy | `api/app/Services/` | MovieRetrievalService, UsageTracker |
| Akcje | `api/app/Actions/` | QueueMovieGenerationAction |
| Repozytoria | `api/app/Repositories/` | MovieRepository, PersonRepository |
| Eventy | `api/app/Events/` | MovieGenerationRequested |
| Listeners | `api/app/Listeners/` | QueueMovieGenerationJob |
| Joby | `api/app/Jobs/` | RealGenerateMovieJob, MockGenerateMovieJob |

### Dokumentacja

- [Architektura](../technical/ARCHITECTURE.md)
- [ADR-y](../adr/README.md)
- [Strategia Testów](../qa/TEST_STRATEGY.md)
- [Feature Flags](../technical/FEATURE_FLAGS.md)
- [Subskrypcje i Rate Limiting](../knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md)
