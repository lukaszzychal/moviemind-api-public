# 📋 Backlog Zadań - MovieMind API

**Ostatnia aktualizacja:** 2025-12-06  
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

2. **`TASK-048`** - Kompleksowa dokumentacja bezpieczeństwa aplikacji (OWASP, AI security, audyty)
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
   - **Status:** ⏳ PENDING (Faza 1 ✅ COMPLETED)

6. **`TASK-013`** - Konfiguracja dostępu do Horizon
   - **Dlaczego:** Bezpieczeństwo - zabezpiecza panel Horizon w produkcji
   - **Czas:** 1-2h
   - **Status:** ⏳ PENDING

#### Faza 2: Usprawnienia funkcjonalne (🟡 Średni Priorytet)

4. **`TASK-022`** - Endpoint listy osób (List People)
   - **Dlaczego:** Parzystość API - uzupełnia podstawowe endpointy
   - **Czas:** 2-3h
   - **Status:** ⏳ PENDING

5. **`TASK-024`** - Wdrożenie planu baseline locking
   - **Dlaczego:** Stabilizuje mechanizm generowania, zapobiega race conditions
   - **Czas:** 4h
   - **Status:** ⏳ PENDING
   - **Zależności:** TASK-012 ✅, TASK-023 ✅

6. **`TASK-025`** - Standaryzacja flag produktowych i developerskich
   - **Dlaczego:** Uporządkowanie zarządzania flagami, wspiera rozwój
   - **Czas:** 1h
   - **Status:** ⏳ PENDING

7. **`TASK-026`** - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
   - **Dlaczego:** Poprawa UX - użytkownik widzi poziom pewności generacji
   - **Czas:** 1-2h
   - **Status:** ⏳ PENDING

#### Faza 3: Infrastruktura i CI/CD (🟡 Średni Priorytet)

8. **`TASK-011`** - Stworzenie CI dla staging (GHCR)
   - **Dlaczego:** Automatyzacja deploymentu, szybsze iteracje
   - **Czas:** 3h
   - **Status:** ⏳ PENDING

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

1. **`TASK-013`** - Konfiguracja dostępu do Horizon (bezpieczeństwo)
2. **`TASK-022`** - Endpoint listy osób (podstawowa funkcjonalność)
3. **`TASK-025`** - Standaryzacja flag (uproszczenie zarządzania)

**Uwaga:** Większość zadań POC jest już zrealizowana (TASK-001, TASK-002, TASK-003, TASK-012, TASK-023 ✅). POC jest praktycznie gotowy.

---

### 📋 Podsumowanie według Priorytetów

#### 🔴 Wysoki Priorytet (Krytyczne)
- `TASK-037` (Faza 2-3) - Weryfikacja istnienia przed AI
- `TASK-038` (Faza 2) - Weryfikacja zgodności danych

#### 🟡 Średni Priorytet (Ważne)
- `TASK-013` - Konfiguracja Horizon
- `TASK-022` - Lista osób
- `TASK-024` - Baseline locking
- `TASK-025` - Standaryzacja flag
- `TASK-026` - Pola zaufania
- `TASK-011` - CI dla staging
- `TASK-015` - Testy Newman
- `TASK-019` - Docker Distroless
- `TASK-032` - Automatyczna obsada
- `TASK-033` - Usunięcie Actor
- `TASK-028` - Synchronizacja Issues
- `TASK-029` - Standaryzacja testów
- `TASK-031` - Wersjonowanie opisów
- `TASK-040` - Analiza TOON vs JSON
- `TASK-049` - Weryfikacja naprawy phpstan-fixer
- `TASK-050` - Aktualizacja do maksymalnych wersji PHP i Laravel

#### 🟢 Niski Priorytet (Roadmap)
- `TASK-008` - Webhooks System
- `TASK-009` - Admin UI
- `TASK-010` - Analytics/Monitoring Dashboards
- `TASK-030` - Dokumentacja testów "trzech linii"

---

### ⏳ PENDING

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

#### `TASK-013` - Konfiguracja dostępu do Horizon
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Uporządkowanie reguł dostępu do panelu Horizon poza środowiskiem lokalnym.
- **Szczegóły:**
  - Przeniesienie listy autoryzowanych adresów e-mail do konfiguracji/ENV.
  - Dodanie testów/reguł zapobiegających przypadkowemu otwarciu panelu w produkcji.
  - Aktualizacja dokumentacji operacyjnej.
- **Zależności:** Brak
- **Utworzone:** 2025-11-08

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
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Zweryfikować, co dzieje się podczas generowania opisów dla slugów, które nie reprezentują realnych filmów lub osób.
- **Szczegóły:**
  - Przeanalizować obecne joby generujące (`RealGenerateMovieJob`, `RealGeneratePersonJob`) pod kątem tworzenia fikcyjnych encji.
  - Zaproponować/zaimplementować scenariusz zabezpieczający (np. flaga konfiguracyjna, walidacja źródłowa, dodatkowe logowanie).
  - Przygotować testy regresyjne i aktualizację dokumentacji (OpenAPI, README) opisującą zachowanie.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
---

#### `TASK-022` - Endpoint listy osób (List People)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Dodanie endpointu `GET /api/v1/people` zwracającego listę osób w formacie analogicznym do listy filmów.
- **Szczegóły:**
  - Ujednolicić parametry filtrowania, sortowania i paginacji z endpointem `List movies`.
  - Zaimplementować kontroler, resource oraz testy feature dla nowego endpointu.
  - Zaktualizować dokumentację (OpenAPI, Postman, Insomnia) oraz przykłady odpowiedzi.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10
---

#### `TASK-024` - Wdrożenie planu baseline locking z dokumentu AI_BASELINE_LOCKING_PLAN.md
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 4 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Realizacja i dopracowanie działań opisanych w `docs/knowledge/technical/AI_BASELINE_LOCKING_PLAN.md`.
- **Szczegóły:**
  - Zweryfikować konfigurację flagi `ai_generation_baseline_locking` na stagingu/produkcji i przygotować procedurę rollout.
  - Uzułnić testy (Mock/Real jobs) o warianty z aktywną flagą oraz przypadki związane z cache i slugami.
  - Dodać metryki/logi do monitorowania trybu baseline locking w Horizon.
  - Przygotować decyzję rolloutową oraz ewentualny rollback.
- **Zależności:** TASK-012, TASK-023
- **Utworzone:** 2025-11-10

---

#### `TASK-025` - Standaryzacja flag produktowych i developerskich
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1 godzina
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Uzupełnienie `.cursor/rules/coding-standards.mdc` o zasady korzystania z dwóch typów feature flag (produktowe vs developerskie) oraz aktualizacja powiązanej dokumentacji.
- **Szczegóły:**
  - Zdefiniować w sekcji flag rozróżnienie na flagi produktowe (długoterminowe włączanie/wyłączanie funkcji) i flagi developerskie (tymczasowe, domyślnie wyłączone do czasu zakończenia prac).
  - Opisać lifecycle flag developerskich: tworzenie wraz z rozpoczęciem funkcji, testowanie po ręcznym włączeniu, obowiązkowe usuwanie po wdrożeniu.
  - Dodać wskazówki kiedy stosować flagi developerskie (każda nowa lub ryzykowna funkcja zaburzająca stabilność) oraz zasady nazewnictwa i dokumentacji.
  - Zsynchronizować wiedzę w `docs/knowledge/reference/FEATURE_FLAGS*.md` (jeśli wymaga uzupełnienia) i upewnić się, że instrukcje są spójne PL/EN.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-026` - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Weryfikacja pól `confidence` oraz `confidence_level` zwracanych, gdy endpointy show automatycznie uruchamiają generowanie dla brakujących encji.
- **Szczegóły:**
  - Odtworzyć odpowiedź dla `GET /api/v1/movies/{slug}` oraz `GET /api/v1/people/{slug}` w scenariuszu braku encji i kolejki joba.
  - Zidentyfikować przyczynę wartości `confidence = null` i `confidence_level = unknown` w payloadzie oraz określić oczekiwane wartości.
  - Dodać testy regresyjne (feature/unit) zabezpieczające poprawione zachowanie oraz zaktualizować dokumentację API, jeśli kontrakt ulegnie zmianie.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

#### `TASK-049` - Weryfikacja naprawy problemu phpstan-fixer z Laravel package:discover
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Sprawdzenie, czy problem z `package:discover` w Laravel został rozwiązany w bibliotece `phpstan-fixer` (issue #60, #63). Jeśli tak, przetestowanie poprawki i usunięcie workaround (wrapper script).
- **Szczegóły:**
  - Sprawdzić status issue #60 i #63 w repozytorium `lukaszzychal/phpstan-fixer`:
    - Issue #60: https://github.com/lukaszzychal/phpstan-fixer/issues/60 (zamknięte, ale fix niepełny)
    - Issue #63: https://github.com/lukaszzychal/phpstan-fixer/issues/63 (nowe - `dont-discover` powinno być tablicą, nie boolean)
  - Jeśli problem został rozwiązany (zmieniono `"dont-discover": true` na `"dont-discover": []`):
    - Zaktualizować pakiet do najnowszej wersji
    - Przetestować, czy `composer install` i `composer update` działają bez błędów
    - Przetestować, czy `php artisan package:discover` działa poprawnie
    - Przetestować, czy testy Feature przechodzą bez błędów
    - Usunąć wrapper scripts (`scripts/package-discover-wrapper`, `scripts/artisan-wrapper`) jeśli nie są już potrzebne
    - Zaktualizować wszystkie miejsca używające wrapperów na bezpośrednie użycie komend
    - Zaktualizować dokumentację (workflow.mdc, pre-commit hook, CI workflow)
    - Uruchomić testy i upewnić się, że wszystko działa
  - Jeśli problem nie został rozwiązany:
    - Zaktualizować issue #63 z informacją o statusie
    - Pozostawić wrapper scripts jako workaround
- **Zależności:** Brak
- **Utworzone:** 2025-12-06
- **Zaktualizowane:** 2025-12-14
- **Powiązane issue:**
  - Issue #60: https://github.com/lukaszzychal/phpstan-fixer/issues/60 (zamknięte, ale fix niepełny)
  - Issue #63: https://github.com/lukaszzychal/phpstan-fixer/issues/63 (nowe - `dont-discover` powinno być tablicą)
- **Obserwacje:**
  - **Problem z testami:** Testy Feature nie przechodzą z powodu błędu `Call to a member function make() on null` w `vendor/laravel/framework/src/Illuminate/Console/Command.php:175`
  - **Przyczyna:** Błąd występuje podczas `package:discover` w Laravel, gdy próbuje przetworzyć pakiet `phpstan-fixer` podczas uruchamiania testów
  - **Nowy problem:** W wersji v1.2.1 `"dont-discover": true` (boolean) zamiast `"dont-discover": []` (array), co powoduje błąd `array_merge(): Argument #2 must be of type array, true given` w `PackageManifest.php:135`
  - **Workaround:** Testy zostały oznaczone jako `skip` z informacją o błędzie i linkiem do issue #60
  - **Status testów:** Wszystkie testy Feature nie przechodzą z powodu błędu `package:discover` podczas inicjalizacji Laravel
  - **Dodatkowe informacje:** Błąd nie wpływa na działanie aplikacji w runtime, tylko na uruchamianie testów Feature, które wymagają pełnej inicjalizacji Laravel (w tym `package:discover`)
  - **Workaround w CI:** `package:discover` został przywrócony w `post-autoload-dump` w `composer.json` i używa `scripts/package-discover-wrapper`. Komendy `php artisan` w CI używają `scripts/artisan-wrapper`.
  - **Aktualna wersja:** v1.2.1 (zaktualizowana 2025-12-14)

---

#### `TASK-050` - Aktualizacja projektu do maksymalnych wersji PHP i Laravel
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 4-8 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Zaktualizowanie projektu do maksymalnych dostępnych wersji PHP i Laravel oraz wszystkich zależności do najnowszych kompatybilnych wersji.
- **Szczegóły:**
  - **Aktualizacja PHP:**
    - Sprawdzić najnowszą dostępną wersję PHP (8.4 lub 8.5 jeśli dostępne)
    - Zaktualizować `composer.json` constraint z `^8.2` do `^8.4` (lub wyższej)
    - Zaktualizować platform config w `composer.json` z `8.2.0` do najnowszej wersji
    - Zaktualizować CI workflow (`.github/workflows/ci.yml`) jeśli potrzeba
    - Sprawdzić kompatybilność wszystkich zależności z nową wersją PHP
  - **Aktualizacja Laravel:**
    - Sprawdzić najnowszą dostępną wersję Laravel 12.x (obecnie: v12.36.1)
    - Zaktualizować `composer.json` constraint do najnowszej wersji `^12.0` (lub konkretnej wersji)
    - Uruchomić `composer update laravel/framework` i sprawdzić breaking changes
    - Przejrzeć dokumentację migracji Laravel dla zmian między wersjami
  - **Aktualizacja zależności:**
    - Zaktualizować wszystkie zależności do najnowszych kompatybilnych wersji
    - Sprawdzić `composer outdated` i zaktualizować pakiety
    - Zweryfikować kompatybilność zależności z nowymi wersjami PHP i Laravel
    - Rozwiązać konflikty zależności jeśli wystąpią
  - **Aktualizacja zależności dev:**
    - Zaktualizować narzędzia deweloperskie (PHPStan, PHPUnit, Pint, etc.)
    - Sprawdzić kompatybilność z nowymi wersjami PHP i Laravel
  - **Testowanie:**
    - Uruchomić wszystkie testy (unit i feature)
    - Sprawdzić PHPStan (poziom 5, zero błędów)
    - Sprawdzić Laravel Pint (formatowanie)
    - Przetestować manualnie kluczowe funkcjonalności
    - Sprawdzić CI workflow dla wszystkich wersji PHP
  - **Dokumentacja:**
    - Zaktualizować dokumentację projektu z nowymi wersjami
    - Zaktualizować README jeśli zawiera informacje o wersjach
    - Zaktualizować `.cursor/rules/workflow.mdc` jeśli potrzeba
- **Zależności:** 
  - TASK-049 (opcjonalnie - może pomóc w rozwiązaniu problemów z testami)
- **Utworzone:** 2025-12-14
- **Aktualne wersje:**
  - PHP: `^8.2` (platform: `8.2.0`)
  - Laravel: `^12.0` (zainstalowana: `v12.36.1`)
  - CI testuje: PHP 8.2, 8.3, 8.4
- **Cel:**
  - PHP: `^8.4` (lub wyższa jeśli dostępna)
  - Laravel: najnowsza wersja `12.x`
  - Wszystkie zależności: najnowsze kompatybilne wersje

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
- **Czas zakończenia:** 2025-12-01 (Faza 1), 2025-12-06 01:24 (Faza 2), 2025-12-06 02:17 (Faza 3)
- **Czas realizacji:** ~5h (Faza 1), ~00h14m (Faza 2), ~00h47m (Faza 3 - feature flag + testy)
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
- **Zakres wykonanych prac (Faza 3):**
  - ✅ Utworzono feature flag `tmdb_verification` do kontroli weryfikacji TMDb (togglable przez API)
  - ✅ Zintegrowano feature flag w `TmdbVerificationService` (sprawdzanie przed weryfikacją w `verifyMovie()`, `verifyPerson()`, `searchMovies()`)
  - ✅ Zaktualizowano kontrolery (`MovieController`, `PersonController`) - pozwalają na generowanie bez TMDb gdy flag wyłączony
  - ✅ Utworzono testy jednostkowe (4 testy dla feature flag'a) i feature (4 testy) - wszystkie przechodzą
  - ✅ PHPStan bez błędów, Laravel Pint formatowanie
  - ✅ Zaktualizowano dokumentację
- **Zależności:** Brak
- **Utworzone:** 2025-11-30
- **Ukończone (Faza 1):** 2025-12-01
- **Powiązane dokumenty:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-038` - Weryfikacja zgodności danych AI z slugiem
- **Status:** ✅ COMPLETED (Faza 1), ⏳ PENDING (Faza 2)
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** Faza 1: 3-4h (✅), Faza 2: 6-8h (⏳)
- **Czas rozpoczęcia:** 2025-12-01
- **Czas zakończenia:** 2025-12-01 (Faza 1)
- **Czas realizacji:** ~4h (Faza 1)
- **Realizacja:** 🤖 AI Agent
- **Opis:** Implementacja walidacji czy dane wygenerowane przez AI faktycznie należą do filmu/osoby określonej przez slug, przeciwdziałanie niezgodnościom danych.
- **Szczegóły:**
  - **✅ Faza 1 (UKOŃCZONA):** Implementacja serwisu `AiDataValidator` z heurystykami walidacji, walidacja czy tytuł/imię pasuje do slug (Levenshtein + fuzzy matching), walidacja czy rok wydania/data urodzenia są rozsądne (1888-aktualny rok+2), odrzucanie danych jeśli niezgodność > threshold (0.6), integracja z Jobami (RealGenerateMovieJob, RealGeneratePersonJob) z feature flag `hallucination_guard`
  - **⏳ Faza 2 (PENDING):** Rozszerzone heurystyki (sprawdzanie czy reżyser pasuje do gatunku, geografia dla osób, spójność gatunków z rokiem), logowanie i monitoring podejrzanych przypadków (nawet gdy przeszły walidację), dashboard/metrics dla jakości danych AI, dostosowanie threshold na podstawie danych produkcyjnych
- **Zależności:** Brak (może być realizowane równolegle z TASK-037)
- **Utworzone:** 2025-11-30
- **Ukończone (Faza 1):** 2025-12-01
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
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 30-40 godzin
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
- **Realizacja:** Do ustalenia
- **Opis:** Implementacja osobnych encji domenowych Series i TVShow zgodnie z Domain-Driven Design. Movie i Series/TV Show to różne koncepty domenowe - Movie nie ma odcinków, Series ma.
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
- **Utworzone:** 2025-01-09
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
- **Status:** ⏳ PENDING (Wymaga TASK-041)
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 8-10 godzin (Faza 1), 3-4 godziny (Faza 2)
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Rozszerzenie integracji TMDb o weryfikację seriali i TV Shows przed generowaniem przez AI.
- **Szczegóły:**
  - **Faza 1 (Podstawowa) - ⏳ PENDING:**
    - Rozszerzenie `TmdbVerificationService` o metody:
      - `verifySeries(string $slug): ?array`
      - `verifyTVShow(string $slug): ?array`
    - Integracja weryfikacji w `SeriesController::show()` i `TVShowController::show()`
    - Aktualizacja jobów generacji dla seriali/TV Shows
    - Testy dla seriali i TV Shows
  - **Faza 2 (Optymalizacja) - ⏳ PENDING:**
    - Rozszerzenie cache o seriale i TV Shows (wspólny cache z filmami i osobami)
    - Testy cache
- **Zależności:** TASK-041 (dodanie seriali/TV Shows), TASK-044 (Faza 1), TASK-045 (Faza 1)
- **Powiązane dokumenty:**
  - [`docs/knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md`](../../knowledge/technical/AI_VERIFICATION_ANALYSIS_ALL_TYPES.md)
- **Utworzone:** 2025-12-03
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
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Zapewnić, że endpoint `GET /api/v1/movies/{slug}` zwraca podstawową obsadę (imię/nazwisko/rola) także dla świeżo wygenerowanych filmów poprzez automatyczne tworzenie rekordów `Person` i powiązań `movie_person`.
- **Szczegóły:**
  - Rozszerzyć job generujący (`RealGenerateMovieJob` / `MockGenerateMovieJob`) o logikę zapisu osób zwróconych przez AI (reżyserzy, główna obsada).
  - Zadbać o de-duplikację (np. gdy osoba już istnieje), update relacji oraz utrzymanie minimalnego zestawu danych (imię, nazwisko, rola).
  - Uzupełnić testy feature (`MoviesApiTest`) i dokumentację (OpenAPI, Postman/Insomnia) o scenariusz z automatycznie utworzoną obsadą.
- **Zależności:** Rozważyć synchronizację z `TASK-022` (lista osób)
- **Utworzone:** 2025-11-10

---

#### `TASK-033` - Usunięcie modelu Actor i konsolidacja na Person
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 2-3 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Wyeliminowanie legacy modelu `Actor` na rzecz ujednoliconego `Person`, tak aby cała obsada korzystała z jednej tabeli i relacji `movie_person`.
- **Szczegóły:**
  - Zastąpić odwołania do `Actor`/`ActorBio` w seederach, jobach i relacjach odpowiednikami `Person`/`PersonBio`.
  - Zaktualizować migracje/seedery lub dodać migrację porządkującą dane po migracji aktorów do tabeli `people`.
  - Usunąć nieużywane pliki (`app/Models/Actor*`, seeder `ActorSeeder`, etc.) oraz zaktualizować testy i dokumentację (OpenAPI, Postman, README) aby używały `Person`.
- **Zależności:** Powiązane z `TASK-032`, `TASK-022`
- **Utworzone:** 2025-11-10

---

### 🔄 IN_PROGRESS

#### `TASK-023` - Integracja i naprawa połączenia z OpenAI
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


---

## ✅ **Zakończone Zadania**

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

- **Aktywne:** 28
- **Zakończone:** 22
- **Anulowane:** 0
- **W trakcie:** 0

---

**Ostatnia aktualizacja:** 2025-12-14

