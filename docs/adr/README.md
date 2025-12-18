# Architecture Decision Records (ADR)

## 📋 Spis Treści / Table of Contents

### 🇵🇱
1. [ADR-001: Wybór Laravel zamiast Symfony](#adr-001-wybór-laravel-zamiast-symfony)
2. [ADR-002: Hybrydowa architektura Python + PHP](#adr-002-hybrydowa-architektura-python--php)
3. [ADR-003: Strategia dual-repository](#adr-003-strategia-dual-repository)
4. [ADR-004: Wielojęzyczność - generation-first vs translate-then-adapt](#adr-004-wielojęzyczność---generation-first-vs-translate-then-adapt)
5. [ADR-005: Git Trunk Flow](#adr-005-git-trunk-flow)
6. [ADR-006: Feature Flags Strategy](#adr-006-feature-flags-strategy)
7. [ADR-007: Blokady generowania opisów AI](#adr-007-blokady-generowania-opisów-ai)
8. [ADR-008: Strategia UUID - v7, v4, v5](#adr-008-strategia-uuid---v7-v4-v5)

### 🇬🇧
1. [ADR-001: Choosing Laravel over Symfony](#adr-001-choosing-laravel-over-symfony)
2. [ADR-002: Hybrid Python + PHP architecture](#adr-002-hybrid-python--php-architecture)
3. [ADR-003: Dual-repository strategy](#adr-003-dual-repository-strategy)
4. [ADR-004: Multilingual - generation-first vs translate-then-adapt](#adr-004-multilingual---generation-first-vs-translate-then-adapt)
5. [ADR-005: Git Trunk Flow](#adr-005-git-trunk-flow-en)
6. [ADR-006: Feature Flags Strategy](#adr-006-feature-flags-strategy-en)
7. [ADR-007: AI description generation locks](#adr-007-ai-description-generation-locks)
8. [ADR-008: UUID Strategy - v7, v4, v5](#adr-008-uuid-strategy---v7-v4-v5)

---

## ADR-001: Wybór Laravel zamiast Symfony

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Wybór frameworka PHP dla admin panelu MovieMind API

### 🎯 Decyzja
Używamy **Laravel 11** zamiast Symfony 7 dla admin panelu i wewnętrznego API.

### 💡 Uzasadnienie

#### ✅ Zalety Laravel:
- **Szybszy development** - więcej gotowych rozwiązań
- **Laravel Nova** - gotowy admin panel (idealny dla zarządzania filmami/aktorami)
- **Eloquent ORM** - bardziej intuicyjny niż Doctrine
- **Laravel Sanctum** - łatwa autoryzacja API dla RapidAPI
- **Laravel Horizon** - zarządzanie kolejkami AI jobów
- **Laravel Telescope** - debugging i monitoring
- **Artisan CLI** - potężne narzędzia do generowania kodu
- **Lepsze dokumentacje** - więcej tutoriali i przykładów

#### ❌ Wady Symfony:
- Bardziej skomplikowany dla prostych CRUD operacji
- Doctrine ORM wymaga więcej konfiguracji
- Brak gotowego admin panelu (trzeba budować od zera)
- Mniej gotowych rozwiązań dla API

### 🔄 Konsekwencje
- **Pozytywne:**
  - Szybszy development MVP
  - Gotowy admin panel z Laravel Nova
  - Łatwiejsza nauka dla zespołu
  - Lepsze narzędzia do debugowania

- **Negatywne:**
  - Laravel może być mniej "enterprise-ready" niż Symfony
  - Mniej elastyczny dla bardzo złożonych domen

### 📊 Alternatywy rozważane:
1. **Symfony 7** - odrzucone z powodu złożoności
2. **Laravel 11** - wybrane ✅
3. **Pure PHP** - odrzucone z powodu braku frameworka

---

## ADR-001: Choosing Laravel over Symfony

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** Choosing PHP framework for MovieMind API admin panel

### 🎯 Decision
We use **Laravel 11** instead of Symfony 7 for admin panel and internal API.

### 💡 Rationale

#### ✅ Laravel Advantages:
- **Faster development** - more ready-made solutions
- **Laravel Nova** - ready admin panel (perfect for movies/actors management)
- **Eloquent ORM** - more intuitive than Doctrine
- **Laravel Sanctum** - easy API authentication for RapidAPI
- **Laravel Horizon** - AI job queue management
- **Laravel Telescope** - debugging and monitoring
- **Artisan CLI** - powerful code generation tools
- **Better documentation** - more tutorials and examples

#### ❌ Symfony Disadvantages:
- More complex for simple CRUD operations
- Doctrine ORM requires more configuration
- No ready admin panel (need to build from scratch)
- Fewer ready-made solutions for APIs

### 🔄 Consequences
- **Positive:**
  - Faster MVP development
  - Ready admin panel with Laravel Nova
  - Easier team learning
  - Better debugging tools

- **Negative:**
  - Laravel may be less "enterprise-ready" than Symfony
  - Less flexible for very complex domains

### 📊 Alternatives considered:
1. **Symfony 7** - rejected due to complexity
2. **Laravel 11** - chosen ✅
3. **Pure PHP** - rejected due to lack of framework

---

## ADR-002: Hybrydowa architektura Python + PHP

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Architektura systemu MovieMind API

### 🎯 Decyzja
Używamy hybrydowej architektury:
- **API Gateway (Kong/Tyk)** - publiczne API (RapidAPI)
- **PHP Laravel** - admin panel i wewnętrzne API

### 💡 Uzasadnienie

#### ✅ Zalety hybrydowej architektury:
- **Izolacja ryzyka** - publiczne API oddzielone od wewnętrznego
- **Skalowalność** - niezależne skalowanie serwisów
- **Zgodność z RapidAPI** - Python naturalny dla ML/AI
- **Komfort pracy** - PHP dla domeny, Python dla AI
- **Rozdział kosztów** - optymalizacja kosztów per serwis

#### 🔄 Przepływ danych:
```
RapidAPI → API Gateway → Laravel → RabbitMQ → Horizon Workers → OpenAI → PostgreSQL → Redis → Admin Panel
```

### 📊 Alternatywy rozważane:
1. **Tylko Python** - odrzucone (zespół zna PHP)
2. **Tylko PHP** - odrzucone (Python lepszy dla AI)
3. **Hybrydowa** - wybrana ✅

---

## ADR-002: Hybrid Python + PHP architecture

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** MovieMind API system architecture

### 🎯 Decision
We use hybrid architecture:
- **API Gateway (Kong/Tyk)** - public API (RapidAPI)
- **PHP Laravel** - admin panel and internal API

### 💡 Rationale

#### ✅ Hybrid architecture advantages:
- **Risk isolation** - public API separated from internal
- **Scalability** - independent service scaling
- **RapidAPI compatibility** - Python natural for ML/AI
- **Work comfort** - PHP for domain, Python for AI
- **Cost separation** - cost optimization per service

#### 🔄 Data flow:
```
RapidAPI → API Gateway → Laravel → RabbitMQ → Horizon Workers → OpenAI → PostgreSQL → Redis → Admin Panel
```

### 📊 Alternatives considered:
1. **Python only** - rejected (team knows PHP)
2. **PHP only** - rejected (Python better for AI)
3. **Hybrid** - chosen ✅

---

## ADR-003: Strategia dual-repository

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Zarządzanie kodem MovieMind API

### 🎯 Decyzja
Używamy strategii dual-repository:
- **Publiczne repo** - portfolio, demonstracja umiejętności
- **Prywatne repo** - pełny produkt komercyjny

### 💡 Uzasadnienie

#### ✅ Zalety dual-repository:
- **Bezpieczeństwo** - klucze API tylko w prywatnym repo
- **Portfolio** - publiczne repo pokazuje umiejętności
- **Elastyczność** - różne licencje i cele
- **Kontrola** - pełna kontrola nad komercyjnym produktem

#### 📁 Podział:
- **Publiczne:** Mock AI, przykładowe dane, MIT licencja
- **Prywatne:** Prawdziwe AI, billing, webhooki, komercyjna licencja

---

## ADR-003: Dual-repository strategy

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** MovieMind API code management

### 🎯 Decision
We use dual-repository strategy:
- **Public repo** - portfolio, skills demonstration
- **Private repo** - full commercial product

### 💡 Rationale

#### ✅ Dual-repository advantages:
- **Security** - API keys only in private repo
- **Portfolio** - public repo shows skills
- **Flexibility** - different licenses and goals
- **Control** - full control over commercial product

#### 📁 Division:
- **Public:** Mock AI, sample data, MIT license
- **Private:** Real AI, billing, webhooks, commercial license

---

## ADR-004: Wielojęzyczność - generation-first vs translate-then-adapt

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Strategia wielojęzyczności MovieMind API

### 🎯 Decyzja
Używamy strategii **generation-first** dla opisów i biografii, **translate-then-adapt** dla krótkich streszczeń.

### 💡 Uzasadnienie

#### ✅ Generation-first dla długich treści:
- **Unikalność** - każdy opis generowany od zera
- **Brak plagiatu** - nie kopiujemy z innych źródeł
- **Jakość** - treści dostosowane do kultury
- **Różnorodność** - różne style i konteksty

#### ✅ Translate-then-adapt dla krótkich treści:
- **Spójność** - zachowanie faktów
- **Szybkość** - szybsze niż generowanie od zera
- **Koszt** - niższy koszt tokenów

### 📊 Obsługiwane języki:
1. **Polski (pl-PL)** - język docelowy
2. **Angielski (en-US)** - język kanoniczny
3. **Niemiecki (de-DE)** - rynek europejski
4. **Francuski (fr-FR)** - rynek europejski
5. **Hiszpański (es-ES)** - rynek hiszpańskojęzyczny

---

## ADR-004: Multilingual - generation-first vs translate-then-adapt

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** MovieMind API multilingual strategy

### 🎯 Decision
We use **generation-first** strategy for descriptions and biographies, **translate-then-adapt** for short summaries.

### 💡 Rationale

#### ✅ Generation-first for long content:
- **Uniqueness** - each description generated from scratch
- **No plagiarism** - we don't copy from other sources
- **Quality** - content adapted to culture
- **Diversity** - different styles and contexts

#### ✅ Translate-then-adapt for short content:
- **Consistency** - preserving facts
- **Speed** - faster than generating from scratch
- **Cost** - lower token costs

### 📊 Supported languages:
1. **Polish (pl-PL)** - target language
2. **English (en-US)** - canonical language
3. **German (de-DE)** - European market
4. **French (fr-FR)** - European market
5. **Spanish (es-ES)** - Spanish-speaking market

---

## ADR-005: Git Trunk Flow

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Strategia zarządzania kodem i deploymentu MovieMind API

### 🎯 Decyzja
Używamy **Git Trunk Flow** jako głównej strategii zarządzania kodem.

### 💡 Uzasadnienie

#### ✅ Zalety Trunk Flow:
- **Prostszy workflow** - jeden główny branch (main)
- **Szybsze integracje** - częste mergowanie do main
- **Mniej konfliktów** - krótsze żywotność feature branchy
- **Lepsze CI/CD** - każdy commit na main może być deployowany
- **Feature flags** - kontrola funkcji bez branchy
- **Rollback** - łatwy rollback przez feature flags

#### 🔄 Workflow:
1. **Feature branch** - `feature/ai-description-generation`
2. **Pull Request** - code review i testy
3. **Merge do main** - po zatwierdzeniu
4. **Deploy** - automatyczny deploy z feature flags
5. **Feature flag** - kontrola włączenia funkcji

### 📊 Alternatywy rozważane:
1. **GitFlow** - odrzucone (zbyt skomplikowany dla małego zespołu)
2. **GitHub Flow** - rozważane (brak feature flags)
3. **Trunk Flow + Feature Flags** - wybrane ✅

### 🔧 Implementacja:
- **Main branch** - zawsze deployable
- **Feature branchy** - krótkoterminowe (1-3 dni)
- **Feature flags** - kontrola funkcji w runtime
- **CI/CD** - automatyczny deploy na każdy merge

---

## ADR-005: Git Trunk Flow

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** MovieMind API code management and deployment strategy

### 🎯 Decision
We use **Git Trunk Flow** as the main code management strategy.

### 💡 Rationale

#### ✅ Trunk Flow Advantages:
- **Simpler workflow** - single main branch (main)
- **Faster integrations** - frequent merging to main
- **Fewer conflicts** - shorter feature branch lifetime
- **Better CI/CD** - every commit on main can be deployed
- **Feature flags** - feature control without branches
- **Rollback** - easy rollback through feature flags

#### 🔄 Workflow:
1. **Feature branch** - `feature/ai-description-generation`
2. **Pull Request** - code review and tests
3. **Merge to main** - after approval
4. **Deploy** - automatic deploy with feature flags
5. **Feature flag** - feature enablement control

### 📊 Alternatives considered:
1. **GitFlow** - rejected (too complex for small team)
2. **GitHub Flow** - considered (no feature flags)
3. **Trunk Flow + Feature Flags** - chosen ✅

### 🔧 Implementation:
- **Main branch** - always deployable
- **Feature branches** - short-term (1-3 days)
- **Feature flags** - runtime feature control
- **CI/CD** - automatic deploy on every merge

---

## ADR-006: Feature Flags Strategy

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-01-27  
**Kontekst:** Strategia kontroli funkcji MovieMind API

### 🎯 Decyzja
Używamy **oficjalnej integracji Laravel Feature Flags** (`laravel/feature-flags`) zamiast własnej implementacji.

### 💡 Uzasadnienie

#### ✅ Zalety oficjalnej integracji Laravel:
- **Oficjalne wsparcie** - wspierane przez Laravel team
- **Prostota** - gotowe API i funkcje
- **Bezpieczeństwo** - przetestowane przez społeczność
- **Integracja** - idealna integracja z Laravel
- **Funkcje** - więcej funkcji out-of-the-box
- **Maintenance** - utrzymywane przez zespół Laravel

#### 🎛️ Typy Feature Flags:
1. **Boolean flags** - włącz/wyłącz funkcje
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - dla konkretnych użytkowników
4. **Environment flags** - różne ustawienia per środowisko

#### 🔧 Implementacja Laravel Feature Flags:
```php
// Instalacja
composer require laravel/feature-flags

// Użycie w kontrolerze
use Laravel\FeatureFlags\Facades\FeatureFlags;

class MovieController extends Controller
{
    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        if (!FeatureFlags::enabled('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }
        
        // Reszta logiki...
    }
}
```

### 📊 Alternatywy rozważane:
1. **LaunchDarkly** - odrzucone (koszt, złożoność)
2. **Split.io** - odrzucone (koszt)
3. **Unleash** - rozważane (open source)
4. **Własna implementacja** - odrzucone (dużo pracy)
5. **Laravel Feature Flags** - wybrane ✅

### 🎯 Użycie w MovieMind API:
- **AI Generation** - gradual rollout nowych modeli
- **Multilingual** - włączanie nowych języków
- **Style Packs** - testowanie nowych stylów
- **Rate Limiting** - różne limity dla różnych użytkowników

---

## ADR-006: Feature Flags Strategy

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-01-27  
**Context:** MovieMind API feature control strategy

### 🎯 Decision
We use **official Laravel Feature Flags integration** (`laravel/feature-flags`) instead of custom implementation.

### 💡 Rationale

#### ✅ Official Laravel integration advantages:
- **Official support** - supported by Laravel team
- **Simplicity** - ready-made API and functions
- **Security** - tested by community
- **Integration** - perfect Laravel integration
- **Features** - more features out-of-the-box
- **Maintenance** - maintained by Laravel team

#### 🎛️ Feature Flag Types:
1. **Boolean flags** - enable/disable features
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - for specific users
4. **Environment flags** - different settings per environment

#### 🔧 Laravel Feature Flags Implementation:
```php
// Installation
composer require laravel/feature-flags

// Usage in controller
use Laravel\FeatureFlags\Facades\FeatureFlags;

class MovieController extends Controller
{
    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        if (!FeatureFlags::enabled('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }
        
        // Rest of logic...
    }
}
```

### 📊 Alternatives considered:
1. **LaunchDarkly** - rejected (cost, complexity)
2. **Split.io** - rejected (cost)
3. **Unleash** - considered (open source)
4. **Custom implementation** - rejected (too much work)
5. **Laravel Feature Flags** - chosen ✅

### 🎯 Usage in MovieMind API:
- **AI Generation** - gradual rollout of new models
- **Multilingual** - enabling new languages
- **Style Packs** - testing new styles
- **Rate Limiting** - different limits for different users

---

*Dokument utworzony: 2025-01-27*  
*Document created: 2025-01-27*

---

## ADR-007: Blokady generowania opisów AI

### 🇵🇱

**Status:** ✅ Zaakceptowane / Accepted  
**Data:** 2025-11-12  
**Kontekst:** Duplikujące się opisy filmów podczas równoległych jobów AI (`RealGenerateMovieJob`)

### 🎯 Decyzja

Używamy **dwupoziomowej strategii** zapobiegania duplikatom:

1. **Poziom 1: "In-flight token" (`Cache::add`)** - w `QueueMovieGenerationAction` i `QueuePersonGenerationAction` używamy `JobStatusService::acquireGenerationSlot()` do zapobiegania dispatchowaniu wielu jobów dla tego samego slug.
2. **Poziom 2: Unique index + exception handling** - w `RealGenerateMovieJob` i `RealGeneratePersonJob` polegamy na unikalnym indeksie `movies.slug` / `people.slug` oraz obsłudze `QueryException`, aby wykrywać równoległe próby utworzenia rekordu i kończyć job na istniejącym rekordzie.

### 💡 Uzasadnienie

#### Poziom 1 (`Cache::add`):
- **Atomowa operacja** - `Cache::add()` gwarantuje, że tylko pierwszy request ustawi wartość.
- **Oszczędność zasobów** - zapobiega niepotrzebnym jobom (OpenAI API calls).
- **Prosty mechanizm** - brak oczekiwania na lock, natychmiastowa odpowiedź.

#### Poziom 2 (Unique index + exception):
- **Baza danych zapewnia spójność** (brak duplikatów) niezależnie od liczby workerów.
- **Mniejsze opóźnienia** – brak oczekiwania na lock.
- **Prostszy kod** – brak dodatkowej logiki „po wyjściu z locka”.
- **Lepsze logowanie** – SQLSTATE jasno identyfikuje konflikt.
- **Działa zarówno na PostgreSQL (prod), jak i SQLite (testy)**.

#### Dlaczego dwa poziomy?
- **Poziom 1** zapobiega niepotrzebnym jobom (oszczędność zasobów).
- **Poziom 2** zabezpiecza na wypadek wyścigu (deterministyczne zachowanie).

### 🔄 Konsekwencje

- **Pozytywne:**
  - Eliminacja dodatkowych opisów tworzonych przez drugi job.
  - Większa skalowalność przy wielu instancjach Horizon.
  - Oszczędność zasobów (OpenAI API calls) dzięki poziomowi 1.
  - Deterministyczne zachowanie dzięki poziomowi 2.
  - Możliwość rozszerzenia obsługi o inne konflikty (np. per locale).
- **Negatywne:**
  - Konieczność utrzymania listy kodów błędów dla obsługiwanych baz.
  - Logika pomocnicza (np. awans opisu domyślnego) nadal wymaga wąskich lokalnych locków.
  - Dwa mechanizmy do utrzymania (Cache::add + unique index).

### 📊 Alternatywy rozważane:

1. **`Cache::lock` (Redis)** – odrzucone (powodowało duplikację opisów, globalny mutex spowalniał równoległe joby).
2. **`SELECT ... FOR UPDATE` + tabela kontrolna** – odrzucone (zbyt złożone dla SQLite/testów).
3. **Tylko `Cache::add`** – odrzucone (nie zabezpiecza przed edge cases, gdy slot wygaśnie).
4. **Tylko unique index + exception** – odrzucone (nie zapobiega niepotrzebnym jobom).
5. **Dwupoziomowa strategia (`Cache::add` + unique index)** – wybrane ✅.

---

## ADR-007: AI description generation locks

### 🇬🇧

**Status:** ✅ Accepted  
**Date:** 2025-11-12  
**Context:** Duplicate movie descriptions triggered by concurrent AI jobs (`RealGenerateMovieJob`)

### 🎯 Decision

We use a **two-level strategy** to prevent duplicates:

1. **Level 1: "In-flight token" (`Cache::add`)** - In `QueueMovieGenerationAction` and `QueuePersonGenerationAction`, we use `JobStatusService::acquireGenerationSlot()` to prevent dispatching multiple jobs for the same slug.
2. **Level 2: Unique index + exception handling** - In `RealGenerateMovieJob` and `RealGeneratePersonJob`, we rely on the unique index `movies.slug` / `people.slug` and catch `QueryException` to detect concurrent record creation attempts and finish the job on the existing record.

### 💡 Rationale

#### Level 1 (`Cache::add`):
- **Atomic operation** - `Cache::add()` guarantees that only the first request will set the value.
- **Resource savings** - prevents unnecessary jobs (OpenAI API calls).
- **Simple mechanism** - no lock waiting, immediate response.

#### Level 2 (Unique index + exception):
- **Database guarantees uniqueness** regardless of worker count.
- **Lower latency** – no lock waiting.
- **Simpler code** – no "post-lock reconciliation".
- **Better logging** – SQLSTATE clearly identifies conflict.
- **Works in both PostgreSQL (prod) and SQLite (tests)**.

#### Why two levels?
- **Level 1** prevents unnecessary jobs (resource savings).
- **Level 2** protects against race conditions (deterministic behavior).

### 🔄 Consequences

- **Positive:**
  - Stops secondary descriptions from being generated by a second worker.
  - Scales better with multiple Horizon workers.
  - Resource savings (OpenAI API calls) thanks to level 1.
  - Deterministic behavior thanks to level 2.
  - Exception branch can cover future conflicts (e.g. locale-specific).
- **Negative:**
  - Requires maintaining SQLSTATE mappings per driver.
  - Auxiliary logic (e.g. promoting default description) still uses narrow locks.
  - Two mechanisms to maintain (Cache::add + unique index).

### 📊 Alternatives considered:

1. **`Cache::lock` (Redis)** – rejected (caused duplicate descriptions, global mutex slowed down parallel jobs).
2. **`SELECT ... FOR UPDATE` + control table** – rejected as overly complex for SQLite/tests.
3. **Only `Cache::add`** – rejected (doesn't protect against edge cases when slot expires).
4. **Only unique index + exception** – rejected (doesn't prevent unnecessary jobs).
5. **Two-level strategy (`Cache::add` + unique index)** – chosen ✅.

---

---

*Dokument zaktualizowany: 2025-12-18*  
*Document updated: 2025-12-18*  
*Ostatnia aktualizacja: 2025-12-18 - Dodano ADR-008: Strategia UUID (v7, v4, v5)*  
*Last update: 2025-12-18 - Added ADR-008: UUID Strategy (v7, v4, v5)*
