# MovieMind API – Podsumowanie nauki

> **Dla:** Przygotowanie do rozmowy, Samoocena, Materiały edukacyjne  
> **Ostatnia aktualizacja:** 2026-01-25  
> **Status:** Projekt Portfolio/Demo

---

## 1. Szybki przegląd

Dokument podsumowuje, czego nauczyłem się podczas budowania MovieMind API – RESTowego API do generowania opisów filmów, seriali i aktorów za pomocą AI. Obejmuje architekturę, wzorce, uzasadnienie implementacji oraz punkty do rozmowy rekrutacyjnej.

**Kluczowy wniosek:** Oryginalna treść generowana przez AI (nie kopiowana z IMDb/TMDb), zbudowana w Laravelu, architekturze Event-Driven i praktykach gotowych do produkcji.

---

## 2. Architektura i wzorce projektowe

### Thin Controllers (cienkie kontrolery)

**Co:** Kontrolery ograniczone do ok. 20–30 linii na metodę. Walidują wejście, delegują do Actions/Services i formatują odpowiedzi.

**Jak:** Wstrzykiwanie przez konstruktor `MovieRepository`, `MovieRetrievalService`, `MovieResponseFormatter`. Każda metoda deleguje do jednego Action lub Service.

**Dlaczego:** Zasada Pojedynczej Odpowiedzialności. Kontrolery obsługują tylko HTTP. Logika biznesowa zostaje w Services/Actions – reużywalna z API, CLI lub Jobs. Łatwiejsze testowanie (mock zależności) i zamiana warstwy HTTP (np. REST → GraphQL).

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

### Warstwa serwisów (Service Layer)

**Co:** Serwisy enkapsulują logikę biznesową i koordynują repozytoria, zewnętrzne API i cache.

**Jak:** `MovieRetrievalService` używa `MovieRepository` i `EntityVerificationServiceInterface` (TMDB/TVmaze). Obsługuje pobieranie, rozróżnianie i tworzenie na żądanie z zewnętrznych API.

**Dlaczego:** Scentralizowana logika biznesowa, reużywalna w API i Jobs. Jasne granice i testowalność dzięki mockom.

**Przykłady:** `MovieRetrievalService`, `PlanService`, `UsageTracker`, `TmdbVerificationService`.

---

### Wzorzec Repository

**Co:** Abstrakcje nad dostępem do danych. Repozytoria enkapsulują zapytania.

**Jak:** `MovieRepository::findBySlug()`, `MovieRepository::searchMovies()` itd. Zwracają modele lub kolekcje.

**Dlaczego:** Łatwiejsze testowanie (mock repozytoriów), zamiana magazynu (PostgreSQL → MongoDB) i scentralizowana logika zapytań.

**Przykład:**

```php
public function findBySlug(string $slug): ?Movie
{
    return Movie::where('slug', $slug)->first();
}
```

---

### Wzorzec Action

**Co:** Pojedyncze, spójne operacje biznesowe. Jeden action = jeden przepływ pracy.

**Jak:** `QueueMovieGenerationAction::handle()` koordynuje walidację, JobStatusService, dispatchowanie eventów. Zwraca tablicę z ID joba i statusem.

**Dlaczego:** Pojedyncza odpowiedzialność, jasne wejście/wyjście, łatwe testowanie i komponowanie.

**Przykłady:** `QueueMovieGenerationAction`, `QueuePersonGenerationAction`, `VerifyMovieReportAction`.

---

### Response Formatter

**Co:** Dedykowane klasy formatujące odpowiedzi API do spójnego JSON.

**Jak:** `MovieResponseFormatter::formatSuccess()`, `formatError()`, `formatNotFound()` itd. Obsługa linków HATEOAS i struktury.

**Dlaczego:** Jednolite odpowiedzi API. Kontrolery pozostają cienkie; logika formatowania scentralizowana.

---

### Architektura Event-Driven

**Co:** Przepływ: Event → Listener → Job. Eventy odłączają producentów od konsumentów.

**Jak:** Event `MovieGenerationRequested`; listenery `QueueMovieGenerationJob` i `SendOutgoingWebhookListener`; joby `RealGenerateMovieJob` lub `MockGenerateMovieJob`.

**Dlaczego:** Luźne sprzężenie, rozszerzalność (dodawanie listenerów bez zmiany producentów), skalowalność (wiele workerów).

**Przepływ:**

```
Controller → Action → Event::dispatch()
    → Listener (QueueMovieGenerationJob) → Job::dispatch()
    → Horizon Worker → OpenAI/TMDB → Database
```

---

## 3. Przetwarzanie asynchroniczne i Horizon

**Co:** Długotrwałe generowanie AI odbywa się w jobach w tle przez Laravel Horizon (kolejki Redis).

**Przepływ:** `Controller` → `QueueMovieGenerationAction` → event `MovieGenerationRequested` → listener `QueueMovieGenerationJob` → `RealGenerateMovieJob` (lub Mock) → Horizon Worker → OpenAI API → DB.

**Dlaczego:** Unikanie timeoutów HTTP, lepszy UX (asynchroniczny polling), skalowanie poziome workerów.

**ADR-007 (dwa poziomy blokad):**

1. **Poziom 1 – Token in-flight (`Cache::add`):** `JobStatusService::acquireGenerationSlot()` zapobiega dispatchowaniu wielu jobów dla tego samego sluga. Oszczędza zasoby (brak duplikatów wywołań OpenAI).

2. **Poziom 2 – Unikalny indeks + wyjątek:** Unikalny `movies.slug` + obsługa `QueryException`, gdy dwa workery próbują utworzyć ten sam rekord. Zapewnia deterministyczne zachowanie nawet przy wyścigu.

**Dlaczego oba?** Poziom 1 ogranicza niepotrzebne joby; Poziom 2 obsługuje edge case’y, gdy slot cache wygaśnie.

---

## 4. Subskrypcje i rate limiting

**Plany:**

- **Free:** 100 zapytań/miesiąc, tylko odczyt.
- **Pro:** 10 000/miesiąc, generowanie AI, tagi kontekstowe.
- **Enterprise:** Bez limitów, webhooki, analityka, priorytetowe wsparcie.

**Komponenty:**

- Middleware **PlanBasedRateLimit** – sprawdza limity miesięczne i na minutę.
- **UsageTracker** – `hasExceededMonthlyLimit()`, `hasExceededRateLimit()`, `getRemainingQuota()`.
- **PlanService** – `canUseFeature()`, `getMonthlyLimit()`, `getRateLimit()`.
- Model **SubscriptionPlan** – `hasFeature()`, `isUnlimited()`.

**Przepływ:** `ApiKeyAuth` → `PlanBasedRateLimit` → sprawdzenia `UsageTracker` → inkrementacja użycia.

---

## 5. Integracje zewnętrzne

| Serwis   | Cel                               | Licencjonowanie                               |
|----------|-----------------------------------|-----------------------------------------------|
| **OpenAI** | Opisy generowane przez AI (gpt-4o-mini) | Klucz API, rozliczanie za użycie             |
| **TMDB**   | Weryfikacja filmów/osób, metadane | Licencja komercyjna wymagana w prod           |
| **TVmaze** | Weryfikacja seriali TV            | CC BY-SA, użycie komercyjne dozwolone         |

**AiServiceSelector:** Wybiera `mock` vs `real` AI na podstawie env `AI_SERVICE`. Publiczne repo używa mocka; prywatne może używać prawdziwego OpenAI.

---

## 6. Feature flags

**Stack:** Laravel Pennant + własny `BaseFeature` + `config/features.php`.

**Kolejność rozwiązywania:** Environment Force (`_FORCE`) > Przełącznik w DB (Filament) > Environment Default (`_DEFAULT`) > domyślna wartość z kodu.

**Zastosowania:**

- Specjalizacja instancji (nody API vs Worker).
- Stopniowe rollout’y.
- Testy A/B.

**Admin Filament:** Zarządzanie flagami w `/admin/features`. Ikona kłódki, gdy stan kontrolowany przez `_FORCE`.

**Flagi deweloperskie:** Tymczasowe (kategoria `experiments`). Po wdrożeniu funkcji muszą być usunięte.

---

## 7. Strategia testowania

**Piramida:**

- **Unit (~60%):** Serwisy, Actions, Helpers. Szybkie, izolowane.
- **Feature (~35%):** Endpointy API, integracje. SQLite in-memory.
- **E2E (~5%):** Playwright dla krytycznych przepływów (np. panel admina).

**TDD:** Red → Green → Refactor. Najpierw testy, potem implementacja.

**Mockowanie:** Zewnętrzne API (OpenAI, TMDB, TVmaze) mockowane w testach. Wewnętrzne serwisy zazwyczaj nie.

---

## 8. Bezpieczeństwo

- **Klucze API:** Zahashowane, bezpiecznie przechowywane. Walidacja przez middleware `ApiKeyAuth`.
- **Rate limiting:** Limity oparte na planach; backend Redis.
- **Walidacja wejścia:** Form Requests, ścisłe reguły.
- **Sekrety:** GitLeaks w pre-commit; Composer Audit.
- **AI:** Sanityzacja promptów, ścisłe formaty wyjścia.

---

## 9. Kluczowe decyzje architektoniczne (ADR)

| ADR  | Decyzja                                       | Uzasadnienie                                     |
|------|-----------------------------------------------|--------------------------------------------------|
| 001  | Laravel zamiast Symfony                       | Szybszy MVP, Horizon, Eloquent, lepszy DX       |
| 003  | Dual-repository (publiczne portfolio / prywatne) | Bezpieczeństwo, portfolio, elastyczne licencje |
| 004  | generation-first vs translate-then-adapt      | Unikalna treść, adaptacja kulturowa              |
| 006  | Pennant dla feature flags                     | Natywne dla Laravela, prostota, DB + env         |
| 007  | Dwa poziomy blokad (Cache::add + unique index)| Unikanie duplikatów jobów + race conditions      |
| 008  | Strategia UUID v7/v4/v5                       | Sortowalne ID, kompatybilność                    |

---

## 10. DevOps i narzędzia

- **Docker:** Obowiązkowy w dev (PostgreSQL, Redis, Nginx, PHP-FPM).
- **Pint:** Formatowanie PSR-12.
- **PHPStan:** Analiza statyczna (poziom 5).
- **GitLeaks:** Wykrywanie sekretów.
- **Pre-commit:** Pint, PHPStan, testy, GitLeaks.

---

## 11. Rozwój wspomagany AI

### IDE i narzędzia

- **Cursor** – główne IDE z integracją Claude/GPT.
- **Antigravity** – alternatywne IDE oparte na AI.
- **LLM:** Claude (Sonnet, Opus), Gemini.

### Serwery MCP (Model Context Protocol)

MCP rozszerza asystentów AI o narzędzia i zasoby. Nauczyłem się tworzyć własny serwer MCP do generowania dokumentacji ([mcp-doc-generator](https://github.com/lukaszzychal/mcp-doc-generator)).

| Serwer           | Zastosowanie                                     |
|------------------|--------------------------------------------------|
| **GitHub MCP**   | Issues, PR, commity, wyszukiwanie, recenzje     |
| **Firecrawl MCP**| Web scraping, wyszukiwanie, crawl                |
| **Filesystem MCP**| Odczyt/zapis plików projektu                     |
| **Playwright MCP**| Automatyzacja przeglądarki, testy E2E           |
| **Postman MCP**  | Testowanie API, kolekcje                         |
| **Sequential Thinking MCP** | Wieloetapowe rozumowanie              |
| **Memory Bank MCP** | Długoterminowa pamięć projektu               |

### Skills (Cursor)

Własne Skills automatyzują powtarzalne zadania. W projekcie jest prosty przykładowy Skill: `php-pre-commit` (`.cursor/skills/php-pre-commit/`) – przypomnienie o uruchomieniu Pint, PHPStan, testów i GitLeaks przed commitem.

### Praktyki

- Prompt engineering przy generowaniu kodu.
- Code review wspomagane AI.
- Dokumentacja generowana przez AI z nadzorem człowieka.
- TDD z AI (generowanie testów, potem implementacja).

---

## 12. Punkty do rozmowy rekrutacyjnej

### „Opowiedz o projekcie”

> MovieMind API to REST API generujące unikalne opisy filmów, seriali i aktorów z użyciem AI. W przeciwieństwie do IMDb czy TMDb tworzy oryginalną treść za pomocą OpenAI zamiast kopiować metadane. Używa Laravela, architektury Event-Driven, Horizon do jobów w tle i subskrypcji z rate limitingiem opartym na planach. Zbudowane jako projekt portfolio z TDD, Dockerem i praktykami gotowymi do produkcji.

### „Jak rozwiązałeś problem duplikatów przy równoległych jobach?”

> Używam strategii dwupoziomowej (ADR-007). Poziom 1: `Cache::add` jako token in-flight w `JobStatusService::acquireGenerationSlot()`, żeby nie dispatchować wielu jobów dla tego samego sluga. Poziom 2: unikalny indeks na `movies.slug` plus obsługa `QueryException`, gdy dwa workery próbują utworzyć ten sam rekord. Poziom 1 ogranicza niepotrzebne joby; Poziom 2 obsługuje wyścigi przy wygaśnięciu slota cache.

### „Dlaczego Thin Controllers?”

> Ze względu na Zasadę Pojedynczej Odpowiedzialności. Kontrolery obsługują tylko HTTP – walidację, delegację i formatowanie odpowiedzi. Logika biznesowa jest w Services i Actions, więc jest reużywalna z API, CLI i Jobs. Ułatwia to testowanie (mock zależności) i zmianę warstwy transportowej (np. REST → GraphQL) bez dotykania rdzenia.

### „Jak działa rate limiting?”

> Opiera się na planach. Middleware `PlanBasedRateLimit` używa `UsageTracker` do sprawdzania limitów miesięcznych i na minutę na klucz API. Plany (Free, Pro, Enterprise) definiują te limity. Użycie jest zapisywane w DB; sprawdzanie na minutę korzysta z Redis. Przy przekroczeniu limitów zwracamy 429.

### „Co to Event-Driven i po co?”

> Eventy odłączają producentów od konsumentów. Np. dispatchowany jest `MovieGenerationRequested`; listenery `QueueMovieGenerationJob` i `SendOutgoingWebhookListener` reagują. Można dodawać listenery bez zmiany producenta. Joby wykonują workery Horizon, więc skalowanie polega na dodawaniu workerów.

### „Jak wykorzystałeś AI w rozwoju?”

> Używałem Cursor z Claude/Gemini do kodowania, refaktoryzacji i dokumentacji. Serwery MCP (GitHub, Firecrawl, Playwright, Postman, mcp-doc-generator) rozszerzają asystenta o narzędzia. W projekcie dodałem przykładowy Skill `php-pre-commit` do przypomnienia o narzędziach jakości przed commitem. Używałem AI do TDD (generowania testów) i code review, z nadzorem człowieka.

### „Co to MCP Server?”

> Model Context Protocol (MCP) pozwala asystentom AI wywoływać zewnętrzne narzędzia. Serwer MCP udostępnia narzędzia (np. API GitHub, automatyzacja przeglądarki) i zasoby (np. pliki). Asystent może przeszukiwać kod, tworzyć PR-y, uruchamiać testy E2E czy edytować pliki przez te narzędzia, zamiast tylko generować tekst.

---

## 13. Szybka ściągawka

### Kluczowe pliki

| Warstwa      | Lokalizacja                    | Przykłady                                  |
|-------------|---------------------------------|--------------------------------------------|
| Controllers | `api/app/Http/Controllers/`    | MovieController, GenerateController        |
| Services    | `api/app/Services/`            | MovieRetrievalService, UsageTracker        |
| Actions     | `api/app/Actions/`             | QueueMovieGenerationAction                 |
| Repositories| `api/app/Repositories/`        | MovieRepository, PersonRepository          |
| Events      | `api/app/Events/`              | MovieGenerationRequested                   |
| Listeners   | `api/app/Listeners/`           | QueueMovieGenerationJob                    |
| Jobs        | `api/app/Jobs/`                | RealGenerateMovieJob, MockGenerateMovieJob |

### Dokumentacja

- [Architektura](../technical/ARCHITECTURE.md)
- [ADR](../adr/README.md)
- [Strategia testów](../qa/TEST_STRATEGY.md)
- [Feature flags](../technical/FEATURE_FLAGS.md)
- [Subskrypcje i rate limiting](../knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md)
