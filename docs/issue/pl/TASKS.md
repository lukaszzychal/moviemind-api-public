# 📋 Backlog Zadań - MovieMind API

**Ostatnia aktualizacja:** 2025-01-27  
**Status:** 🔄 Aktywny

---

## 📝 **Format Zadania**

Każde zadanie ma następującą strukturę:
- `[STATUS]` - Status zadania (⏳ PENDING, 🔄 IN_PROGRESS, ✅ COMPLETED, ❌ CANCELLED)
- `ID` - Unikalny identyfikator zadania
- `Tytuł` - Krótki opis zadania
- `Opis` - Szczegółowy opis lub link do dokumentacji
- `Priorytet` - 🔴 Wysoki, 🟡 Średni, 🟢 Niski
- `Szacowany czas` - W godzinach (opcjonalnie)
- `Czas rozpoczęcia` - Data/godzina rozpoczęcia
- `Czas zakończenia` - Data/godzina zakończenia
- `Czas realizacji` - Automatycznie liczony (różnica zakończenie - rozpoczęcie, wypełnia Agent AI przy typie `🤖`)
- `Realizacja` - Kto wykonał zadanie: `🤖 AI Agent`, `👨‍💻 Manualna`, `⚙️ Hybrydowa`

---

## 🎯 **Aktywne Zadania**

### 🤖 Funkcja priorytetyzacji

> **Cel:** zapewnić spójną analizę ważności i kolejności wykonania zadań.

1. **Zbierz dane wejściowe:** status, priorytet, zależności, ryzyko blokady, wymagane zasoby.
2. **Oceń ważność:**
   - 🔴 krytyczne dla stabilności/bezpieczeństwa → najwyższy priorytet.
   - 🟡 średni, ale z wpływem na inne zadania → kolejny w kolejce.
   - 🟢 roadmapa lub prace opcjonalne → realizuj po zadaniach blokujących.
3. **Sprawdź zależności:** jeśli zadanie odblokowuje inne, awansuj je wyżej.
4. **Uwzględnij synergię:** grupuj zadania o podobnym kontekście (np. CI, bezpieczeństwo).
5. **Wynik:** ułóż listę rekomendowanego porządku + krótka notatka *dlaczego* (np. „odblokowuje X", „wspiera testy", „roadmapa").

> **Przykład raportu:**  
> 1. `TASK-007` – centralizuje flagi; fundament dla ochrony Horizon i kontroli AI.  
> 2. `TASK-013` – zabezpiecza panel Horizon po zmianach flag.  
> 3. `TASK-020` – audyt AI korzysta z ustabilizowanych flag oraz monitoringu Horizon.  
> …

---

## 📊 Rekomendowana Kolejność Wykonania

### 🎯 Dla MVP (Minimum Viable Product)

**Cel MVP:** Działająca wersja API gotowa do deploymentu na RapidAPI z podstawowymi funkcjami.

#### Faza 1: Krytyczne dla stabilności i bezpieczeństwa (🔴 Wysoki Priorytet)

1. **`TASK-044` (Faza 1)** - Integracja TMDb API dla weryfikacji istnienia filmów przed generowaniem AI
   - **Dlaczego:** **KRYTYCZNY PROBLEM** - System zwraca 202 z job_id, ale job kończy się FAILED z NOT_FOUND nawet dla istniejących filmów. System jest obecnie nie do użycia dla wielu filmów.
   - **Czas:** 8-12h (Faza 1)
   - **Status:** ✅ COMPLETED (2025-12-01)
   - **Priorytet:** 🔴🔴🔴 Najwyższy - wymaga natychmiastowej naprawy
   - **Następne:** Faza 2 (Optymalizacja) - rate limiting, dodatkowe testy

2. **`TASK-050`** - Dodanie Basic Auth dla endpointów admin
   - **Dlaczego:** **KRYTYCZNY PROBLEM BEZPIECZEŃSTWA** - Endpointy `/api/v1/admin/*` są obecnie publiczne i niechronione. Każdy może przełączać flagi, co stanowi poważne zagrożenie bezpieczeństwa.
   - **Czas:** 2-3h
   - **Status:** ✅ COMPLETED (2025-12-16)
   - **Priorytet:** 🔴🔴🔴 Najwyższy - wymaga natychmiastowej naprawy
   - **Zależności:** Brak

3. **`TASK-048`** - Kompleksowa dokumentacja bezpieczeństwa aplikacji (OWASP, AI security, audyty)
   - **Dlaczego:** Bezpieczeństwo - kompleksowa dokumentacja bezpieczeństwa z OWASP Top 10, OWASP LLM Top 10, procedurami audytów
   - **Czas:** 4-6h
   - **Status:** ✅ COMPLETED (2025-12-06)
   - **Priorytet:** 🔴 Wysoki - bezpieczeństwo jest najwyższym priorytetem
   - **Zależności:** Brak

3. **`TASK-043`** - Implementacja zasady wykrywania BREAKING CHANGE
   - **Dlaczego:** Bezpieczeństwo zmian - wymaganie analizy BREAKING CHANGE przed wprowadzeniem zmian
   - **Czas:** 2-3h
   - **Status:** ✅ COMPLETED (2025-12-06)
   - **Priorytet:** 🔴 Wysoki - bezpieczeństwo zmian
   - **Zależności:** Brak

4. **`TASK-037` (Faza 2-3)** - Weryfikacja istnienia filmów/osób przed generowaniem AI
   - **Dlaczego:** Zapobiega halucynacjom AI, kluczowe dla jakości danych
   - **Czas:** 8-12h (Faza 2) + 20-30h (Faza 3)
   - **Status:** ⏳ PENDING (Faza 1 ✅ COMPLETED)

5. **`TASK-038` (Faza 2)** - Weryfikacja zgodności danych AI z slugiem
   - **Dlaczego:** Zapewnia spójność danych, zapobiega błędnym generacjom
   - **Czas:** 6-8h
   - **Status:** ✅ COMPLETED (Faza 1 ✅, Faza 2 ✅)

6. **`TASK-013`** - Konfiguracja dostępu do Horizon
   - **Dlaczego:** Bezpieczeństwo - zabezpiecza panel Horizon w produkcji
   - **Czas:** 1-2h
   - **Status:** ✅ COMPLETED

#### Faza 2: Usprawnienia funkcjonalne (🟡 Średni Priorytet)

4. **`TASK-022`** - Endpoint listy osób (List People)
   - **Dlaczego:** Parzystość API - uzupełnia podstawowe endpointy
   - **Czas:** 2-3h
   - **Status:** ✅ COMPLETED (2025-12-14)

5. **`TASK-024`** - Wdrożenie planu baseline locking
   - **Dlaczego:** Stabilizuje mechanizm generowania, zapobiega race conditions
   - **Czas:** 4h
   - **Status:** ✅ COMPLETED (2025-12-16)
   - **Zależności:** TASK-012 ✅, TASK-023 ✅

6. **`TASK-025`** - Standaryzacja flag produktowych i developerskich
   - **Dlaczego:** Uporządkowanie zarządzania flagami, wspiera rozwój
   - **Czas:** 1h
   - **Status:** ✅ COMPLETED

7. **`TASK-026`** - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
   - **Dlaczego:** Poprawa UX - użytkownik widzi poziom pewności generacji
   - **Czas:** 1-2h
   - **Status:** ✅ COMPLETED (2025-12-16)

#### Faza 3: Infrastruktura i CI/CD (🟡 Średni Priorytet)

8. **`TASK-011`** - Stworzenie CI dla staging (GHCR)
   - **Dlaczego:** Automatyzacja deploymentu, szybsze iteracje
   - **Czas:** 3h
   - **Status:** ✅ COMPLETED (2025-12-16)

9. **`TASK-015`** - Automatyczne testy Newman w CI
   - **Dlaczego:** Automatyczna weryfikacja API, wyższa jakość
   - **Czas:** 2h
   - **Status:** ⏳ PENDING

10. **`TASK-019`** - Migracja produkcyjnego obrazu Docker na Distroless
    - **Dlaczego:** Bezpieczeństwo - zmniejszenie powierzchni ataku
    - **Czas:** 3-4h
    - **Status:** ⏳ PENDING

#### Faza 4: Refaktoryzacja i czyszczenie (🟡 Średni Priorytet)

11. **`TASK-033`** - Usunięcie modelu Actor i konsolidacja na Person
    - **Dlaczego:** Uporządkowanie kodu, eliminacja legacy
    - **Czas:** 2-3h
    - **Status:** ⏳ PENDING
    - **Zależności:** TASK-032, TASK-022

12. **`TASK-032`** - Automatyczne tworzenie obsady przy generowaniu filmu
    - **Dlaczego:** Uzupełnia dane filmów, lepsze UX
    - **Czas:** 3h
    - **Status:** ⏳ PENDING
    - **Zależności:** TASK-022

13. **`TASK-028`** - Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues
    - **Dlaczego:** Usprawnienie workflow, lepsze zarządzanie zadaniami
    - **Czas:** 0.5-1h
    - **Status:** ⏳ PENDING

14. **`TASK-029`** - Uporządkowanie testów według wzorca AAA lub GWT
    - **Dlaczego:** Standaryzacja testów, lepsza czytelność
    - **Czas:** 2-3h
    - **Status:** ⏳ PENDING

    - **Dlaczego:** Reużywalność, możliwość użycia w innych projektach
    - **Czas:** 3-4h
    - **Status:** ⏳ PENDING
    - **Zależności:** TASK-017 ✅

#### Faza 5: Dokumentacja i analiza (🟡/🟢 Priorytet)

16. **`TASK-031`** - Kierunek rozwoju wersjonowania opisów AI
    - **Dlaczego:** Dokumentacja decyzji architektonicznej
    - **Czas:** 1-2h
    - **Status:** ⏳ PENDING

17. **`TASK-040`** - Analiza formatu TOON vs JSON dla komunikacji z AI
    - **Dlaczego:** Optymalizacja kosztów (oszczędność tokenów)
    - **Czas:** 2-3h
    - **Status:** ⏳ PENDING

18. **`TASK-030`** - Opracowanie dokumentu o technice testów „trzech linii"
    - **Dlaczego:** Dokumentacja techniczna, wspiera TASK-029
    - **Czas:** 1-2h
    - **Status:** ⏳ PENDING
    - **Zależności:** TASK-029

---

### 🧪 Dla POC (Proof of Concept)

**Cel POC:** Minimalna wersja demonstracyjna pokazująca działanie AI generacji.

#### Minimalny zakres POC:

1. **`TASK-013`** - Konfiguracja dostępu do Horizon (bezpieczeństwo) ✅
2. **`TASK-022`** - Endpoint listy osób (podstawowa funkcjonalność)
3. **`TASK-025`** - Standaryzacja flag (uproszczenie zarządzania)

**Uwaga:** Większość zadań POC jest już zrealizowana (TASK-001, TASK-002, TASK-003, TASK-012, TASK-023 ✅). POC jest praktycznie gotowy.

---

### 📋 Podsumowanie według Priorytetów

#### 🔴 Wysoki Priorytet (Krytyczne)
- ~~`TASK-050` - Basic Auth dla endpointów admin~~ ✅ COMPLETED
- ~~`TASK-037` (Faza 2-3) - Weryfikacja istnienia przed AI~~ ✅ COMPLETED
- ~~`TASK-038` (Faza 2) - Weryfikacja zgodności danych~~ ✅ COMPLETED

#### 🟡 Średni Priorytet (Ważne)
- ~~`TASK-013` - Konfiguracja Horizon~~ ✅ COMPLETED
- ~~`TASK-022` - Lista osób~~ ✅ COMPLETED
- ~~`TASK-024` - Baseline locking~~ ✅ COMPLETED
- ~~`TASK-025` - Standaryzacja flag~~ ✅ COMPLETED
- ~~`TASK-026` - Pola zaufania~~ ✅ COMPLETED
- ~~`TASK-011` - CI dla staging~~ ✅ COMPLETED
- `TASK-015` - Testy Newman
- `TASK-019` - Docker Distroless
- ~~`TASK-032` - Automatyczna obsada~~ ✅ COMPLETED
- ~~`TASK-033` - Usunięcie Actor~~ ✅ COMPLETED
- `TASK-028` - Synchronizacja Issues
- `TASK-029` - Standaryzacja testów
- `TASK-031` - Wersjonowanie opisów
- `TASK-040` - Analiza TOON vs JSON

#### 🟢 Niski Priorytet (Roadmap)
- `TASK-008` - Webhooks System
- `TASK-009` - Admin UI
- `TASK-010` - Analytics/Monitoring Dashboards
- `TASK-030` - Dokumentacja testów "trzech linii"

---

### ⏳ PENDING

#### `TASK-RAPI-001` - API Key Authentication System
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 16-20 godzin
- **Czas rozpoczęcia:** 2025-01-27
- **Czas zakończenia:** 2025-01-27
- **Czas realizacji:** ~04h00m (już było częściowo zaimplementowane)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja systemu autoryzacji API przez klucze API (wymagane dla RapidAPI)
- **Szczegóły:**
  - ✅ Model i migracja `api_keys` (hashowane klucze, plany, status)
  - ✅ Service `ApiKeyService` (generowanie, walidacja, tracking)
  - ✅ Middleware `RapidApiAuth` (weryfikacja header `X-RapidAPI-Key`)
  - ✅ Admin controller do zarządzania kluczami
  - ✅ Testy jednostkowe i feature
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
- **Ukończone:** 2025-01-27
- **Dokumentacja:** `docs/issue/RAPIDAPI_INTEGRATION_PLAN.md`
- **Uwaga:** Zadanie było już w dużej mierze zaimplementowane, tylko zweryfikowano i uzupełniono brakujące elementy

---

#### `TASK-RAPI-002` - Subscription Plans System
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 12-16 godzin
- **Czas rozpoczęcia:** 2025-01-27
- **Czas zakończenia:** 2025-01-27
- **Czas realizacji:** ~03h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja systemu planów subskrypcyjnych (Free/Pro/Enterprise)
- **Szczegóły:**
  - ✅ Model i migracja `subscription_plans` (Free: 100/mies, Pro: 10k/mies, Enterprise: unlimited)
  - ✅ Service `PlanService` (pobieranie planów, sprawdzanie funkcji, rate limits)
  - ✅ Seeder z 3 planami (Free, Pro, Enterprise)
  - ✅ Factory dla SubscriptionPlan z metodami free(), pro(), enterprise()
  - ✅ Relacja w ApiKey do SubscriptionPlan
  - ✅ Foreign key constraint w api_keys.plan_id
  - ✅ Testy jednostkowe (13 testów) i feature (6 testów) - wszystkie przechodzą
  - ⏳ Integracja z feature flags (opcjonalne, do zrobienia w TASK-RAPI-004)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
- **Ukończone:** 2025-01-27
- **Dokumentacja:** `docs/issue/RAPIDAPI_INTEGRATION_PLAN.md`

---

#### `TASK-RAPI-003` - Plan-based Rate Limiting
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 12-16 godzin
- **Czas rozpoczęcia:** 2025-01-27
- **Czas zakończenia:** 2025-01-27
- **Czas realizacji:** ~04h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja rate limiting opartego na planach subskrypcyjnych
- **Szczegóły:**
  - ✅ Middleware `PlanBasedRateLimit` (monthly + per-minute limits)
  - ✅ Service `UsageTracker` (tracking użycia, pozostały limit)
  - ✅ Model i migracja `api_usage` (logowanie requestów)
  - ✅ Job `ResetMonthlyUsageJob` (scheduled na 1. dnia miesiąca)
  - ✅ Scheduled job w `routes/console.php`
  - ✅ Testy jednostkowe (7 testów) i feature (4 testy) - wszystkie przechodzą
  - ✅ Headers X-RateLimit-* w odpowiedziach
  - ✅ Rejestracja middleware w `bootstrap/app.php`
- **Zależności:** TASK-RAPI-001 ✅, TASK-RAPI-002 ✅
- **Utworzone:** 2025-01-27
- **Ukończone:** 2025-01-27
- **Dokumentacja:** `docs/issue/RAPIDAPI_INTEGRATION_PLAN.md`

---

#### `TASK-008` - Webhooks System (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 8-10 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja systemu webhooks dla billing/notifications (zgodnie z roadmap)
- **Szczegóły:** 
  - Projekt architektury webhooks
  - Implementacja endpointów webhook
  - System retry i error handling
  - Dokumentacja
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
- **Uwaga:** Zadanie z roadmap, niski priorytet

---

#### `TASK-009` - Admin UI (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 15-20 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja admin panel dla zarządzania treścią (Nova/Breeze) zgodnie z roadmap
- **Szczegóły:** 
  - Wybór narzędzia (Laravel Nova, Filament, Breeze)
  - Implementacja panelu admin
  - Zarządzanie movies, people, flags
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
- **Uwaga:** Zadanie z roadmap, niski priorytet

---

#### `TASK-010` - Analytics/Monitoring Dashboards (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 10-12 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja dashboardów dla analytics i monitoring (queue jobs, failed jobs, metrics)
- **Szczegóły:** 
  - Dashboard dla queue jobs status
  - Monitoring failed jobs
  - Analytics metrics (API usage, generation stats)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27
- **Uwaga:** Zadanie z roadmap, niski priorytet

---

#### `TASK-011` - Stworzenie CI dla staging (GHCR)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-16
- **Czas realizacji:** ~01h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Przygotowanie workflow GitHub Actions budującego obraz Docker dla środowiska staging i publikującego go do GitHub Container Registry.
- **Szczegóły:** Skonfigurować pipeline (trigger np. na push/tag `staging`), dodać logowanie do GHCR, poprawne tagowanie obrazu oraz wymagane sekrety.
- **Zakres wykonanych prac:**
  - ✅ Utworzono workflow `.github/workflows/staging.yml` z triggerami:
    - Push do brancha `staging`
    - Tagi `staging*`
    - Manual trigger (`workflow_dispatch`) z opcją force rebuild
  - ✅ Skonfigurowano logowanie do GHCR używając `GITHUB_TOKEN` (automatyczny token)
  - ✅ Zaimplementowano tagowanie obrazów:
    - `staging` - najnowszy obraz z brancha staging
    - `staging-<short-sha>` - obraz z konkretnym commitem (krótki hash)
    - `staging-<full-sha>` - obraz z konkretnym commitem (pełny hash)
  - ✅ Użyto Docker Buildx z cache (GitHub Actions cache) dla szybszych buildów
  - ✅ Zaimplementowano build stage `production` (używany dla staging i production)
  - ✅ Dodano output summary z informacjami o opublikowanych obrazach
  - ✅ Zaktualizowano dokumentację GHCR o workflow staging
- **Zależności:** Brak
- **Utworzone:** 2025-11-07
- **Ukończone:** 2025-12-16
- **Powiązane dokumenty:**
  - [`.github/workflows/staging.yml`](../../../.github/workflows/staging.yml)
  - [`docs/knowledge/reference/GITHUB_CONTAINER_REGISTRY.md`](../../knowledge/reference/GITHUB_CONTAINER_REGISTRY.md)

---

#### `TASK-013` - Konfiguracja dostępu do Horizon
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** 2025-12-14
- **Czas zakończenia:** 2025-12-14
- **Czas realizacji:** ~01h30m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Uporządkowanie reguł dostępu do panelu Horizon poza środowiskiem lokalnym.
- **Szczegóły:**
  - ✅ Przeniesienie listy autoryzowanych adresów e-mail do konfiguracji/ENV.
  - ✅ Dodanie testów/reguł zapobiegających przypadkowemu otwarciu panelu w produkcji.
  - ✅ Aktualizacja dokumentacji operacyjnej.
- **Zakres wykonanych prac:**
  - ✅ Zaktualizowano zmienne środowiskowe w `env/local.env.example`, `env/staging.env.example`, `env/production.env.example` z komentarzami bezpieczeństwa
  - ✅ Utworzono testy autoryzacji Horizon (`tests/Feature/HorizonAuthorizationTest.php`) - 11 testów, wszystkie przechodzą
  - ✅ Dodano zabezpieczenia bezpieczeństwa w `HorizonServiceProvider`:
    - Wymuszenie autoryzacji w produkcji nawet jeśli przypadkowo dodano `production` do `bypass_environments`
    - Wymaganie `HORIZON_ALLOWED_EMAILS` w produkcji
    - Logowanie ostrzeżeń i błędów dla nieprawidłowej konfiguracji
  - ✅ Zaktualizowano dokumentację (`docs/knowledge/tutorials/HORIZON_SETUP.md`) o szczegóły autoryzacji i best practices
- **Zależności:** Brak
- **Utworzone:** 2025-11-08
- **Ukończone:** 2025-12-14

---

#### `TASK-019` - Migracja produkcyjnego obrazu Docker na Distroless
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3-4 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Zastąpienie alpine’owego obrazu produkcyjnego wersją Distroless od Google w celu zmniejszenia powierzchni ataku.
- **Szczegóły:**
  - Wybrać odpowiednią bazę Distroless, która pozwoli uruchomić PHP-FPM, Nginx oraz Supervisora (build wieloetapowy).
  - Zmodyfikować etapy w `docker/php/Dockerfile`, aby kopiowały artefakty runtime do obrazu Distroless.
  - Zapewnić działanie Supervisora, Horizona oraz skryptów entrypoint bez powłoki (wektorowa forma `CMD`/`ENTRYPOINT`).
  - Zaktualizować dokumentację wdrożeniową (README, playbooki operacyjne) do nowego obrazu.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
---

#### `TASK-020` - Sprawdzić zachowanie AI dla nieistniejących filmów/osób
- **Status:** ❌ CANCELLED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** 2025-12-23
- **Czas realizacji:** --
- **Realizacja:** N/A
- **Opis:** Zweryfikować, co dzieje się podczas generowania opisów dla slugów, które nie reprezentują realnych filmów lub osób.
- **Powód anulowania:** Zadanie jest już w pełni pokryte przez inne zrealizowane zadania:
  - ✅ **TASK-037** - Weryfikacja istnienia przed AI (PreGenerationValidator, heurystyki walidacji)
  - ✅ **TASK-038** - Weryfikacja zgodności danych AI z slugiem (AiDataValidator)
  - ✅ **TASK-044** - Integracja TMDb API dla weryfikacji filmów (TmdbVerificationService)
  - ✅ **TASK-045** - Integracja TMDb API dla weryfikacji osób (TmdbVerificationService)
  - ✅ Testy regresyjne już istnieją (`MissingEntityGenerationTest`)
  - ✅ Dokumentacja już zaktualizowana (OpenAPI, README)
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
- **Anulowane:** 2025-12-23
---

#### `TASK-022` - Endpoint listy osób (List People)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-12-14 14:30:00
- **Czas zakończenia:** 2025-12-14 15:15:00
- **Czas realizacji:** 45 minut
- **Realizacja:** 🤖 AI Agent
- **Opis:** Dodanie endpointu `GET /api/v1/people` zwracającego listę osób w formacie analogicznym do listy filmów.
- **Szczegóły:**
  - ✅ Ujednolicić parametry filtrowania, sortowania i paginacji z endpointem `List movies`.
  - ✅ Zaimplementować kontroler, resource oraz testy feature dla nowego endpointu.
  - ✅ Zaktualizować dokumentację (OpenAPI, Postman, Insomnia) oraz przykłady odpowiedzi.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
- **Realizacja szczegóły:**
  - Endpoint `/api/v1/people` już istniał, dodano testy feature
  - Naprawiono kompatybilność z SQLite (LIKE zamiast ILIKE dla testów)
  - Dodano testy: `test_list_people_returns_ok`, `test_list_people_with_search_query`
  - Ujednolicono parametry z endpointem movies (oba używają `q` do wyszukiwania)
  - Dokumentacja OpenAPI już była zaktualizowana
  - Wszystkie testy przechodzą: 266 passed
---

#### `TASK-023` - Naprawa niespójnego wyszukiwania (case-insensitive) i dodanie testu wyszukiwania dla movies
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** 2025-12-16 15:45:00
- **Czas zakończenia:** 2025-12-16 16:00:00
- **Czas realizacji:** 15 minut
- **Realizacja:** 🤖 AI Agent
- **Opis:** Naprawa niespójnego zachowania wyszukiwania między SQLite (testy) a PostgreSQL (produkcja) oraz dodanie brakującego testu wyszukiwania dla endpointu movies.
- **Szczegóły:**
  - ✅ Zastąpiono `ILIKE`/`LIKE` przez `LOWER() LIKE LOWER()` w `MovieRepository::searchMovies()` i `PersonRepository::searchPeople()`
  - ✅ Zapewniono spójne case-insensitive wyszukiwanie w obu bazach danych (SQLite i PostgreSQL)
  - ✅ Dodano test `test_list_movies_with_search_query()` w `MoviesApiTest`
  - ✅ Dodano test `test_list_movies_search_is_case_insensitive()` do weryfikacji case-insensitive wyszukiwania
  - ✅ Wszystkie testy przechodzą: 268 passed
- **Zależności:** TASK-022
- **Utworzone:** 2025-12-16
- **Realizacja szczegóły:**
  - Zastąpiono `ILIKE`/`LIKE` przez `LOWER() LIKE LOWER()` w obu repozytoriach
  - Usunięto logikę wykrywania bazy danych (nie jest już potrzebna)
  - Dodano 2 nowe testy dla movies endpoint
  - Wszystkie testy przechodzą: 268 passed (2 nowe testy)
- **Uwagi:**
  - `LOWER() LIKE LOWER()` zapewnia spójne case-insensitive wyszukiwanie w obu bazach danych
  - Rozwiązanie jest bardziej niezawodne i czytelne niż poprzednie

---

#### `TASK-024` - Wdrożenie planu baseline locking z dokumentu AI_BASELINE_LOCKING_PLAN.md
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 4 godziny
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-16
- **Czas realizacji:** ~02h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Realizacja i dopracowanie działań opisanych w `docs/knowledge/technical/AI_BASELINE_LOCKING_PLAN.md`.
- **Szczegóły:**
  - ✅ Zweryfikowano konfigurację flagi `ai_generation_baseline_locking` - dodano komentarze do plików `.env.example` dla wszystkich środowisk
  - ✅ Testy jednostkowe już pokrywają oba tryby (flag on/off) - `GenerateMovieJobTest` i `GeneratePersonJobTest`
  - ✅ Dodano logowanie/metriki do monitorowania trybu baseline locking w jobach (`RealGenerateMovieJob`, `RealGeneratePersonJob`)
  - ✅ Przygotowano dokumentację roll-outową (`AI_BASELINE_LOCKING_ROLLOUT.md`) z planem wdrożenia i procedurą rollback
- **Zakres wykonanych prac:**
  - ✅ Dodano logowanie w `RealGenerateMovieJob` i `RealGeneratePersonJob` - logi informujące o aktywności baseline locking i wynikach operacji
  - ✅ Dodano komentarze do plików środowiskowych (`env/*.env.example`) z instrukcjami dotyczącymi flagi
  - ✅ Utworzono dokumentację roll-outową z planem wdrożenia (staging → production), metrykami do monitorowania i procedurą rollback
  - ✅ Wszystkie testy przechodzą (testy jednostkowe pokrywają oba tryby flagi)
- **Zależności:** TASK-012 ✅, TASK-023 ✅
- **Utworzone:** 2025-11-10
- **Ukończone:** 2025-12-16
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/AI_BASELINE_LOCKING_PLAN.md`](../../knowledge/technical/AI_BASELINE_LOCKING_PLAN.md)
  - [`docs/knowledge/technical/AI_BASELINE_LOCKING_ROLLOUT.md`](../../knowledge/technical/AI_BASELINE_LOCKING_ROLLOUT.md)

---

#### `TASK-025` - Standaryzacja flag produktowych i developerskich
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Zakończone:** 2025-12-16
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-16
- **Czas realizacji:** 00h30m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Uzupełnienie `docs/cursor-rules/pl/coding-standards.mdc` o zasady korzystania z dwóch typów feature flag (produktowe vs developerskie) oraz aktualizacja powiązanej dokumentacji.
- **Szczegóły:**
  - Dodano sekcję "Feature Flags" w `docs/cursor-rules/pl/coding-standards.mdc` z rozróżnieniem na flagi produktowe i developerskie.
  - Opisano lifecycle flag developerskich: tworzenie, testowanie, obowiązkowe usuwanie po wdrożeniu.
  - Zaktualizowano `docs/knowledge/reference/FEATURE_FLAGS.md` i `FEATURE_FLAGS.en.md` o typy flag i lifecycle.
  - Dodano przykłady konfiguracji i zasady nazewnictwa.
  - Dodano przypomnienia o feature flags w `.cursor/060-testing-policy.mdc` (testowanie) i `.cursor/020-task-protocol.mdc` (cleanup po taskach).
  - Utworzono regułę `.cursor/015-cursor-rules-cost-optimization.mdc` dla optymalizacji kosztów przy modyfikacji reguł.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-026` - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
- **Status:** ✅ COMPLETED (2025-12-16)
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-16
- **Czas realizacji:** ~1h
- **Realizacja:** 🤖 AI Agent
- **Opis:** Weryfikacja pól `confidence` oraz `confidence_level` zwracanych, gdy endpointy show automatycznie uruchamiają generowanie dla brakujących encji.
- **Szczegóły:**
  - ✅ Odtworzyć odpowiedź dla `GET /api/v1/movies/{slug}` oraz `GET /api/v1/people/{slug}` w scenariuszu braku encji i kolejki joba.
  - ✅ Zidentyfikować przyczynę wartości `confidence = null` i `confidence_level = unknown` w payloadzie oraz określić oczekiwane wartości.
  - ✅ Dodać testy regresyjne (feature/unit) zabezpieczające poprawione zachowanie oraz zaktualizować dokumentację API, jeśli kontrakt ulegnie zmianie.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
- **Zakres wykonanych prac:**
  - **Problem:** Kontrolery `MovieController::show()` i `PersonController::show()` nie przekazywały wartości `confidence` do akcji kolejkowania, co powodowało zwracanie `confidence = null` i `confidence_level = "unknown"` w odpowiedziach 202.
  - **Rozwiązanie:**
    - Naprawiono `MovieController::show()` - dodano przekazywanie `$validation['confidence']` do `queueMovieGenerationAction->handle()` w dwóch miejscach (gdy TMDb verification jest wyłączone i gdy jest włączone).
    - Naprawiono `PersonController::show()` - dodano przekazywanie `$validation['confidence']` do `queuePersonGenerationAction->handle()` w dwóch miejscach.
    - Naprawiono `MovieController::handleDisambiguationSelection()` - dodano ponowną walidację slug i przekazywanie `confidence`.
  - **Testy:**
    - Utworzono nowy plik testowy `ConfidenceFieldsTest.php` z 6 testami sprawdzającymi:
      - Obecność pól `confidence` i `confidence_level` w odpowiedziach 202
      - Poprawność typów danych (float dla confidence, string dla confidence_level)
      - Wartości nie są null/unknown dla poprawnych slugów
      - Zgodność confidence z walidacją slug
    - Zaktualizowano istniejące testy w `MissingEntityGenerationTest.php` - dodano asercje sprawdzające pola confidence.
  - **Dokumentacja:**
    - Zaktualizowano schemat OpenAPI `AcceptedGeneration` - dodano pola `confidence`, `confidence_level`, `locale` i `context_tag` z opisami.
- **Powiązane dokumenty:**
  - `api/app/Http/Controllers/Api/MovieController.php`
  - `api/app/Http/Controllers/Api/PersonController.php`
  - `api/tests/Feature/ConfidenceFieldsTest.php`
  - `api/tests/Feature/MissingEntityGenerationTest.php`
  - `api/public/docs/openapi.yaml`

---

#### `TASK-027` - Diagnostyka duplikacji eventów generowania (movies/people)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** 2025-11-10 18:03
- **Czas zakończenia:** 2025-11-30
- **Czas realizacji:** 20d01h22m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Ustalenie, dlaczego eventy generowania filmów i osób są wyzwalane wielokrotnie, prowadząc do powielania jobów/opisów.
- **Szczegóły:**
  - Odtworzyć problem w flow `GET /api/v1/movies/{slug}` oraz `GET /api/v1/people/{slug}` oraz podczas `POST /api/v1/generate`.
  - Przeanalizować miejsca emisji eventów i listenerów (kontrolery, serwisy, joby) pod kątem wielokrotnego dispatchu.
  - Zweryfikować liczbę wpisów w logach/kolejce i przygotować propozycję poprawek z testami regresyjnymi.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-034` - Tłumaczenie zasad Cursor (.mdc) i CLAUDE.md na angielski
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-11-12 17:30
- **Czas zakończenia:** 2025-11-12 18:30
- **Czas realizacji:** 01h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Przetłumaczenie wszystkich plików `.cursor/rules/*.mdc` i `CLAUDE.md` na angielski. Polskie wersje zostaną przeniesione do dokumentacji (`docs/`) i będą synchronizowane z wersjami angielskimi (cel: nauka języka angielskiego). Cursor/Claude będzie korzystać tylko z wersji angielskich.
- **Szczegóły:**
  - Przetłumaczyć wszystkie pliki `.cursor/rules/*.mdc` na angielski
  - Przetłumaczyć `CLAUDE.md` na angielski
  - Przenieść polskie wersje do `docs/cursor-rules/pl/` i `docs/CLAUDE.pl.md`
  - Zaktualizować strukturę tak, aby Cursor używał tylko wersji angielskich
  - Dodać instrukcje synchronizacji w dokumentacji
- **Zależności:** Brak
- **Utworzone:** 2025-11-12

---
#### `TASK-037` - Weryfikacja istnienia filmów/osób przed generowaniem AI
- **Status:** ✅ COMPLETED (Faza 1), ✅ COMPLETED (Faza 2), ✅ COMPLETED (Faza 3)
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** Faza 1: 4-6h (✅), Faza 2: 8-12h (✅), Faza 3: 20-30h (✅)
- **Czas rozpoczęcia:** 2025-12-01 (Faza 1), 2025-12-06 01:10 (Faza 2), 2025-12-06 01:30 (Faza 3)
- **Czas zakończenia:** 2025-12-01 (Faza 1), 2025-12-06 01:24 (Faza 2), 2025-12-14 (Faza 3 - finalizacja z TDD)
- **Czas realizacji:** ~5h (Faza 1), ~00h14m (Faza 2), ~00h47m (Faza 3 - feature flag + testy) + ~01h00m (finalizacja TDD)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja weryfikacji czy film/osoba faktycznie istnieje przed wywołaniem AI, przeciwdziałanie halucynacjom AI.
- **Szczegóły:**
  - **✅ Faza 1 (UKOŃCZONA):** Ulepszone prompty z instrukcją weryfikacji istnienia (AI zwraca `{"error": "Movie/Person not found"}` gdy nie istnieje), obsługa odpowiedzi z błędem w OpenAiClient i Jobach
  - **✅ Faza 2 (UKOŃCZONA):** Heurystyki walidacji przed generowaniem (PreGenerationValidator), aktywacja feature flag `hallucination_guard`, rozszerzone heurystyki (rok wydania, data urodzenia, podobieństwo slug, podejrzane wzorce)
  - **✅ Faza 3 (UKOŃCZONA):** Integracja z TMDb API zaimplementowana w TASK-044, TASK-045 i obecnym zadaniu:
    - ✅ Integracja z TMDb API (dla filmów i osób)
    - ✅ Cache wyników weryfikacji (TTL: 24h, Redis)
    - ✅ Rate limiting dla TMDb API
    - ✅ Fallback do AI jeśli TMDb niedostępny
    - ✅ Dedykowany feature flag `tmdb_verification` do włączania/wyłączania TMDb weryfikacji (togglable przez API)
    - ⏳ OMDb API fallback (opcjonalne, niski priorytet)
    - ⏳ Monitoring i dashboard (opcjonalne, długoterminowo)
- **Zakres wykonanych prac (Faza 2):**
  - ✅ Utworzono `PreGenerationValidator` service z heurystykami walidacji przed generowaniem
  - ✅ Zaimplementowano `shouldGenerateMovie()` i `shouldGeneratePerson()` z walidacją confidence, roku wydania, daty urodzenia i podejrzanych wzorców
  - ✅ Zintegrowano z `RealGenerateMovieJob` i `RealGeneratePersonJob` (walidacja przed wywołaniem AI)
  - ✅ Użyto feature flag `hallucination_guard` (już istniał)
  - ✅ Utworzono testy jednostkowe (11 testów) i feature (6 testów) - wszystkie przechodzą
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
  - ✅ Zaktualizowano dokumentację techniczną
  - ✅ **Finalizacja TDD (2025-12-14):** Dodano 11 dodatkowych testów edge cases (graniczne lata 1888, przyszłe lata, daty urodzenia, wzorce podejrzane), poprawiono walidację dat urodzenia dla przyszłych lat, wszystkie testy przechodzą (28 testów, 57 asercji)
- **Zakres wykonanych prac (Faza 3):**
  - ✅ Utworzono feature flag `tmdb_verification` do kontroli weryfikacji TMDb (togglable przez API)
  - ✅ Zintegrowano feature flag w `TmdbVerificationService` (sprawdzanie przed weryfikacją w `verifyMovie()`, `verifyPerson()`, `searchMovies()`)
  - ✅ Zaktualizowano kontrolery (`MovieController`, `PersonController`) - pozwalają na generowanie bez TMDb gdy flag wyłączony
  - ✅ Utworzono testy jednostkowe (4 testy dla feature flag'a) i feature (4 testy) - wszystkie przechodzą
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
  - ✅ Zaktualizowano dokumentację
  - ✅ **Finalizacja TDD (2025-12-14):** Dodano 11 dodatkowych testów edge cases dla `PreGenerationValidator` (graniczne lata, wzorce podejrzane, daty urodzenia), poprawiono walidację dat urodzenia dla przyszłych lat, wszystkie testy przechodzą (28 testów, 57 asercji)
- **Zależności:** Brak
- **Utworzone:** 2025-11-30
- **Ukończone (Faza 1):** 2025-12-01
- **Ukończone (Faza 2):** 2025-12-06
- **Ukończone (Faza 3):** 2025-12-14 (finalizacja z pełnym flow TDD: Red-Green-Refactor)
- **Powiązane dokumenty:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-038` - Weryfikacja zgodności danych AI z slugiem
- **Status:** ✅ COMPLETED (Faza 1), ✅ COMPLETED (Faza 2)
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** Faza 1: 3-4h (✅), Faza 2: 6-8h (✅)
- **Czas rozpoczęcia:** 2025-12-01 (Faza 1), 2025-12-14 (Faza 2)
- **Czas zakończenia:** 2025-12-01 (Faza 1), 2025-12-14 (Faza 2)
- **Czas realizacji:** ~4h (Faza 1), ~02h00m (Faza 2 - rozszerzone heurystyki + logowanie)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja walidacji czy dane wygenerowane przez AI faktycznie należą do filmu/osoby określonej przez slug, przeciwdziałanie niezgodnościom danych.
- **Szczegóły:**
  - **✅ Faza 1 (UKOŃCZONA):** Implementacja serwisu `AiDataValidator` z heurystykami walidacji, walidacja czy tytuł/imię pasuje do slug (Levenshtein + fuzzy matching), walidacja czy rok wydania/data urodzenia są rozsądne (1888-aktualny rok+2), odrzucanie danych jeśli niezgodność > threshold (0.6), integracja z Jobami (RealGenerateMovieJob, RealGeneratePersonJob) z feature flag `hallucination_guard`
  - **✅ Faza 2 (UKOŃCZONA):** Rozszerzone heurystyki (sprawdzanie czy reżyser pasuje do gatunku, geografia dla osób, spójność gatunków z rokiem), logowanie i monitoring podejrzanych przypadków (nawet gdy przeszły walidację - similarity 0.6-0.7), zaimplementowano walidację reżyser-gatunek, gatunek-rok, miejsce urodzenia-data urodzenia
- **Zakres wykonanych prac (Faza 2):**
  - ✅ Zaimplementowano walidację reżyser-gatunek (`validateDirectorGenreConsistency`) - sprawdza czy reżyser jest znany z gatunków zgodnych z podanymi
  - ✅ Zaimplementowano walidację gatunek-rok (`validateGenreYearConsistency`) - sprawdza czy gatunki są spójne z rokiem wydania (np. Cyberpunk nie może być przed 1980)
  - ✅ Zaimplementowano walidację miejsce urodzenia-data urodzenia (`validateBirthplaceBirthdateConsistency`) - sprawdza czy nazwa kraju jest odpowiednia dla daty (np. Czech Republic nie może być przed 1993)
  - ✅ Dodano logowanie podejrzanych przypadków (similarity 0.6-0.7) - loguje nawet gdy walidacja przeszła, dla monitoringu jakości
  - ✅ Utworzono bazę danych reżyserów i ich typowych gatunków (można rozszerzyć o lookup z bazy danych)
  - ✅ Utworzono bazę danych gatunków i ich er (kiedy gatunek się pojawił)
  - ✅ Utworzono bazę danych krajów i ich dat powstania (dla walidacji geograficznej)
  - ✅ Utworzono 7 dodatkowych testów jednostkowych dla rozszerzonych heurystyk - wszystkie przechodzą
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
  - ✅ Wszystkie testy przechodzą (243 testy, 861 asercji)
  - ✅ **Testy manualne:** Utworzono skrypty testowe (`api/tests/Manual/AiDataValidatorManualTest.php`, `api/tests/Manual/ApiValidationManualTest.php`), wszystkie 7 testów walidacji przeszły (reżyser-gatunek, gatunek-rok, miejsce-data, logowanie), logowanie działa poprawnie (znaleziono wpisy "Low similarity detected" w logach)
- **Zależności:** Brak (może być realizowane równolegle z TASK-037)
- **Utworzone:** 2025-11-30
- **Ukończone (Faza 1):** 2025-12-01
- **Ukończone (Faza 2):** 2025-12-14 (finalizacja z pełnym flow TDD: Red-Green-Refactor + testy manualne)
- **Powiązane dokumenty:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-040` - Analiza formatu TOON vs JSON dla komunikacji z AI
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Analiza formatu TOON (Token-Oriented Object Notation) jako alternatywy dla JSON w komunikacji z AI. TOON może oszczędzać 30-60% tokenów w porównaniu do JSON.
- **Szczegóły:**
  - Przeanalizować format TOON i jego zastosowanie w komunikacji z AI
  - Porównać TOON vs JSON pod kątem oszczędności tokenów
  - Ocenić przydatność TOON dla MovieMind API
  - Przygotować rekomendacje dotyczące użycia TOON w projekcie
- **Zależności:** Brak
- **Utworzone:** 2025-11-30
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/TOON_VS_JSON_ANALYSIS.md`](../../knowledge/technical/TOON_VS_JSON_ANALYSIS.md)
  - [`docs/knowledge/technical/TOON_VS_JSON_ANALYSIS.en.md`](../../knowledge/technical/TOON_VS_JSON_ANALYSIS.en.md)

---

#### `TASK-041` - Dodanie seriali i programów telewizyjnych (DDD approach)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski (Roadmap)
- **Szacowany czas:** 30-40 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja osobnych encji domenowych Series i TVShow zgodnie z Domain-Driven Design. Movie i Series/TV Show to różne koncepty domenowe - Movie nie ma odcinków, Series ma.
- **⚠️ UWAGA:** To zadanie jest **alternatywą dla TASK-051** (proste podejście). Obecnie realizujemy **TASK-051** jako naturalne rozszerzenie MVP. TASK-041 to opcja do rozważenia w przyszłości, gdy projekt urośnie i pojawi się potrzeba refaktoryzacji z wspólnymi abstrakcjami (interfejsy, traity). Zobacz: `docs/knowledge/DDD_VS_SIMPLE_APPROACH_EXPLANATION.md` dla szczegółowego porównania.
- **Szczegóły:**
  - Utworzenie modelu `Series` z tabelą `series`:
    - Pola: `title`, `slug`, `start_year`, `end_year`, `network`, `seasons`, `episodes`, `director`, `genres`, `default_description_id`
    - Relacje: `descriptions()`, `people()` (series_person), `genres()`
  - Utworzenie modelu `TVShow` z tabelą `tv_shows`:
    - Pola: `title`, `slug`, `start_year`, `end_year`, `network`, `format`, `episodes`, `runtime_per_episode`, `genres`, `default_description_id`
    - Relacje: `descriptions()`, `people()` (tv_show_person), `genres()`
  - Utworzenie wspólnych interfejsów/trait:
    - `DescribableContent` interface (dla descriptions)
    - `Sluggable` trait (dla slug generation/parsing)
    - `HasPeople` interface (dla relacji z Person)
  - Utworzenie `SeriesDescription` i `TVShowDescription` modeli (lub polimorficzna `ContentDescription`)
  - Utworzenie `SeriesRepository` i `TVShowRepository` (wspólna logika przez interfejsy)
  - Utworzenie `SeriesController` i `TVShowController` (wspólna logika przez interfejsy)
  - Utworzenie jobów: `RealGenerateSeriesJob`, `MockGenerateSeriesJob`, `RealGenerateTVShowJob`, `MockGenerateTVShowJob`
  - Aktualizacja `GenerateController` (obsługa SERIES, TV_SHOW)
  - Utworzenie enum `EntityType` (MOVIE, SERIES, TV_SHOW, PERSON)
  - Aktualizacja OpenAPI schema
  - Migracje dla tabel `series`, `tv_shows`, `series_person`, `tv_show_person`, `series_descriptions`, `tv_show_descriptions`
  - Testy (automatyczne i manualne)
  - Dokumentacja
- **Zależności:** Brak
- **Uwagi:** 
  - **Alternatywa dla TASK-051** - obecnie realizujemy TASK-051 (proste podejście)
  - **Do rozważenia w przyszłości** - gdy projekt urośnie i pojawi się potrzeba refaktoryzacji
  - **DDD approach** - wprowadza wspólne abstrakcje (interfejsy, traity), ale narusza granice agregatów (Shared Kernel)
  - Szczegóły porównania: `docs/knowledge/DDD_VS_SIMPLE_APPROACH_EXPLANATION.md`
- **Utworzone:** 2025-01-09
- **Zaktualizowane:** 2025-01-27 (zmiana priorytetu na 🟢 Niski - roadmap)
---

#### `TASK-042` - Analiza możliwych rozszerzeń typów i rodzajów
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 4-6 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Analiza i dokumentacja możliwych rozszerzeń systemu o nowe typy treści i rodzaje.
- **Szczegóły:**
  - Analiza obecnej struktury (Movie, Person, Series, TVShow)
  - Identyfikacja potencjalnych rozszerzeń (np. Documentaries, Short Films, Web Series, Podcasts, Books, Music Albums)
  - Analiza wpływu na API, bazę danych, joby
  - Analiza wspólnych interfejsów i możliwości refaktoryzacji
  - Dokumentacja rekomendacji i alternatyw
  - Utworzenie dokumentu w `docs/knowledge/technical/`
- **Zależności:** Brak
- **Utworzone:** 2025-01-09
---

#### `TASK-044` - Integracja TMDb API dla weryfikacji istnienia filmów przed generowaniem AI
- **Status:** ✅ COMPLETED (Wszystkie fazy ukończone)
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 8-12 godzin (Faza 1), 4-6 godzin (Faza 2), 6-8 godzin (Faza 3)
- **Czas rozpoczęcia:** 2025-12-01
- **Czas zakończenia:** 2025-12-03
- **Czas realizacji:** ~18h (Faza 1: ~10h, Faza 2: ~4h, Faza 3: ~4h)
- **Realizacja:** 🤖 AI Agent
- **Opis:** **KRYTYCZNY PROBLEM** - System zwraca 202 z job_id, ale job kończy się FAILED z NOT_FOUND nawet dla istniejących filmów (np. "bad-boys"). AI nie ma dostępu do zewnętrznych baz danych i weryfikuje tylko w swojej wiedzy z treningu, co powoduje fałszywe negatywy.
- **Szczegóły:**
  - **Problem:** AI zwraca "Movie not found" dla filmów które istnieją w rzeczywistości (np. "Bad Boys" z Williem Smithem)
  - **Przyczyna:** AI używa tylko wiedzy z treningu, nie ma dostępu do aktualnych baz danych filmowych
  - **Rozwiązanie:** Integracja z TMDb API do weryfikacji przed generowaniem przez AI
  - **Faza 1 (Krytyczna) - ✅ COMPLETED:**
    - ✅ Instalacja biblioteki `lukaszzychal/tmdb-client-php` (v1.0.2, kompatybilna z psr/http-message 2.0)
    - ✅ Utworzenie `TmdbVerificationService` z metodą `verifyMovie(string $slug): ?array`
    - ✅ Konfiguracja `TMDB_API_KEY` w `config/services.php` i `.env.example` (local, staging, production)
    - ✅ Integracja weryfikacji w `MovieController::show()` - sprawdź TMDb przed queue job
    - ✅ Jeśli nie znaleziono w TMDb → zwróć 404 od razu (zamiast 202)
    - ✅ Jeśli znaleziono → queue job z danymi z TMDb jako kontekst
    - ✅ Aktualizacja `RealGenerateMovieJob` i `MockGenerateMovieJob` - przekazanie danych z TMDb
    - ✅ Aktualizacja `OpenAiClient::generateMovie()` - użycie danych z TMDb w prompt (mniej halucynacji)
    - ✅ Aktualizacja `MovieGenerationRequested` Event - przekazanie `tmdbData`
    - ✅ Aktualizacja `QueueMovieGenerationAction` - przekazanie `tmdbData`
    - ✅ Testy jednostkowe: `TmdbVerificationServiceTest` (6 testów)
    - ✅ Testy feature: `MissingEntityGenerationTest` - zaktualizowane z mockowaniem TMDb
    - ✅ Cache wyników TMDb w Redis (TTL: 24h) - zaimplementowane w `TmdbVerificationService`
    - ✅ Obsługa błędów: NotFoundException, RateLimitException, TMDBException
    - ✅ Fallback do AI jeśli TMDb niedostępny (zwraca null, pozwala na fallback)
  - **Faza 2 (Optymalizacja) - ✅ COMPLETED:**
    - ✅ Cache wyników TMDb w Redis (TTL: 24h) - zaimplementowane w Fazie 1
    - ✅ Rate limiting dla TMDb API (40 requests per 10 seconds) - zaimplementowane w `checkRateLimit()`
    - ✅ Fallback do AI jeśli TMDb niedostępny - zaimplementowane w Fazie 1
    - ✅ Testy cache i rate limiting - `TmdbVerificationServiceTest` z testami rate limiting
  - **Faza 3 (Disambiguation) - ✅ COMPLETED:**
    - ✅ Metoda `searchMovies()` w `TmdbVerificationService` - zwraca wiele wyników
    - ✅ Disambiguation w `MovieController::show()` - zwraca 300 Multiple Choices z listą opcji
    - ✅ Wybór konkretnego filmu przez `tmdb_id` query parameter
    - ✅ Testy disambiguation - `MovieDisambiguationTest` (4 testy)
- **Zależności:** Brak
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/AI_MOVIE_VERIFICATION_PROBLEM.md`](../../knowledge/technical/AI_MOVIE_VERIFICATION_PROBLEM.md)
  - [`docs/knowledge/technical/TMDB_CLIENT_LIBRARY_EVALUATION.md`](../../knowledge/technical/TMDB_CLIENT_LIBRARY_EVALUATION.md)
  - [`docs/knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md`](../../knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md)
  - [`docs/knowledge/technical/AI_VERIFICATION_APPROACHES_COMPARISON.md`](../../knowledge/technical/AI_VERIFICATION_APPROACHES_COMPARISON.md)
  - [TMDb API Documentation](https://www.themoviedb.org/documentation/api)
- **Utworzone:** 2025-12-01
- **Ukończone:** 2025-12-03 (Wszystkie fazy)
---

#### `TASK-045` - Integracja TMDb API dla weryfikacji istnienia osób przed generowaniem AI
- **Status:** ✅ COMPLETED (Wszystkie fazy ukończone)
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 6-8 godzin (Faza 1), 3-4 godziny (Faza 2)
- **Czas rozpoczęcia:** 2025-12-03
- **Czas zakończenia:** 2025-12-03
- **Czas realizacji:** ~7h (Faza 1: ~6h, Faza 2: ~1h - cache już był zaimplementowany)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Rozszerzenie integracji TMDb o weryfikację osób (People) przed generowaniem biografii przez AI.
- **Szczegóły:**
  - **Faza 1 (Krytyczna) - ✅ COMPLETED:**
    - ✅ Rozszerzenie `TmdbVerificationService` o metodę `verifyPerson(string $slug): ?array` (już istniała)
    - ✅ Integracja weryfikacji w `PersonController::show()` - sprawdź TMDb przed queue job
    - ✅ Jeśli nie znaleziono w TMDb → zwróć 404 od razu
    - ✅ Jeśli znaleziono → queue job z danymi z TMDb jako kontekst
    - ✅ Aktualizacja `PersonGenerationRequested` Event - przekazanie `tmdbData`
    - ✅ Aktualizacja `QueuePersonGenerationAction` - przekazanie `tmdbData`
    - ✅ Aktualizacja `RealGeneratePersonJob` i `MockGeneratePersonJob` - przekazanie danych z TMDb
    - ✅ Aktualizacja `OpenAiClient::generatePerson()` - użycie danych z TMDb w prompt
    - ✅ Testy feature: `MissingEntityGenerationTest` - zaktualizowane z mockowaniem TMDb dla osób
  - **Faza 2 (Optymalizacja) - ✅ COMPLETED:**
    - ✅ Cache wyników TMDb dla osób (TTL: 24h) - już zaimplementowane w `TmdbVerificationService`
    - ✅ Testy cache dla osób - cache działa automatycznie dla wszystkich typów
- **Zależności:** TASK-044 (Faza 1) - dla spójności implementacji
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md`](../../knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md)
  - [`docs/knowledge/technical/AI_VERIFICATION_APPROACHES_COMPARISON.md`](../../knowledge/technical/AI_VERIFICATION_APPROACHES_COMPARISON.md)
- **Utworzone:** 2025-12-03
- **Ukończone:** 2025-12-03
---

#### `TASK-046` - Integracja TMDb API dla weryfikacji istnienia seriali i TV Shows przed generowaniem AI
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 8-10 godzin (Faza 1), 3-4 godziny (Faza 2)
- **Czas rozpoczęcia:** 2025-01-27
- **Czas zakończenia:** 2025-01-27
- **Czas realizacji:** ~04h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Rozszerzenie integracji TMDb o weryfikację seriali i TV Shows przed generowaniem przez AI.
- **Szczegóły:**
  - **Faza 1 (Podstawowa) - ✅ COMPLETED:**
    - ✅ Metody `verifyTvSeries()` i `verifyTvShow()` już istniały w `TmdbVerificationService`
    - ✅ Utworzono `TmdbTvSeriesCreationService` i `TmdbTvShowCreationService` dla tworzenia encji z danych TMDb
    - ✅ Zintegrowano weryfikację TMDb w `TvSeriesRetrievalService` i `TvShowRetrievalService` (analogicznie do `MovieRetrievalService`)
    - ✅ Zaktualizowano `QueueTvSeriesGenerationAction` i `QueueTvShowGenerationAction` o `confidence_level` w odpowiedziach
    - ✅ Dodano testy jednostkowe dla serwisów retrieval (6 testów dla TV Series, 6 testów dla TV Shows)
    - ✅ Dodano testy feature dla weryfikacji TMDb (6 testów w `MissingEntityGenerationTest`)
    - ✅ Zaktualizowano `FakeEntityVerificationService` o obsługę TV Series i TV Shows
    - ✅ Zaktualizowano `FakeOpenAiClient` o metody `generateTvSeries()` i `generateTvShow()`
  - **Faza 2 (Optymalizacja) - ✅ COMPLETED:**
    - ✅ Cache dla TV Series i TV Shows już był zaimplementowany w `TmdbVerificationService` (TTL: 24h)
    - ✅ Dodano stałe `CACHE_PREFIX_TV_SERIES` i `CACHE_PREFIX_TV_SHOW` dla spójności z Movies i People
    - ✅ Cache działa automatycznie dla wszystkich metod weryfikacji (verifyTvSeries, verifyTvShow, searchTvSeries, searchTvShows)
- **Zakres wykonanych prac:**
  - ✅ Utworzono `TmdbTvSeriesCreationService` - tworzenie TV Series z danych TMDb
  - ✅ Utworzono `TmdbTvShowCreationService` - tworzenie TV Shows z danych TMDb
  - ✅ Zaktualizowano `TvSeriesRetrievalService` - dodana weryfikacja TMDb (exact match, search, disambiguation)
  - ✅ Zaktualizowano `TvShowRetrievalService` - dodana weryfikacja TMDb (exact match, search, disambiguation)
  - ✅ Zaktualizowano `QueueTvSeriesGenerationAction` - dodano `confidence_level` i metodę `confidenceLabel()`
  - ✅ Zaktualizowano `QueueTvShowGenerationAction` - dodano `confidence_level` i metodę `confidenceLabel()`
  - ✅ Zaktualizowano `FakeEntityVerificationService` - dodano metody dla TV Series i TV Shows
  - ✅ Zaktualizowano `FakeOpenAiClient` - dodano metody `generateTvSeries()` i `generateTvShow()`
  - ✅ Dodano testy jednostkowe (12 testów) i feature (6 testów) - wszystkie przechodzą
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
  - ✅ Wszystkie testy przechodzą: 654 passed (2855 assertions)
- **Zależności:** TASK-051 ✅ (dodanie seriali/TV Shows), TASK-044 ✅ (Faza 1), TASK-045 ✅ (Faza 1)
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md`](../../knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md)
- **Utworzone:** 2025-12-03
- **Ukończone:** 2025-01-27
---

#### `TASK-047` - Refaktoryzacja do wspólnego serwisu weryfikacji
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 4-6 godzin
- **Czas rozpoczęcia:** 2025-12-03
- **Czas zakończenia:** 2025-12-03
- **Czas realizacji:** ~2h
- **Realizacja:** 🤖 AI Agent
- **Opis:** Refaktoryzacja `TmdbVerificationService` do wspólnego interfejsu dla wszystkich typów encji.
- **Szczegóły:**
  - ✅ Utworzenie interfejsu `EntityVerificationServiceInterface` z metodami dla wszystkich typów
  - ✅ Refaktoryzacja `TmdbVerificationService` do implementacji interfejsu
  - ✅ Aktualizacja `MovieController` i `PersonController` - użycie interfejsu zamiast konkretnej klasy
  - ✅ Rejestracja binding w `AppServiceProvider` - `EntityVerificationServiceInterface` → `TmdbVerificationService`
  - ✅ Testy refaktoryzacji - wszystkie testy przechodzą
- **Zależności:** TASK-044 (Faza 1), TASK-045 (Faza 1)
- **Utworzone:** 2025-12-03
- **Ukończone:** 2025-12-03
---

#### `TASK-028` - Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 0.5-1 godzina
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Sprawdzić, czy mechanizm synchronizacji `docs/issue/TASKS.md` → GitHub Issues obsługuje dodawanie tagów w issue odzwierciedlających priorytet zadań.
- **Szczegóły:**
  - Zweryfikować aktualny workflow synchronizacji pod kątem przekazywania informacji o priorytecie.
  - Ustalić mapowanie priorytetów (`🔴/🟡/🟢`) na tagi/etykiety w GitHub Issues.
  - Przygotować propozycję zmian (jeśli potrzebne) wraz z dokumentacją procesu.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-029` - Uporządkowanie testów według wzorca AAA lub GWT
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Przeanalizować i ustandaryzować styl testów, wybierając pomiędzy wzorcami Arrange-Act-Assert (AAA) oraz Given-When-Then (GWT).
- **Szczegóły:**
  - Zebrać materiał referencyjny dotyczący AAA i GWT (zalety, wady, przykłady w kontekście PHP/Laravel).
  - Przygotować opracowanie porównujące oba podejścia wraz z rekomendacją dla MovieMind API.
  - Opracować plan refaktoryzacji istniejących testów (kolejność plików, zakres).
  - Zaktualizować wytyczne dotyczące testów (PL/EN) i dodać dokumentację, jeśli będzie to zasadne.
  - Rozważyć zastosowanie techniki „trzech linii” (Given/When/Then w formie metod pomocniczych) jako wariantu rekomendowanego wzorca.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-030` - Opracowanie dokumentu o technice testów „trzech linii”
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Zebrać informacje i przygotować dokument (tutorial/reference) opisujący technikę testów, w której główny test składa się z trzech wywołań metod pomocniczych (Given/When/Then).
- **Szczegóły:**
  - Zgromadzić źródła (artykuły, przykłady w PHP/Laravel) dotyczące „three-line tests” / „three-act tests”.
  - Przygotować dokument w `docs/knowledge/tutorials/` (PL/EN), zawierający opis, przykłady kodu, korzyści i ograniczenia.
  - Zaproponować konwencje nazewnicze metod (`given*`, `when*`, `then*`) oraz wskazówki integracji z PHPUnit.
  - Powiązać dokument z zadaniem `TASK-029` i podlinkować w guideline testów po akceptacji.
- **Zależności:** `TASK-029`
- **Utworzone:** 2025-11-10

---

#### `TASK-031` - Kierunek rozwoju wersjonowania opisów AI
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Uporządkowanie wniosku, czy utrzymujemy aktualne podejście (pojedynczy opis na kombinację `locale + context_tag`) czy planujemy pełne wersjonowanie wszystkich generacji.
- **Szczegóły:**
  - Zsyntetyzować ustalenia z rozmowy (2025-11-10) i kodu (`RealGenerate*Job::persistDescription` – upsert po `(movie_id, locale, context_tag)`).
  - Opisać konsekwencje obecnej rekomendacji (najnowszy wpis per wariant) oraz potencjalny plan migracji do wersjonowania historii (np. kolumna `version`/`generated_at`, cleanup, zmiany w API i cache).
  - Przygotować notatkę lub szkic ADR dokumentując aktualną decyzję i warunki ewentualnej przyszłej zmiany.
- **Zależności:** Powiązane z `TASK-012`, `TASK-024`
- **Utworzone:** 2025-11-10

---

#### `TASK-032` - Automatyczne tworzenie obsady przy generowaniu filmu
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** 2025-12-23
- **Czas zakończenia:** 2025-12-23
- **Czas realizacji:** ~03h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Zapewnić, że endpoint `GET /api/v1/movies/{slug}` zwraca podstawową obsadę (imię/nazwisko/rola) także dla świeżo wygenerowanych filmów poprzez automatyczne tworzenie rekordów `Person` i powiązań `movie_person`.
- **Szczegóły:**
  - ✅ Rozszerzono `OpenAiClient` o zwracanie `cast` w odpowiedzi AI (schema, prompty)
  - ✅ Rozszerzono `RealGenerateMovieJob` o logikę tworzenia `Person` i relacji `movie_person` z danych AI
  - ✅ Zaimplementowano de-duplikację (znajdowanie istniejących osób po nazwie, case-insensitive)
  - ✅ Dodano obsługę wszystkich ról: DIRECTOR, ACTOR, WRITER, PRODUCER
  - ✅ Dodano obsługę `character_name` i `billing_order` dla ACTOR
  - ✅ Utworzono testy feature (`MovieCastAutoCreationTest`) - 4 testy, wszystkie przechodzą
  - ✅ Zaktualizowano `FakeOpenAiClient` o obsługę `cast` w odpowiedziach
- **Zakres wykonanych prac:**
  - ✅ Rozszerzono `OpenAiClient::generateMovie()` o zwracanie `cast` w odpowiedzi
  - ✅ Zaktualizowano schema odpowiedzi AI o `cast` array z rolami, character_name, billing_order
  - ✅ Zaimplementowano `RealGenerateMovieJob::createCastAndCrew()` - tworzenie Person i relacji
  - ✅ Zaimplementowano `RealGenerateMovieJob::findOrCreatePerson()` - de-duplikacja osób
  - ✅ Dodano tworzenie cast również w `refreshExistingMovie()` dla istniejących filmów
  - ✅ Utworzono testy: `test_movie_generation_creates_director_person`, `test_movie_generation_creates_actors`, `test_movie_generation_handles_existing_person`, `test_movie_generation_creates_director_and_actors`
  - ✅ Wszystkie testy przechodzą: 596 passed
- **Zależności:** TASK-022 ✅ (lista osób)
- **Utworzone:** 2025-11-10
- **Ukończone:** 2025-12-23

---

#### `TASK-033` - Usunięcie modelu Actor i konsolidacja na Person
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-12-23
- **Czas zakończenia:** 2025-12-23
- **Czas realizacji:** ~01h30m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Wyeliminowanie legacy modelu `Actor` na rzecz ujednoliconego `Person`, tak aby cała obsada korzystała z jednej tabeli i relacji `movie_person`.
- **Szczegóły:**
  - ✅ Usunięto modele `Actor` i `ActorBio` (już nieużywane w kodzie produkcyjnym)
  - ✅ Zaktualizowano `GenerateRequest` - `ACTOR` jest teraz deprecated, automatycznie konwertowany na `PERSON` (backward compatibility)
  - ✅ Zaktualizowano `GenerateController` - nadal obsługuje `ACTOR` jako alias dla `PERSON`
  - ✅ Zaktualizowano dokumentację: README.md (actors/actor_bios → people/person_bios), OpenAPI (usunięto tag "Actors", zaktualizowano entity_type)
  - ✅ `ActorSeeder` już używa `Person` i `PersonBio` (nie wymaga zmian)
  - ✅ Wszystkie testy przechodzą: 596 passed
- **Zakres wykonanych prac:**
  - ✅ Usunięto `api/app/Models/Actor.php`
  - ✅ Usunięto `api/app/Models/ActorBio.php`
  - ✅ Zaktualizowano `GenerateRequest::prepareForValidation()` - konwersja `ACTOR` → `PERSON` z logowaniem
  - ✅ Zaktualizowano `GenerateRequest::rules()` - `ACTOR` nadal akceptowany (deprecated)
  - ✅ Zaktualizowano `README.md` - actors/actor_bios → people/person_bios, entity_type ACTOR → PERSON
  - ✅ Zaktualizowano `docs/openapi.yaml` - usunięto tag "Actors", zaktualizowano opis entity_type
  - ✅ Zaktualizowano `api/public/docs/openapi.yaml` - usunięto tag "Actors", zaktualizowano schema GenerateRequest
  - ✅ Wszystkie testy przechodzą: 596 passed
- **Zależności:** TASK-032 ✅, TASK-022 ✅
- **Utworzone:** 2025-11-10
- **Ukończone:** 2025-12-23
- **Uwaga:** Migracje `actors` i `actor_bios` pozostają w bazie danych (nie są używane, ale nie są usuwane dla bezpieczeństwa danych historycznych)

---

### 🔄 IN_PROGRESS

#### `TASK-039` - Integracja i naprawa połączenia z OpenAI
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** 2025-11-10 14:00
- **Czas zakończenia:** 2025-12-01
- **Czas realizacji:** ~20d (włączając TASK-037, TASK-038, TASK-039)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Integracja i naprawa połączenia z OpenAI.
- **Szczegóły:**
  - ✅ Diagnoza błędów komunikacji (timeouty, odpowiedzi HTTP, limity) - naprawione
  - ✅ Weryfikacja konfiguracji kluczy (`OPENAI_API_KEY`, endpointy, modele) - zweryfikowane i działające
  - ✅ Aktualizacja serwisów i fallbacków obsługujących OpenAI w API - zaktualizowane (OpenAiClient)
  - ✅ Przygotowanie testów (unit/feature) potwierdzających poprawną integrację - wszystkie testy przechodzą (15 passed)
  - ✅ Naprawa błędów JSON Schema (usunięcie oneOf, poprawa schematów)
  - ✅ Przetestowanie manualnie z AI_SERVICE=real - działa poprawnie
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
- **Ukończone:** 2025-12-01

---

### `TASK-007` - Feature Flags Hardening
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-11-10 10:36
- **Czas zakończenia:** 2025-11-10 11:08
- **Czas realizacji:** 00h32m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Centralizacja konfiguracji flag i dodanie dokumentacji oraz admin endpoints do toggle flags
- **Szczegóły:** 
  - Centralizacja flags config (`config/pennant.php`)
  - Dodanie dokumentacji feature flags
  - Rozszerzenie admin endpoints o toggle flags (guarded)
- **Zakres wykonanych prac:**
  - Wprowadzono `BaseFeature` oraz aktualizację wszystkich klas w `app/Features/*` do odczytu wartości z konfiguracji.
  - Dodano nowy plik `config/pennant.php` z metadanymi (kategorie, domyślne wartości, `togglable`) oraz zabezpieczenia toggle w `FlagController`.
  - Rozszerzono testy (`AdminFlagsTest`), dokumentację API (OpenAPI, Postman) i przygotowano wpis referencyjny `docs/knowledge/reference/FEATURE_FLAGS*.md`.
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

### `TASK-002` - Weryfikacja Queue Workers i Horizon
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-11-09 13:40
- **Czas zakończenia:** 2025-11-09 15:05
- **Czas realizacji:** 01h25m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Weryfikacja i utwardzenie konfiguracji Horizon oraz queue workers.
- **Szczegóły:**
  - Zrównano timeouty i liczbę prób workerów Horizon (`config/horizon.php`, nowe zmienne `.env`).
  - Wprowadzono konfigurowalną listę e-maili i środowisk z automatycznym dostępem do panelu Horizon.
  - Zaktualizowano dokumentację (`docs/tasks/HORIZON_QUEUE_WORKERS_VERIFICATION.md`, `docs/knowledge/tutorials/HORIZON_SETUP.md`) wraz z checklistą uruchomienia Redis/Horizon.
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-015` - Automatyczne testy Newman w CI
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Integracja kolekcji Postman z pipeline CI poprzez uruchamianie Newman.
- **Szczegóły:**
  - Dodanie kroku w `.github/workflows/ci.yml` uruchamiającego testy API.
  - Przygotowanie odpowiednich environmentów/sekretów do CI.
  - Raportowanie wyników (CLI/JUnit) i dokumentacja.
- **Zależności:** Wymaga aktualnych szablonów environmentów Postman.
- **Utworzone:** 2025-11-08

---

#### `TASK-051` - Implementacja TV Series i TV Show
- **Status:** ⏳ PENDING
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 30-40 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja obsługi seriali telewizyjnych (TV Series) i programów telewizyjnych (TV Show) jako nowych typów encji w MovieMind API.
- **Szczegóły:**
  - **TV Series (seriale fabularne):**
    - Dodanie modelu `TvSeries` i `TvSeriesDescription` (analogicznie do Movie)
    - Migracje bazy danych dla tabel `tv_series` i `tv_series_descriptions`
    - Endpointy API: `GET /v1/tv-series`, `GET /v1/tv-series/{slug}`, `POST /v1/generate` (entity_type: TV_SERIES)
  - **TV Show (programy telewizyjne):**
    - Dodanie modelu `TvShow` i `TvShowDescription` (analogicznie do Movie)
    - Migracje bazy danych dla tabel `tv_shows` i `tv_show_descriptions`
    - Endpointy API: `GET /v1/tv-shows`, `GET /v1/tv-shows/{slug}`, `POST /v1/generate` (entity_type: TV_SHOW)
  - Integracja z TMDb API dla weryfikacji i pobierania danych (endpoint `/tv`)
  - Logika rozróżniania między serialami a programami na podstawie gatunków TMDb
  - Generowanie AI-opisów dla obu typów (analogicznie do filmów)
  - Testy jednostkowe i feature tests dla obu typów
  - Aktualizacja OpenAPI spec
  - Dokumentacja
- **Zależności:** 
  - TASK-015 (testy Newman) - zalecane przed rozpoczęciem
  - Stabilizacja funkcji MOVIE i PERSON
- **Uwagi:** 
  - **TV Series** = seriale telewizyjne (produkcje fabularne z sezonami/odcinkami)
  - **TV Show** = programy telewizyjne (talk-show, reality, news, dokumenty)
  - Oba modele powinny być implementowane razem dla spójności (podobna struktura, ten sam endpoint TMDb)
  - Naturalne rozszerzenie MVP po stabilizacji filmów i osób
  - Szczegóły propozycji: `docs/knowledge/ENTITY_TYPES_PROPOSALS.md`
- **Utworzone:** 2025-01-27

---


---

#### `TASK-050` - Dodanie Basic Auth dla endpointów admin
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴🔴🔴 Najwyższy
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-16
- **Czas realizacji:** ~02h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** **KRYTYCZNY PROBLEM BEZPIECZEŃSTWA** - Endpointy `/api/v1/admin/*` są obecnie publiczne i niechronione. Każdy może przełączać flagi feature, co stanowi poważne zagrożenie bezpieczeństwa.
- **Szczegóły:**
  - ✅ Utworzenie middleware `AdminBasicAuth` (analogicznie do `HorizonBasicAuth`)
  - ✅ Konfiguracja zmiennych środowiskowych: `ADMIN_ALLOWED_EMAILS`, `ADMIN_BASIC_AUTH_PASSWORD`, `ADMIN_AUTH_BYPASS_ENVS`
  - ✅ Dodanie middleware do route'ów `/api/v1/admin/*`
  - ✅ Możliwość bypassu w środowiskach local/staging
  - ✅ Wymuszenie autoryzacji w produkcji (nawet jeśli przypadkowo dodano do bypass)
  - ✅ Utworzenie testów autoryzacji dla endpointów admin (13 testów, wszystkie przechodzą)
  - ✅ Aktualizacja dokumentacji operacyjnej i plików `.env.example`
- **Zakres wykonanych prac:**
  - ✅ Utworzono `app/Http/Middleware/AdminBasicAuth.php` z logowaniem prób dostępu
  - ✅ Zarejestrowano middleware w `bootstrap/app.php` jako `admin.basic`
  - ✅ Dodano middleware do route'ów admin w `routes/api.php`
  - ✅ Zaktualizowano pliki `.env.example` (local, staging, production) z komentarzami bezpieczeństwa
  - ✅ Utworzono testy autoryzacji (`tests/Feature/AdminBasicAuthTest.php`) - 13 testów, wszystkie przechodzą
  - ✅ Zaktualizowano `AdminFlagsTest.php` - dodano bypass autoryzacji w setUp() dla testów funkcjonalności
  - ✅ Utworzono dokumentację (`docs/knowledge/tutorials/ADMIN_API_BASIC_AUTH.md`)
  - ✅ Wszystkie testy przechodzą: 281 passed (965 assertions)
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
- **Zależności:** Brak
- **Utworzone:** 2025-12-16
- **Ukończone:** 2025-12-16
- **Powiązane dokumenty:**
  - [`docs/knowledge/tutorials/ADMIN_API_BASIC_AUTH.md`](../../knowledge/tutorials/ADMIN_API_BASIC_AUTH.md)
  - [`docs/knowledge/tutorials/HORIZON_SETUP.md`](../../knowledge/tutorials/HORIZON_SETUP.md) (podobna implementacja)

---

## ✅ **Zakończone Zadania**

### `TASK-052` - Sugerowanie alternatywnych slugów przy błędzie "not found"
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3-4 godziny
- **Czas rozpoczęcia:** 2025-12-16
- **Czas zakończenia:** 2025-12-23
- **Czas realizacji:** ~07d (implementacja w trakcie innych zadań)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Gdy AI zwraca błąd "Movie not found" lub "Person not found", system powinien wyszukać w TMDb możliwe pasujące filmy/osoby i zwrócić listę sugerowanych slugów w odpowiedzi błędu.
- **Szczegóły:**
  - ✅ Rozszerzono `JobErrorFormatter` o możliwość dodania `suggested_slugs` do błędu typu `NOT_FOUND`
  - ✅ W `RealGenerateMovieJob` - gdy AI zwraca "not found" i nie ma TMDb data, wyszukuje w TMDb możliwe filmy i generuje slugi
  - ✅ W `RealGeneratePersonJob` - analogicznie dla osób
  - ✅ Każdy sugerowany slug zawiera: `slug`, `title`/`name`, `release_year` (dla filmów), `director` (dla filmów), `tmdb_id`
  - ✅ Odpowiedź błędu zawiera pole `suggested_slugs` z listą możliwych opcji
- **Zakres wykonanych prac:**
  - ✅ Zaimplementowano `JobErrorFormatter::formatError()` z obsługą `suggested_slugs` (linia 33-34)
  - ✅ Zaimplementowano `RealGenerateMovieJob::findSuggestedSlugs()` (linia 1311)
  - ✅ Zaimplementowano `RealGeneratePersonJob::findSuggestedSlugs()` (linia 769)
  - ✅ Zintegrowano sugerowanie slugów w obu jobach przy błędach "not found"
  - ✅ Każdy sugerowany slug zawiera wszystkie wymagane pola (slug, title/name, release_year, director, tmdb_id)
- **Zależności:** Brak
- **Utworzone:** 2025-12-16
- **Ukończone:** 2025-12-23
- **Uwaga:** Poprawia UX - użytkownik dostaje sugestie zamiast tylko błędu

---

### `TASK-049` - Weryfikacja naprawy problemu phpstan-fixer z Laravel package:discover
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 4-6 godzin
- **Czas rozpoczęcia:** 2025-12-14
- **Czas zakończenia:** 2025-12-14
- **Czas realizacji:** ~04h00m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Weryfikacja naprawy problemu `Call to a member function make() on null` podczas `package:discover` w Laravel po aktualizacji `phpstan-fixer` do v1.2.2.
- **Szczegóły:**
  - ✅ Zaktualizowano `phpstan-fixer` do v1.2.2
  - ✅ Zweryfikowano, że `dont-discover` jest poprawnie ustawione jako tablica `[]`
  - ✅ Problem nadal występuje - błąd `Call to a member function make() on null` podczas `package:discover` i testów Feature
  - ✅ Utworzono workaround: `scripts/build-package-manifest.php` - bezpośredni builder manifestu bez kontenera Laravel
  - ✅ Workaround działa dla `composer install/update`, ale nie rozwiązuje problemu w testach Feature
  - ✅ Dodano instrukcje odtworzenia błędu do issue #60 w repo phpstan-fixer
  - ✅ Utworzono branch `test/phpstan-fixer-issue-60` dla przyszłych testów
  - ✅ Usunięto `phpstan-fixer` z `require-dev` w głównym kodzie (tymczasowe rozwiązanie)
  - ✅ Przywrócono standardowy PHPStan w pre-commit hook
- **Zakres wykonanych prac:**
  - ✅ Zaktualizowano `phpstan-fixer` do v1.2.2
  - ✅ Zweryfikowano konfigurację `dont-discover` (poprawne jako tablica `[]`)
  - ✅ Zidentyfikowano, że problem nie jest związany z `dont-discover`, ale z inicjalizacją kontenera Laravel
  - ✅ Utworzono workaround: `scripts/build-package-manifest.php`
  - ✅ Zaktualizowano `scripts/package-discover-wrapper` aby używał bezpośredniego buildera
  - ✅ Dodano instrukcje odtworzenia błędu do issue #60
  - ✅ Utworzono branch testowy `test/phpstan-fixer-issue-60`
  - ✅ Usunięto `phpstan-fixer` z `require-dev` w main (tymczasowe rozwiązanie)
  - ✅ Przywrócono standardowy PHPStan w pre-commit hook
  - ✅ Utworzono dokumentację techniczną: `PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md`, `PHPSTAN_FIXER_REPRODUCTION_STEPS.md`
- **Obserwacje:**
  - Problem występuje zarówno w runtime (`php artisan package:discover`), jak i w testach Feature
  - `PackageManifest` może być budowany bez kontenera Laravel (przetestowano)
  - Problem jest w `PackageDiscoverCommand`, który wymaga kontenera podczas `Command::run()`
  - Workaround działa dla `composer install/update`, ale nie rozwiązuje problemu w testach
  - Tymczasowe rozwiązanie: usunięcie `phpstan-fixer` z `require-dev` do czasu naprawy w bibliotece
- **Zależności:** Brak
- **Utworzone:** 2025-12-14
- **Ukończone:** 2025-12-14
- **Issue:** https://github.com/lukaszzychal/phpstan-fixer/issues/60
- **Dokumentacja:**
  - [`docs/knowledge/technical/PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md`](../../knowledge/technical/PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md)
  - [`docs/knowledge/technical/PHPSTAN_FIXER_REPRODUCTION_STEPS.md`](../../knowledge/technical/PHPSTAN_FIXER_REPRODUCTION_STEPS.md)
  - [`docs/knowledge/technical/PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md`](../../knowledge/technical/PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md)
  - [`docs/knowledge/technical/PHPSTAN_FIXER_LARAVEL_ISSUE_PROPOSAL.md`](../../knowledge/technical/PHPSTAN_FIXER_LARAVEL_ISSUE_PROPOSAL.md)

---

### `TASK-048` - Kompleksowa dokumentacja bezpieczeństwa aplikacji (OWASP, AI security, audyty)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 4-6 godzin
- **Czas rozpoczęcia:** 2025-01-10
- **Czas zakończenia:** 2025-12-06 01:01
- **Czas realizacji:** ~05h00m (weryfikacja kompletności i finalizacja)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Utworzenie kompleksowego dokumentu o bezpieczeństwie aplikacji obejmującego OWASP Top 10, OWASP LLM Top 10, procedury audytów bezpieczeństwa (wyrywkowe i całościowe), CI/CD pipeline dla bezpieczeństwa, oraz best practices.
- **Szczegóły:**
  - Utworzenie dokumentu `APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md` (PL i EN)
  - Mapowanie OWASP Top 10 na obecną implementację
  - Mapowanie OWASP LLM Top 10 na AI security w aplikacji
  - Dokumentacja audytów bezpieczeństwa (wyrywkowe i całościowe)
  - Częstotliwość audytów (kwartalne, półroczne, pre-release, post-incident)
  - Rozważenie CI/CD pipeline dla bezpieczeństwa
  - Best practices i procedury
  - Zarządzanie incydentami bezpieczeństwa
  - Dodanie zasad bezpieczeństwa do `.cursor/rules/security-awareness.mdc`
  - Aktualizacja `SECURITY.md` z nowymi informacjami
  - Osobny pipeline dla bezpieczeństwa (`.github/workflows/security-pipeline.yml`)
- **Zakres wykonanych prac:**
  - ✅ Utworzono kompleksowy dokument bezpieczeństwa w wersji PL i EN (871 linii)
  - ✅ Zmapowano OWASP Top 10 na obecną implementację MovieMind API
  - ✅ Zmapowano OWASP LLM Top 10 na AI security w aplikacji
  - ✅ Udokumentowano procedury audytów bezpieczeństwa (wyrywkowe i całościowe)
  - ✅ Określono częstotliwość audytów (kwartalne, półroczne, pre-release, post-incident)
  - ✅ Udokumentowano CI/CD pipeline dla bezpieczeństwa
  - ✅ Dodano zasady bezpieczeństwa do `.cursor/rules/security-awareness.mdc` (406 linii)
  - ✅ Zaktualizowano `SECURITY.md` z linkami do kompleksowej dokumentacji
  - ✅ Zweryfikowano istnienie security pipeline workflow (`.github/workflows/security-pipeline.yml`)
  - ✅ Wszystkie wymagane elementy zadania zostały zrealizowane
- **Zależności:** Brak
- **Utworzone:** 2025-01-10
- **Ukończone:** 2025-12-06
- **Dokumentacja:** 
  - [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](../../knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md)
  - [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.en.md`](../../knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.en.md)
  - [`.cursor/rules/security-awareness.mdc`](../../../.cursor/rules/security-awareness.mdc)
  - [`SECURITY.md`](../../../SECURITY.md)
  - [`.github/workflows/security-pipeline.yml`](../../../.github/workflows/security-pipeline.yml)

---

### `TASK-043` - Implementacja zasady wykrywania BREAKING CHANGE
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** 2025-12-06 01:06
- **Czas zakończenia:** 2025-12-06 01:07
- **Czas realizacji:** 00h01m (weryfikacja kompletności istniejącego pliku)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Dodanie zasady do cursor/rules wymagającej analizy BREAKING CHANGE przed wprowadzeniem zmian. Zasada wymaga traktowania zmian jakby były na produkcji z pełnymi danymi.
- **Szczegóły:**
  - Utworzenie `.cursor/rules/breaking-change-detection.mdc`
  - Zasada: traktować zmiany jakby były na produkcji z pełnymi danymi
  - Wymaganie analizy skutków zmian przed wprowadzeniem (data impact, API impact, functionality impact)
  - Analiza alternatyw i bezpiecznego procesu zmiany (migracje, backward compatibility, etc.)
  - Proces: STOP → analiza → dokumentacja → alternatywy → bezpieczny proces → approval
- **Zakres wykonanych prac:**
  - ✅ Plik `.cursor/rules/breaking-change-detection.mdc` istnieje i jest kompletny
  - ✅ Zawiera zasadę traktowania zmian jak na produkcji z pełnymi danymi
  - ✅ Zawiera wymaganie analizy skutków zmian (data, API, functionality, migration impact)
  - ✅ Zawiera analizę alternatyw i bezpieczny proces zmiany
  - ✅ Zawiera workflow: STOP → analiza → dokumentacja → alternatywy → bezpieczny proces → approval
  - ✅ Zawiera przykłady breaking changes i wyjątki
  - ✅ Zawiera wymagania egzekwowania dla AI Agent
- **Zależności:** Brak
- **Utworzone:** 2025-01-09
- **Ukończone:** 2025-12-06
- **Dokumentacja:** 
  - [`.cursor/rules/breaking-change-detection.mdc`](../../../.cursor/rules/breaking-change-detection.mdc)

---

### `TASK-021` - Naprawa duplikacji eventów przy generowaniu filmu
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** 2025-11-10 16:05
- **Czas zakończenia:** 2025-11-10 18:30
- **Czas realizacji:** 02h25m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Zidentyfikowanie i usunięcie przyczyny wielokrotnego uruchamiania jobów generujących opisy filmów oraz duplikowania opisów w bazie dla endpointu `GET /api/v1/movies/{movieSlug}`.
- **Szczegóły:**
  - Reprodukcja błędu i analiza źródeł eventów (kontroler, listener, job).
  - Poprawa logiki wyzwalania eventów/jobs tak, aby każdy opis powstawał tylko raz.
  - Dodanie testów regresyjnych (unit/feature) zabezpieczających przed ponownym duplikowaniem.
  - Weryfikacja skutków ubocznych (np. kolejka Horizon, zapisy w bazie) i aktualizacja dokumentacji jeśli potrzebna.
- **Zakres wykonanych prac:**
  - Wymuszenie utrzymania żądanego sluga przy tworzeniu encji i powiązanych opisów/bio.
  - Obsługa parametrów `locale` i `context_tag` w akcjach, eventach, JobStatusService oraz jobach generujących.
  - Dodanie mechanizmu upsertu opisów/bio per `locale`+`context_tag` oraz rozszerzenie testów feature/unit (Generate API, MissingEntity, job listeners) potwierdzających brak duplikacji i poprawne przekazywanie parametrów.

### `TASK-021` - Refaktoryzacja FlagController
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1 godzina
- **Czas rozpoczęcia:** 2025-11-10 13:09
- **Czas zakończenia:** 2025-11-10 13:13
- **Czas realizacji:** 00h04m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Refaktoryzacja `FlagController` w celu uproszczenia logiki i poprawy czytelności.
- **Zakres wykonanych prac:**
  - Dodano serwisy `FeatureFlagManager` oraz `FeatureFlagUsageScanner` i wykorzystano je w kontrolerze.
  - Wyodrębniono walidację do `SetFlagRequest`.
  - Uzupełniono dokumentację o opis nowych komponentów.

### `TASK-006` - Ulepszenie Postman Collection
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** 2025-11-10 09:37
- **Czas zakończenia:** 2025-11-10 09:51
- **Czas realizacji:** 00h14m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Dodanie przykładów odpowiedzi i testów per request oraz environment templates dla local/staging.
- **Zakres wykonanych prac:**
  - Rozszerzono testy kolekcji o weryfikację `description_id`/`bio_id`, dodano zmienne kolekcji i żądania typu `selected`.
  - Zaktualizowano przykładowe odpowiedzi oraz sekcję jobów, podbijając wersję kolekcji do `1.2.0`.
  - Uzupełniono dokumentację (`docs/postman/README.md`, `docs/postman/README.en.md`) o obsługę wariantów opisów i nowych zmiennych.

### `TASK-014` - Usprawnienie linków HATEOAS dla filmów
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** 2025-11-09 12:45
- **Czas zakończenia:** 2025-11-09 13:25
- **Czas realizacji:** 00h40m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Korekta linków HATEOAS zwracanych przez `HateoasService`, aby odpowiadały dokumentacji i relacjom.
- **Szczegóły:**
  - Posortowano linki osób wg `billing_order` w `HateoasService`.
  - Zaktualizowano przykłady HATEOAS w kolekcji Postman oraz dokumentacji serwerowej (PL/EN).
  - Rozszerzono testy feature `HateoasTest` o weryfikację struktury `_links.people`.
- **Zależności:** Brak
- **Utworzone:** 2025-11-08

### `TASK-012` - Lock + Multi-Description Handling przy generowaniu
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 4-5 godzin
- **Czas rozpoczęcia:** 2025-11-10 08:37
- **Czas zakończenia:** 2025-11-10 09:06
- **Czas realizacji:** 00h29m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Wprowadzenie blokady zapobiegającej wyścigom podczas równoległej generacji oraz pełna obsługa wielu opisów/bio na entity.
- **Szczegóły:**
  - Dodano blokady Redis oraz kontrolę baseline (`description_id` / `bio_id`) w jobach, aby tylko pierwszy zakończony job aktualizował domyślny opis, a kolejne zapisywały alternatywy.
  - Rozszerzono odpowiedzi `POST /api/v1/generate` o pola `existing_id`, `description_id`/`bio_id` oraz pokryto zmianę testami jednostkowymi i feature.
  - Endpointy `GET /api/v1/movies/{slug}` i `/api/v1/people/{slug}` otrzymały parametry `description_id`/`bio_id`, izolację cache per wariant oraz zaktualizowaną dokumentację.
- **Zależności:** Wymaga działających kolejek i storage opisów.
- **Utworzone:** 2025-11-08

### `TASK-000` - People - List Endpoint z Filtrowaniem po Role
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Zakończone:** 2025-01-27
- **Czas rozpoczęcia:** (uzupełnić)
- **Czas zakończenia:** (uzupełnić)
- **Czas realizacji:** (różnica, jeśli możliwe)
- **Realizacja:** (np. 👨‍💻 Manualna / 🤖 AI Agent / ⚙️ Hybrydowa)
- **Opis:** Dodanie endpointu GET /api/v1/people z filtrowaniem po role (ACTOR, DIRECTOR, etc.)
- **Szczegóły:** Implementacja w `PersonController::index()`, `PersonRepository::searchPeople()`

---

### `TASK-001` - Refaktoryzacja Kontrolerów API (SOLID)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Zakończone:** 2025-11-07
- **Czas rozpoczęcia:** 2025-11-07 21:45
- **Czas zakończenia:** 2025-11-07 22:30
- **Czas realizacji:** 00h45m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Refaktoryzacja kontrolerów API zgodnie z zasadami SOLID i dobrymi praktykami Laravel
- **Szczegóły:** [docs/issue/REFACTOR_CONTROLLERS_SOLID.md](./REFACTOR_CONTROLLERS_SOLID.md)
- **Zakres wykonanych prac:** Nowe Resources (`MovieResource`, `PersonResource`), `MovieDisambiguationService`, refaktoryzacja kontrolerów (`Movie`, `Person`, `Generate`, `Jobs`), testy jednostkowe i aktualizacja dokumentacji.

---

### `TASK-003` - Implementacja Redis Caching dla Endpointów
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Zakończone:** 2025-11-08
- **Czas rozpoczęcia:** 2025-11-08
- **Czas zakończenia:** 2025-11-08
- **Czas realizacji:** 00h25m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Dodanie cache'owania odpowiedzi dla `GET /api/v1/movies/{slug}` oraz `GET /api/v1/people/{slug}` z invalidacją po zakończeniu jobów.
- **Szczegóły:** Aktualizacja kontrolerów, jobów generujących treści oraz testów feature (`MoviesApiTest`, `PeopleApiTest`). Wprowadzenie TTL i czyszczenia cache przy zapisach.

---

### `TASK-004` - Aktualizacja README.md (Symfony → Laravel)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟢 Niski
- **Zakończone:** 2025-11-08
- **Czas rozpoczęcia:** 2025-11-08
- **Czas zakończenia:** 2025-11-08
- **Czas realizacji:** 00h10m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Odświeżenie głównych README (PL/EN) po migracji na Laravel 12, aktualizacja kroków Quick Start i poleceń testowych.
- **Szczegóły:** Nowe badże, instrukcje `docker compose`, `php artisan test`, doprecyzowanie roli Horizona.

---

### `TASK-005` - Weryfikacja i Aktualizacja OpenAPI Spec
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Zakończone:** 2025-11-08
- **Czas rozpoczęcia:** 2025-11-08
- **Czas zakończenia:** 2025-11-08
- **Czas realizacji:** 00h45m (auto)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Urealnienie specyfikacji `docs/openapi.yaml` i dodanie linków w `api/README.md`.
- **Szczegóły:** Dodane przykłady odpowiedzi, rozszerzone schematy (joby, flagi, generation), dopasowane statusy 200/202/400/404. Link w `api/README.md` do OpenAPI i Swagger UI.

---

### `TASK-016` - Auto-fix błędów PHPStan
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Zakończone:** 2025-11-08 20:10
- **Czas rozpoczęcia:** 2025-11-08 19:55
- **Czas zakończenia:** 2025-11-08 20:10
- **Czas realizacji:** 00h15m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Wdrożenie komendy `phpstan:auto-fix`, która analizuje logi PHPStan i automatycznie proponuje/wykonuje poprawki kodu.
- **Szczegóły:**
  - Dodano moduł `App\Support\PhpstanFixer` z parserem logów, serwisem oraz początkowymi strategiami napraw (`UndefinedPivotPropertyFixer`, `MissingParamDocblockFixer`).
  - Komenda wspiera tryby `suggest` oraz `apply`, opcjonalnie przyjmuje wcześniej wygenerowany log i raportuje wynik w formie tabeli.
  - Pokryto rozwiązanie testami jednostkowymi i feature z wykorzystaniem fixture JSON.
- **Dokumentacja:** [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.md), [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md)

---

### `TASK-017` - Rozszerzenie fixera PHPStan o dodatkowe strategie
- **Status:** ✅ COMPLETED
- **Priorytet:** 🟡 Średni
- **Zakończone:** 2025-11-08 20:55
- **Czas rozpoczęcia:** 2025-11-08 20:20
- **Czas zakończenia:** 2025-11-08 20:55
- **Czas realizacji:** 00h35m
- **Realizacja:** 🤖 AI Agent
- **Opis:** Rozbudowa modułu `PhpstanFixer` o kolejne strategie auto-poprawek oraz aktualizacja dokumentacji.
- **Szczegóły:**
  - Dodano fixery: `MissingReturnDocblockFixer`, `MissingPropertyDocblockFixer`, `CollectionGenericDocblockFixer`.
  - Zaktualizowano komendę `phpstan:auto-fix` i DI (`AppServiceProvider`), przygotowano rozszerzone fixture JSON i testy.
  - Uporządkowano dokumentację zadania (`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX*.md`) i checklistę rozszerzeń.
- **Dokumentacja:** [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.md), [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md)

---
## 📚 **Szablony**

### **Szablon dla nowego zadania:**

```markdown
#### `TASK-XXX` - Tytuł Zadania
- **Status:** ⏳ PENDING
- **Priorytet:** 🔴 Wysoki / 🟡 Średni / 🟢 Niski
- **Szacowany czas:** X godzin
- **Opis:** Krótki opis zadania
- **Szczegóły:** [link do szczegółowego opisu](./PLIK.md) lub bezpośredni opis
- **Zależności:** TASK-XXX (jeśli wymagane)
- **Utworzone:** YYYY-MM-DD
- **Czas rozpoczęcia:** YYYY-MM-DD HH:MM
- **Czas zakończenia:** -- (uzupełnij po zakończeniu)
- **Czas realizacji:** -- (format HHhMMm; wpisz `AUTO` tylko gdy agent policzy)
- **Realizacja:** 🤖 AI Agent / 👨‍💻 Manualna / ⚙️ Hybrydowa
```

---

## 🔄 **Jak używać z AI Agentem**

### **Dla AI Agenta:**
1. Przeczytaj plik `TASKS.md`
2. Znajdź zadanie ze statusem `⏳ PENDING`
3. Zmień status na `🔄 IN_PROGRESS`
4. Przeczytaj szczegóły zadania (jeśli dostępne)
5. Wykonaj zadanie
6. Po zakończeniu zmień status na `✅ COMPLETED`
7. Przenieś zadanie do sekcji "Zakończone Zadania"
8. Zaktualizuj datę "Ostatnia aktualizacja"

### **Dla użytkownika:**
1. Dodaj nowe zadanie do sekcji "Aktywne Zadania" (PENDING)
2. Użyj szablonu powyżej
3. Jeśli potrzebujesz szczegółowego opisu, stwórz plik w `docs/issue/` i podaj link
4. Agent AI automatycznie znajdzie i wykona zadanie

---

## 📊 **Statystyki**

- **Aktywne:** 23 (19 + 4 RapidAPI tasks - 3 completed)
- **Zakończone:** 31 (28 + 3 RapidAPI tasks)
- **Anulowane:** 1
- **W trakcie:** 0

---

**Ostatnia aktualizacja:** 2025-01-27

