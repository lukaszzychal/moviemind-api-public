# 📊 Analiza Priorytetyzacji Zadań - MovieMind API

**Data aktualizacji:** 2025-12-06  
**Źródło:** `docs/issue/pl/TASKS.md`  
**Status:** 🔄 Aktywny

---

## 🎯 Cel Dokumentu

Dokument zawiera szczegółową analizę zadań według:
- **Priorytetów** (🔴 Wysoki, 🟡 Średni, 🟢 Niski)
- **Powiązań** (zależności, odblokowywanie innych zadań)
- **Kolejności wykonania** (rekomendowana sekwencja)

---

## 📋 Rekomendowana Kolejność Wykonania

### 🔴 Faza 1: Krytyczne dla Stabilności i Bezpieczeństwa

#### 1. **TASK-038 (Faza 2)** - Weryfikacja zgodności danych AI z slugiem (Faza 2)
- **Priorytet:** 🔴 Wysoki
- **Status:** ⏳ PENDING (Faza 1 ✅ COMPLETED)
- **Szacowany czas:** 6-8h
- **Zależności:** Faza 1 ✅ COMPLETED
- **Uzasadnienie:**
  - Faza 1 ukończona - podstawowa walidacja działa
  - Faza 2 rozszerza heurystyki (reżyser vs gatunek, geografia dla osób, spójność gatunków)
  - Dodaje logowanie i monitoring podejrzanych przypadków
  - Dashboard/metrics dla jakości danych AI
  - **Krytyczne dla jakości** - zapobiega niezgodnościom danych AI z rzeczywistością
- **Powiązania:** Wspiera TASK-037 (weryfikacja przed generowaniem)

#### 2. **TASK-013** - Konfiguracja dostępu do Horizon
- **Priorytet:** 🟡 Średni (ale krytyczne dla bezpieczeństwa)
- **Status:** ⏳ PENDING
- **Szacowany czas:** 1-2h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Bezpieczeństwo** - zabezpiecza panel Horizon w produkcji
  - Krótkie zadanie (1-2h) - szybki efekt
  - Przeniesienie listy autoryzowanych e-maili do konfiguracji/ENV
  - Testy/reguły zapobiegające przypadkowemu otwarciu panelu w produkcji
  - **Powinno być wykonane przed deploymentem na produkcję**
- **Powiązania:** Wspiera bezpieczeństwo infrastruktury

---

### 🟡 Faza 2: Funkcjonalne Usprawnienia

#### 3. **TASK-022** - Endpoint listy osób (List People)
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2-3h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Parzystość API** - uzupełnia podstawowe endpointy (analogicznie do listy filmów)
  - **Odblokowuje inne zadania:**
    - TASK-032 (automatyczna obsada wymaga listy osób)
    - TASK-033 (konsolidacja Actor → Person wymaga listy osób)
  - Ujednolicenie parametrów filtrowania, sortowania i paginacji
  - **Powinno być wykonane wcześnie** - fundament dla innych zadań
- **Powiązania:** 
  - Odblokowuje: TASK-032, TASK-033
  - Wspiera: parzystość API

#### 4. **TASK-025** - Standaryzacja flag produktowych i developerskich
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 1h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Krótkie zadanie** (1h) - szybki efekt
  - Uporządkowanie zarządzania flagami
  - Rozróżnienie flag produktowych (długoterminowe) vs developerskich (tymczasowe)
  - Lifecycle flag developerskich (tworzenie, testowanie, obowiązkowe usuwanie)
  - **Upraszcza zarządzanie** - lepsze praktyki
- **Powiązania:** Wspiera zarządzanie feature flags

#### 5. **TASK-024** - Wdrożenie planu baseline locking
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 4h
- **Zależności:** TASK-012 ✅ COMPLETED, TASK-023 ✅ COMPLETED
- **Uzasadnienie:**
  - **Stabilizacja generowania** - zapobiega race conditions
  - Weryfikacja konfiguracji flagi `ai_generation_baseline_locking`
  - Uzupełnienie testów (Mock/Real jobs) o warianty z aktywną flagą
  - Metryki/logi do monitorowania trybu baseline locking
  - **Krytyczne dla stabilności** - zapobiega problemom z równoległą generacją
- **Powiązania:** Wspiera TASK-031 (wersjonowanie opisów)

#### 6. **TASK-026** - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 1-2h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Krótkie zadanie** (1-2h) - szybki efekt
  - **Poprawa UX** - użytkownik widzi poziom pewności generacji
  - Weryfikacja pól `confidence` i `confidence_level` w odpowiedziach
  - Identyfikacja przyczyny `confidence = null` i `confidence_level = unknown`
  - Testy regresyjne zabezpieczające poprawione zachowanie
  - **Ulepsza doświadczenie użytkownika**
- **Powiązania:** Wspiera jakość API

---

### 🟡 Faza 3: Refaktoryzacja i Czyszczenie

#### 7. **TASK-032** - Automatyczne tworzenie obsady przy generowaniu filmu
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 3h
- **Zależności:** TASK-022 (rozważyć)
- **Uzasadnienie:**
  - **Uzupełnia dane filmów** - automatyczne tworzenie rekordów Person i powiązań
  - Rozszerzenie jobów generujących o logikę zapisu osób (reżyserzy, obsada)
  - De-duplikacja (gdy osoba już istnieje)
  - **Odblokowuje TASK-033** - konsolidacja Actor → Person
  - **Powinno być wykonane przed TASK-033**
- **Powiązania:**
  - Wymaga: TASK-022 (lista osób)
  - Odblokowuje: TASK-033

#### 8. **TASK-033** - Usunięcie modelu Actor i konsolidacja na Person
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2-3h
- **Zależności:** TASK-032, TASK-022
- **Uzasadnienie:**
  - **Eliminacja legacy** - uporządkowanie kodu
  - Zastąpienie odwołań do `Actor`/`ActorBio` odpowiednikami `Person`/`PersonBio`
  - Migracja danych (aktory → osoby)
  - Usunięcie nieużywanych plików
  - **Upraszcza architekturę** - jeden model zamiast dwóch
- **Powiązania:**
  - Wymaga: TASK-032, TASK-022
  - Wspiera: czystość kodu

#### 9. **TASK-028** - Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 0.5-1h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Krótkie zadanie** (0.5-1h) - szybki efekt
  - **Usprawnienie workflow** - lepsze zarządzanie zadaniami
  - Weryfikacja mechanizmu synchronizacji pod kątem przekazywania priorytetów
  - Mapowanie priorytetów (🔴/🟡/🟢) na tagi/etykiety w GitHub Issues
  - **Poprawia widoczność** priorytetów w GitHub
- **Powiązania:** Wspiera zarządzanie zadaniami

#### 10. **TASK-029** - Uporządkowanie testów według wzorca AAA lub GWT
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2-3h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Standaryzacja testów** - lepsza czytelność
  - Analiza wzorców AAA (Arrange-Act-Assert) vs GWT (Given-When-Then)
  - Plan refaktoryzacji istniejących testów
  - Aktualizacja wytycznych dotyczących testów
  - **Odblokowuje TASK-030** - dokumentacja techniki "trzech linii"
  - **Poprawia jakość testów**
- **Powiązania:**
  - Odblokowuje: TASK-030
  - Wspiera: jakość kodu

#### 11. **TASK-018** - Wydzielenie PhpstanFixer jako paczki Composer
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 3-4h
- **Zależności:** TASK-017 ✅ COMPLETED
- **Uzasadnienie:**
  - **Reużywalność** - możliwość użycia w innych projektach
  - Wydzielenie kodu do osobnej paczki Composer
  - Przygotowanie `composer.json`, autoload PSR-4
  - Pipeline publikacji (Packagist lub private repo)
  - **Długoterminowa korzyść** - reużywalność narzędzi
- **Powiązania:**
  - Wymaga: TASK-017 ✅
  - Wspiera: reużywalność narzędzi

---

### 🟡 Faza 4: Infrastruktura i CI/CD

#### 12. **TASK-011** - Stworzenie CI dla staging (GHCR)
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 3h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Automatyzacja deploymentu** - szybsze iteracje
  - Workflow GitHub Actions budujący obraz Docker dla staging
  - Publikacja do GitHub Container Registry
  - Trigger na push/tag `staging`
  - **Przyspiesza development** - automatyczny deployment
- **Powiązania:** Wspiera CI/CD pipeline

#### 13. **TASK-015** - Automatyczne testy Newman w CI
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Automatyczna weryfikacja API** - wyższa jakość
  - Integracja kolekcji Postman z pipeline CI
  - Uruchamianie Newman w GitHub Actions
  - Raportowanie wyników (CLI/JUnit)
  - **Zwiększa pewność** - automatyczne testy API
- **Powiązania:**
  - Wymaga: aktualne szablony environmentów Postman
  - Wspiera: jakość API

#### 14. **TASK-019** - Migracja produkcyjnego obrazu Docker na Distroless
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 3-4h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Bezpieczeństwo** - zmniejszenie powierzchni ataku
  - Zastąpienie alpine'owego obrazu wersją Distroless od Google
  - Wieloetapowy build (PHP-FPM, Nginx, Supervisor)
  - Wektorowa forma `CMD`/`ENTRYPOINT` (bez powłoki)
  - **Krytyczne dla bezpieczeństwa** - mniejsza powierzchnia ataku
- **Powiązania:** Wspiera bezpieczeństwo infrastruktury

---

### 🟡 Faza 5: Dokumentacja i Analiza

#### 15. **TASK-031** - Kierunek rozwoju wersjonowania opisów AI
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 1-2h
- **Zależności:** TASK-012 ✅ COMPLETED, TASK-024
- **Uzasadnienie:**
  - **Dokumentacja decyzji architektonicznej**
  - Synteza ustaleń dotyczących wersjonowania opisów
  - Opis konsekwencji obecnej rekomendacji (najnowszy wpis per wariant)
  - Potencjalny plan migracji do wersjonowania historii
  - **Wspiera planowanie** - dokumentacja decyzji
- **Powiązania:**
  - Wymaga: TASK-012 ✅, TASK-024
  - Wspiera: dokumentację architektury

#### 16. **TASK-040** - Analiza formatu TOON vs JSON dla komunikacji z AI
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2-3h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Optymalizacja kosztów** - potencjalna oszczędność 30-60% tokenów
  - Analiza formatu TOON (Token-Oriented Object Notation)
  - Porównanie TOON vs JSON pod kątem oszczędności tokenów
  - Ocena przydatności TOON dla MovieMind API
  - **Potencjalne oszczędności** - mniej tokenów = niższe koszty
- **Powiązania:** Wspiera optymalizację kosztów

#### 17. **TASK-020** - Sprawdzić zachowanie AI dla nieistniejących filmów/osób
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 2h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Weryfikacja zachowania** - jakość danych
  - Analiza jobów generujących pod kątem tworzenia fikcyjnych encji
  - Propozycja/zaimplementowanie scenariusza zabezpieczającego
  - Testy regresyjne i aktualizacja dokumentacji
  - **Zapewnia jakość** - zapobiega halucynacjom AI
- **Powiązania:** Wspiera TASK-037, TASK-038

#### 18. **TASK-041** - Dodanie seriali i programów telewizyjnych (DDD approach)
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING
- **Szacowany czas:** 30-40h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Duże zadanie** - rozszerzenie funkcjonalności
  - Implementacja osobnych encji domenowych Series i TVShow (DDD)
  - Modele `Series`, `TVShow` z tabelami i relacjami
  - Wspólne interfejsy/trait (`DescribableContent`, `Sluggable`, `HasPeople`)
  - Joby, kontrolery, migracje, testy
  - **Wymaga planowania** - duże zadanie, wiele komponentów
- **Powiązania:**
  - Odblokowuje: TASK-046
  - Wspiera: rozszerzenie funkcjonalności

#### 19. **TASK-046** - Integracja TMDb API dla seriali i TV Shows
- **Priorytet:** 🟡 Średni
- **Status:** ⏳ PENDING (Wymaga TASK-041)
- **Szacowany czas:** 8-10h (Faza 1)
- **Zależności:** TASK-041, TASK-044 ✅ COMPLETED, TASK-045 ✅ COMPLETED
- **Uzasadnienie:**
  - **Wymaga TASK-041** - nie można wykonać bez seriali/TV Shows
  - Rozszerzenie `TmdbVerificationService` o metody dla seriali/TV Shows
  - Integracja weryfikacji w kontrolerach
  - Aktualizacja jobów generacji
  - **Spójność** - ta sama logika weryfikacji dla wszystkich typów
- **Powiązania:**
  - Wymaga: TASK-041, TASK-044 ✅, TASK-045 ✅
  - Wspiera: weryfikację wszystkich typów encji

---

### 🟢 Faza 6: Roadmap (Niski Priorytet)

#### 20. **TASK-030** - Opracowanie dokumentu o technice testów „trzech linii"
- **Priorytet:** 🟢 Niski
- **Status:** ⏳ PENDING
- **Szacowany czas:** 1-2h
- **Zależności:** TASK-029
- **Uzasadnienie:**
  - **Wspiera TASK-029** - dokumentacja techniczna
  - Zebranie informacji o technice "three-line tests"
  - Dokument w `docs/knowledge/tutorials/` (PL/EN)
  - Konwencje nazewnicze metod (`given*`, `when*`, `then*`)
  - **Dokumentacja** - wspiera standaryzację testów
- **Powiązania:**
  - Wymaga: TASK-029
  - Wspiera: standaryzację testów

#### 21. **TASK-042** - Analiza możliwych rozszerzeń typów i rodzajów
- **Priorytet:** 🟢 Niski
- **Status:** ⏳ PENDING
- **Szacowany czas:** 4-6h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Dokumentacja analityczna** - planowanie przyszłości
  - Analiza obecnej struktury (Movie, Person, Series, TVShow)
  - Identyfikacja potencjalnych rozszerzeń (Documentaries, Short Films, Web Series, Podcasts, Books, Music Albums)
  - Analiza wpływu na API, bazę danych, joby
  - **Planowanie** - długoterminowa wizja
- **Powiązania:** Wspiera planowanie rozwoju

#### 22. **TASK-008** - Webhooks System (Roadmap)
- **Priorytet:** 🟢 Niski
- **Status:** ⏳ PENDING
- **Szacowany czas:** 8-10h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Roadmap** - billing/notifications
  - Projekt architektury webhooks
  - Implementacja endpointów webhook
  - System retry i error handling
  - **Funkcjonalność biznesowa** - nie krytyczna dla MVP
- **Powiązania:** Wspiera funkcjonalność biznesową

#### 23. **TASK-010** - Analytics/Monitoring Dashboards (Roadmap)
- **Priorytet:** 🟢 Niski
- **Status:** ⏳ PENDING
- **Szacowany czas:** 10-12h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Roadmap** - queue jobs, metrics
  - Dashboard dla queue jobs status
  - Monitoring failed jobs
  - Analytics metrics (API usage, generation stats)
  - **Monitoring** - przydatne, ale nie krytyczne
- **Powiązania:** Wspiera monitoring i analitykę

#### 24. **TASK-009** - Admin UI (Roadmap)
- **Priorytet:** 🟢 Niski
- **Status:** ⏳ PENDING
- **Szacowany czas:** 15-20h
- **Zależności:** Brak
- **Uzasadnienie:**
  - **Roadmap** - Nova/Breeze/Filament
  - Wybór narzędzia (Laravel Nova, Filament, Breeze)
  - Implementacja panelu admin
  - Zarządzanie movies, people, flags
  - **Najdłuższe zadanie** - wymaga najwięcej czasu
- **Powiązania:** Wspiera zarządzanie treścią

---

## 🔗 Kluczowe Powiązania między Zadaniami

### Łańcuchy zależności

1. **TASK-022** → **TASK-032** → **TASK-033**
   - Lista osób → Automatyczna obsada → Konsolidacja Actor → Person

2. **TASK-029** → **TASK-030**
   - Standaryzacja testów → Dokumentacja techniki "trzech linii"

3. **TASK-041** → **TASK-046**
   - Dodanie seriali → Integracja TMDb dla seriali

4. **TASK-012 ✅, TASK-023 ✅** → **TASK-024** → **TASK-031**
   - Lock + Multi-Description → Baseline locking → Wersjonowanie opisów

### Grupy tematyczne

#### 🔒 Bezpieczeństwo
- **TASK-013** - Konfiguracja Horizon (1-2h)
- **TASK-019** - Docker Distroless (3-4h)
- **TASK-038 (F2)** - Weryfikacja zgodności danych (6-8h)
- **Łącznie:** ~10-14h

#### 🚀 CI/CD
- **TASK-011** - CI dla staging (3h)
- **TASK-015** - Testy Newman (2h)
- **Łącznie:** ~5h

#### 🔧 Refaktoryzacja
- **TASK-032** - Automatyczna obsada (3h)
- **TASK-033** - Usunięcie Actor (2-3h)
- **TASK-018** - PhpstanFixer package (3-4h)
- **Łącznie:** ~8-10h

#### 📚 Dokumentacja
- **TASK-031** - Wersjonowanie opisów (1-2h)
- **TASK-040** - Analiza TOON vs JSON (2-3h)
- **TASK-020** - Zachowanie AI (2h)
- **TASK-030** - Dokumentacja testów (1-2h)
- **TASK-042** - Analiza rozszerzeń (4-6h)
- **Łącznie:** ~10-15h

#### 🎬 Funkcjonalność
- **TASK-022** - Lista osób (2-3h)
- **TASK-024** - Baseline locking (4h)
- **TASK-025** - Standaryzacja flag (1h)
- **TASK-026** - Pola zaufania (1-2h)
- **TASK-041** - Dodanie seriali (30-40h)
- **TASK-046** - TMDb dla seriali (8-10h)
- **Łącznie:** ~46-60h

#### 🔄 Workflow
- **TASK-028** - Synchronizacja Issues (0.5-1h)
- **TASK-029** - Standaryzacja testów (2-3h)
- **Łącznie:** ~2.5-4h

---

## 📊 Podsumowanie Statystyk

### Status zadań

- **🔄 W trakcie:** 0 zadań
- **⏳ Oczekujące:** 24 zadania
- **✅ Zakończone:** 21 zadań

### Priorytety

- **🔴 Wysoki:** 2 zadania (~7-10h)
  - TASK-038 (Faza 2) - 6-8h
  - TASK-013 - 1-2h

- **🟡 Średni:** 17 zadań (~80-95h)
  - Funkcjonalne: 5 zadań (~9-12h)
  - Refaktoryzacja: 5 zadań (~10-13h)
  - Infrastruktura: 3 zadania (~8-9h)
  - Dokumentacja: 5 zadań (~38-49h)

- **🟢 Niski:** 5 zadań (~38-50h)
  - Roadmap: 4 zadania (~34-48h)
  - Dokumentacja: 1 zadanie (~4-6h)

### Szacowany czas realizacji

- **🔴 Wysoki:** ~7-10h
- **🟡 Średni:** ~80-95h
- **🟢 Niski:** ~38-50h
- **Łącznie:** ~125-155h

### Rozkład czasowy

- **Krótkie zadania (< 2h):** 6 zadań (~6-9h)
- **Średnie zadania (2-5h):** 12 zadań (~35-45h)
- **Długie zadania (> 5h):** 6 zadań (~84-101h)

---

## 🎯 Rekomendacje

### Dla MVP (Minimum Viable Product)

**Priorytet 1 - Krytyczne:**
1. TASK-038 (Faza 2) - Weryfikacja zgodności danych
2. TASK-013 - Konfiguracja Horizon

**Priorytet 2 - Funkcjonalne:**
3. TASK-022 - Lista osób (odblokowuje inne)
4. TASK-025 - Standaryzacja flag (krótkie)
5. TASK-024 - Baseline locking (stabilizacja)
6. TASK-026 - Pola zaufania (UX)

**Priorytet 3 - Refaktoryzacja:**
7. TASK-032 - Automatyczna obsada
8. TASK-033 - Usunięcie Actor

**Priorytet 4 - Infrastruktura:**
9. TASK-011 - CI dla staging
10. TASK-015 - Testy Newman
11. TASK-019 - Docker Distroless

### Dla długoterminowego rozwoju

- **TASK-041** (30-40h) - Duże zadanie, wymaga planowania
- **TASK-046** (8-10h) - Wymaga TASK-041
- **TASK-009** (15-20h) - Admin UI, najdłuższe zadanie roadmap

### Optymalizacja kosztów

- **TASK-040** (2-3h) - Analiza TOON vs JSON - potencjalna oszczędność 30-60% tokenów

---

## 📝 Uwagi

1. **TASK-043** - Zgodnie z `TASKS.md` jest ✅ COMPLETED, ale w `ZADANIA_TABELA.md` jest ⏳ PENDING. Należy zweryfikować aktualny status.

2. **TASK-037** - Zgodnie z `TASKS.md` wszystkie fazy są ✅ COMPLETED, ale w `ZADANIA_TABELA.md` jest ⏳ PENDING (Faza 2-3). Należy zweryfikować aktualny status.

3. **TASK-048** - Zgodnie z `TASKS.md` jest 🔄 IN_PROGRESS, ale w `ZADANIA_TABELA.md` jest ✅ COMPLETED. Należy zweryfikować aktualny status.

4. **Zależności:** Wszystkie zależności są oznaczone jako ✅ COMPLETED, więc można rozpocząć wykonanie zadań.

5. **Kolejność:** Rekomendowana kolejność uwzględnia zależności, odblokowywanie innych zadań oraz grupy tematyczne.

---

**Ostatnia aktualizacja:** 2025-12-06  
**Następna weryfikacja:** Po ukończeniu każdego zadania z listy

