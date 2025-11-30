# 📋 Backlog Zadań - MovieMind API

**Ostatnia aktualizacja:** 2025-11-30  
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
5. **Wynik:** ułóż listę rekomendowanego porządku + krótka notatka *dlaczego* (np. „odblokowuje X”, „wspiera testy”, „roadmapa”).

> **Przykład raportu:**  
> 1. `TASK-007` – centralizuje flagi; fundament dla ochrony Horizon i kontroli AI.  
> 2. `TASK-013` – zabezpiecza panel Horizon po zmianach flag.  
> 3. `TASK-020` – audyt AI korzysta z ustabilizowanych flag oraz monitoringu Horizon.  
> …

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
  - Zaktualizować dokumentację wdrożeniową (Railway, README, playbooki operacyjne) do nowego obrazu.
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

#### `TASK-027` - Diagnostyka duplikacji eventów generowania (movies/people)
<<<<<<< HEAD
- **Status:** 🔄 IN_PROGRESS
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** 2025-11-10 18:03
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** 🤖 AI Agent
=======
- **Status:** ⏳ PENDING
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
>>>>>>> feature/TASK-023-openai-integration
- **Opis:** Ustalenie, dlaczego eventy generowania filmów i osób są wyzwalane wielokrotnie, prowadząc do powielania jobów/opisów.
- **Szczegóły:**
  - Odtworzyć problem w flow `GET /api/v1/movies/{slug}` oraz `GET /api/v1/people/{slug}` oraz podczas `POST /api/v1/generate`.
  - Przeanalizować miejsca emisji eventów i listenerów (kontrolery, serwisy, joby) pod kątem wielokrotnego dispatchu.
  - Zweryfikować liczbę wpisów w logach/kolejce i przygotować propozycję poprawek z testami regresyjnymi.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

---

<<<<<<< HEAD
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

=======
>>>>>>> feature/TASK-023-openai-integration
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
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 1-2 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** -- (Agent AI obliczy automatycznie przy trybie 🤖)
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

=======
>>>>>>> feature/TASK-023-openai-integration
### 🔄 IN_PROGRESS

#### `TASK-023` - Integracja i naprawa połączenia z OpenAI
- **Status:** 🔄 IN_PROGRESS
- **Priorytet:** 🔴 Wysoki
- **Szacowany czas:** 3 godziny
- **Czas rozpoczęcia:** 2025-11-10 14:00
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** 🤖 AI Agent
- **Opis:** Integracja i naprawa połączenia z OpenAI.
- **Szczegóły:**
  - Diagnoza błędów komunikacji (timeouty, odpowiedzi HTTP, limity).
  - Weryfikacja konfiguracji kluczy (`OPENAI_API_KEY`, endpointy, modele).
  - Aktualizacja serwisów i fallbacków obsługujących OpenAI w API.
  - Przygotowanie testów (unit/feature) potwierdzających poprawną integrację.
- **Zależności:** Brak
- **Utworzone:** 2025-11-10

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

#### `TASK-018` - Wydzielenie PhpstanFixer jako paczki Composer
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Szacowany czas:** 3-4 godziny
- **Czas rozpoczęcia:** --
- **Czas zakończenia:** --
- **Czas realizacji:** --
- **Realizacja:** Do ustalenia
- **Opis:** Przeniesienie modułu `App\Support\PhpstanFixer` do osobnej paczki Composer instalowanej jako zależność projektu.
- **Szczegóły:**
  - Wydzielić kod do repozytorium/paczki z przestrzenią nazw np. `Moviemind\PhpstanFixer`.
  - Przygotować `composer.json`, autoload PSR-4 i dokumentację instalacji/konfiguracji.
  - Zastąpić bieżącą implementację importem paczki i zaktualizować DI w aplikacji.
  - Dodać pipeline publikacji (packagist lub private repo) oraz opis wersjonowania.
- **Zależności:** TASK-017
- **Utworzone:** 2025-11-08

---

## ✅ **Zakończone Zadania**

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

- **Aktywne:** 17
- **Zakończone:** 7
- **Anulowane:** 0
- **W trakcie:** 2

---

**Ostatnia aktualizacja:** 2025-11-30

