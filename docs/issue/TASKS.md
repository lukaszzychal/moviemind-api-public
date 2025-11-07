# 📋 Backlog Zadań - MovieMind API

**Ostatnia aktualizacja:** 2025-11-07  
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

### ⏳ PENDING

#### `TASK-001` - Refaktoryzacja Kontrolerów API (SOLID)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 6-8 godzin
- **Czas rozpoczęcia:** 2025-11-07 21:45
- **Czas zakończenia:** 2025-11-07 22:30
- **Czas realizacji:** AUTO (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Refaktoryzacja kontrolerów API zgodnie z zasadami SOLID i dobrymi praktykami Laravel
- **Szczegóły:** [docs/issue/REFACTOR_CONTROLLERS_SOLID.md](./REFACTOR_CONTROLLERS_SOLID.md)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

**Podzadania:**
- [x] Faza 1: Utworzenie Services i Resources
  - [x] `JobStatusService` - eliminacja duplikacji cache
  - [x] `PersonResource` - konsystencja z MovieResource
  - [x] `MovieDisambiguationService` - wydzielenie logiki disambiguation
- [x] Faza 2: Utworzenie Actions
  - [x] `QueueMovieGenerationAction`
  - [x] `QueuePersonGenerationAction`
- [x] Faza 3: Refaktoryzacja Kontrolerów
  - [x] `MovieController::show()`
  - [x] `PersonController`
  - [x] `GenerateController`
  - [x] `JobsController`
- [x] Faza 4: Testy
  - [x] Testy dla nowych Services
  - [x] Testy dla nowych Actions
  - [x] Testy dla zrefaktoryzowanych kontrolerów
- [x] Faza 5: Dokumentacja
  - [x] Aktualizacja dokumentacji API
  - [x] Aktualizacja README

---

#### `TASK-002` - Weryfikacja Queue Workers i Horizon
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Weryfikacja konfiguracji i działania queue workers/Horizon (jobs obecnie działają, ale wymagają weryfikacji)
- **Szczegóły:** Sprawdzić konfigurację Horizon, działanie workers w produkcji, monitoring
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-003` - Implementacja Redis Caching dla Endpointów
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3-4 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Dodanie response caching dla GET movie/person show oraz cache invalidation po zakończeniu generacji
- **Szczegóły:** 
  - Wprowadzenie cache dla `MovieController::show()` i `PersonController::show()`
  - Cache invalidation po zakończeniu generacji (job completion)
  - Strategia cache keys i TTL
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-004` - Aktualizacja README.md (Symfony → Laravel)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 1 godzina
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Poprawa README.md - zmiana stack info z Symfony na Laravel oraz dodanie instrukcji lokalnego uruchomienia
- **Szczegóły:** 
  - Aktualizacja sekcji tech stack
  - Dodanie instrukcji dla Laravel app (`api/`), Horizon, Redis, Postgres
  - Weryfikacja zgodności z aktualną architekturą
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-005` - Weryfikacja i Aktualizacja OpenAPI Spec
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Weryfikacja kompletności `docs/openapi.yaml` oraz dodanie linków do OpenAPI w README.md
- **Szczegóły:** 
  - Weryfikacja wszystkich endpointów w OpenAPI
  - Dodanie przykładów request/response
  - Linkowanie OpenAPI w root `README.md` i `api/README.md`
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-006` - Ulepszenie Postman Collection
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Dodanie przykładów odpowiedzi i testów per request oraz environment templates dla local/staging
- **Szczegóły:** 
  - Dodanie example responses dla każdego request
  - Dodanie testów automatycznych w Postman
  - Utworzenie environment templates (local, staging)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

---

#### `TASK-007` - Feature Flags Hardening
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Centralizacja konfiguracji flag i dodanie dokumentacji oraz admin endpoints do toggle flags
- **Szczegóły:** 
  - Centralizacja flags config (`config/pennant.php`)
  - Dodanie dokumentacji feature flags
  - Rozszerzenie admin endpoints o toggle flags (guarded)
- **Zależności:** Brak
- **Utworzone:** 2025-01-27

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
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Przygotowanie workflow GitHub Actions budującego obraz Docker dla środowiska staging i publikującego go do GitHub Container Registry.
- **Szczegóły:** Skonfigurować pipeline (trigger np. na push/tag `staging`), dodać logowanie do GHCR, poprawne tagowanie obrazu oraz wymagane sekrety.
- **Zależności:** Brak
- **Utworzone:** 2025-11-07

---

## ✅ **Zakończone Zadania**

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

- **Aktywne:** 10
- **Zakończone:** 1
- **Anulowane:** 0
- **W trakcie:** 0

---

**Ostatnia aktualizacja:** 2025-11-07

