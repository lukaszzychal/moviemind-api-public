# 📋 Plan Wykonania Wszystkich Zadań w Osobnych Branchach

**Data utworzenia:** 2025-01-10  
**Status:** 🔄 AKTYWNY

---

## 🎯 Strategia Wykonania

### Zasady
1. **Jeden branch = jedno zadanie**
2. **Nazewnictwo branchy:** `feature/TASK-XXX-krotki-opis`
3. **Kolejność:** Zgodnie z priorytetami i zależnościami
4. **Workflow:** Branch → Implementacja → Testy → Commit → PR → Merge → Cleanup

---

## 📊 Lista Zadań do Wykonania

### Faza 1: Wysoki Priorytet (🔴) - 4 zadania

#### 1. TASK-048 - Kompleksowa dokumentacja bezpieczeństwa
- **Branch:** `feature/TASK-048-security-documentation`
- **Status:** 🔄 IN_PROGRESS → ✅ COMPLETED
- **Czas:** 4-6h (reszta do dokończenia)
- **Działania:**
  - ✅ Sprawdzenie kompletności dokumentacji
  - ⏳ Weryfikacja wszystkich wymaganych elementów
  - ⏳ Aktualizacja SECURITY.md jeśli potrzebne
  - ⏳ Finalizacja i zamknięcie zadania

#### 2. TASK-043 - Implementacja zasady wykrywania BREAKING CHANGE
- **Branch:** `feature/TASK-043-breaking-change-detection`
- **Status:** ⏳ PENDING
- **Czas:** 2-3h
- **Działania:**
  - Utworzenie `.cursor/rules/breaking-change-detection.mdc`
  - Zasada traktowania zmian jakby były na produkcji
  - Wymaganie analizy skutków przed wprowadzeniem
  - Proces: STOP → analiza → dokumentacja → approval

#### 3. TASK-037 (F2-3) - Weryfikacja istnienia przed AI
- **Branch:** `feature/TASK-037-verification-phase2`
- **Status:** ⏳ PENDING
- **Czas:** 8-12h (Faza 2)
- **Zależności:** Faza 1 ✅
- **Działania:**
  - Heurystyki walidacji przed generowaniem (PreGenerationValidator)
  - Aktywacja feature flag `hallucination_guard`
  - Rozszerzone heurystyki (rok, data urodzenia, slug, wzorce)

#### 4. TASK-038 (F2) - Weryfikacja zgodności danych
- **Branch:** `feature/TASK-038-data-consistency-phase2`
- **Status:** ⏳ PENDING
- **Czas:** 6-8h
- **Zależności:** Faza 1 ✅
- **Działania:**
  - Rozszerzone heurystyki (reżyser ↔ gatunek, geografia)
  - Logowanie i monitoring podejrzanych przypadków
  - Dashboard/metrics dla jakości danych AI

---

### Faza 2: Średni Priorytet - Funkcjonalne (🟡) - 5 zadań

#### 5. TASK-013 - Konfiguracja dostępu do Horizon
- **Branch:** `feature/TASK-013-horizon-access-config`
- **Status:** ⏳ PENDING
- **Czas:** 1-2h
- **Działania:**
  - Przeniesienie listy emaili do konfiguracji/ENV
  - Dodanie testów/reguł zapobiegających otwarciu w produkcji
  - Aktualizacja dokumentacji operacyjnej

#### 6. TASK-022 - Endpoint listy osób
- **Branch:** `feature/TASK-022-people-list-endpoint`
- **Status:** ⏳ PENDING
- **Czas:** 2-3h
- **Działania:**
  - Implementacja `GET /api/v1/people`
  - Ujednolicenie parametrów z endpointem movies
  - Kontroler, resource, testy feature
  - Aktualizacja dokumentacji (OpenAPI, Postman, Insomnia)

#### 7. TASK-024 - Baseline locking
- **Branch:** `feature/TASK-024-baseline-locking`
- **Status:** ⏳ PENDING
- **Czas:** 4h
- **Zależności:** TASK-012 ✅, TASK-023 ✅
- **Działania:**
  - Weryfikacja konfiguracji flagi `ai_generation_baseline_locking`
  - Procedura rollout
  - Uzupełnienie testów (Mock/Real jobs)
  - Metryki/logi do monitorowania

#### 8. TASK-025 - Standaryzacja flag
- **Branch:** `feature/TASK-025-flag-standardization`
- **Status:** ⏳ PENDING
- **Czas:** 1h
- **Działania:**
  - Aktualizacja `.cursor/rules/coding-standards.mdc`
  - Rozróżnienie flag produktowych vs developerskich
  - Lifecycle flag developerskich
  - Synchronizacja dokumentacji FEATURE_FLAGS

#### 9. TASK-026 - Pola zaufania
- **Branch:** `feature/TASK-026-confidence-fields`
- **Status:** ⏳ PENDING
- **Czas:** 1-2h
- **Działania:**
  - Weryfikacja pól `confidence` i `confidence_level`
  - Identyfikacja przyczyny wartości null/unknown
  - Testy regresyjne
  - Aktualizacja dokumentacji API

---

### Faza 3: Infrastruktura i CI/CD (🟡) - 3 zadania

#### 10. TASK-011 - CI dla staging (GHCR)
- **Branch:** `feature/TASK-011-staging-ci-ghcr`
- **Status:** ⏳ PENDING
- **Czas:** 3h
- **Działania:**
  - Workflow GitHub Actions dla staging
  - Build obrazu Docker
  - Publikacja do GitHub Container Registry
  - Konfiguracja triggerów i sekretów

#### 11. TASK-015 - Testy Newman w CI
- **Branch:** `feature/TASK-015-newman-tests-ci`
- **Status:** ⏳ PENDING
- **Czas:** 2h
- **Działania:**
  - Dodanie kroku Newman do `.github/workflows/ci.yml`
  - Konfiguracja environmentów/sekretów
  - Raportowanie wyników (CLI/JUnit)
  - Dokumentacja

#### 12. TASK-019 - Docker Distroless
- **Branch:** `feature/TASK-019-docker-distroless`
- **Status:** ⏳ PENDING
- **Czas:** 3-4h
- **Działania:**
  - Wybór odpowiedniej bazy Distroless
  - Wieloetapowy build (PHP-FPM, Nginx, Supervisor)
  - Modifikacja `docker/php/Dockerfile`
  - Wektorowa forma CMD/ENTRYPOINT
  - Aktualizacja dokumentacji wdrożeniowej

---

### Faza 4: Refaktoryzacja (🟡) - 5 zadań

#### 13. TASK-032 - Automatyczna obsada
- **Branch:** `feature/TASK-032-auto-cast-generation`
- **Status:** ⏳ PENDING
- **Czas:** 3h
- **Zależności:** TASK-022 (rozważyć)
- **Działania:**
  - Rozszerzenie jobów generujących o logikę zapisu osób
  - De-duplikacja osób
  - Update relacji `movie_person`
  - Testy feature i dokumentacja

#### 14. TASK-033 - Usunięcie Actor
- **Branch:** `feature/TASK-033-remove-actor-model`
- **Status:** ⏳ PENDING
- **Czas:** 2-3h
- **Zależności:** TASK-032, TASK-022
- **Działania:**
  - Zastąpienie odwołań do Actor/ ActorBio
  - Migracja danych
  - Usunięcie nieużywanych plików
  - Aktualizacja testów i dokumentacji

#### 15. TASK-028 - Synchronizacja Issues
- **Branch:** `feature/TASK-028-priority-tags-sync`
- **Status:** ⏳ PENDING
- **Czas:** 0.5-1h
- **Działania:**
  - Weryfikacja workflow synchronizacji
  - Mapowanie priorytetów na tagi GitHub Issues
  - Aktualizacja `scripts/sync_tasks.py`
  - Dokumentacja procesu

#### 16. TASK-029 - Standaryzacja testów
- **Branch:** `feature/TASK-029-test-standardization`
- **Status:** ⏳ PENDING
- **Czas:** 2-3h
- **Działania:**
  - Analiza wzorców AAA i GWT
  - Rekomendacja dla MovieMind API
  - Plan refaktoryzacji testów
  - Aktualizacja wytycznych testów

#### 17. TASK-018 - PhpstanFixer package
- **Branch:** `feature/TASK-018-phpstan-fixer-package`
- **Status:** ⏳ PENDING
- **Czas:** 3-4h
- **Zależności:** TASK-017 ✅
- **Działania:**
  - Wydzielenie do osobnego repo/paczki
  - Przestrzeń nazw `Moviemind\PhpstanFixer`
  - composer.json, autoload PSR-4
  - Dokumentacja instalacji
  - Pipeline publikacji

---

### Faza 5: Dokumentacja i Analiza (🟡) - 5 zadań

#### 18. TASK-031 - Wersjonowanie opisów
- **Branch:** `feature/TASK-031-description-versioning`
- **Status:** ⏳ PENDING
- **Czas:** 1-2h
- **Zależności:** TASK-012, TASK-024
- **Działania:**
  - Synteza ustaleń
  - Opis konsekwencji obecnego podejścia
  - Plan migracji do wersjonowania
  - Notatka/ADR

#### 19. TASK-040 - Analiza TOON vs JSON
- **Branch:** `feature/TASK-040-toon-vs-json-analysis`
- **Status:** ⏳ PENDING
- **Czas:** 2-3h
- **Działania:**
  - Analiza formatu TOON
  - Porównanie TOON vs JSON (oszczędność tokenów)
  - Ocena przydatności dla MovieMind API
  - Rekomendacje

#### 20. TASK-020 - Zachowanie AI dla nieistniejących
- **Branch:** `feature/TASK-020-ai-nonexistent-behavior`
- **Status:** ⏳ PENDING
- **Czas:** 2h
- **Działania:**
  - Analiza jobów generujących
  - Scenariusz zabezpieczający
  - Testy regresyjne
  - Aktualizacja dokumentacji

#### 21. TASK-041 - Seriale i TV Shows (DDD)
- **Branch:** `feature/TASK-041-series-tvshows-ddd`
- **Status:** ⏳ PENDING
- **Czas:** 30-40h (DUŻE ZADANIE)
- **Działania:**
  - Modele Series i TVShow
  - Wspólne interfejsy/trait
  - Repositories i Controllers
  - Joby generowania
  - Migracje, testy, dokumentacja

#### 22. TASK-046 - TMDb dla seriali
- **Branch:** `feature/TASK-046-tmdb-series-integration`
- **Status:** ⏳ PENDING
- **Czas:** 8-10h
- **Zależności:** TASK-041
- **Działania:**
  - Rozszerzenie TmdbVerificationService
  - Integracja w kontrolerach
  - Testy dla seriali i TV Shows

---

### Faza 6: Niski Priorytet - Roadmap (🟢) - 5 zadań

#### 23. TASK-008 - Webhooks System
- **Branch:** `feature/TASK-008-webhooks-system`
- **Status:** ⏳ PENDING
- **Czas:** 8-10h
- **Działania:**
  - Projekt architektury webhooks
  - Implementacja endpointów
  - System retry i error handling
  - Dokumentacja

#### 24. TASK-009 - Admin UI
- **Branch:** `feature/TASK-009-admin-ui`
- **Status:** ⏳ PENDING
- **Czas:** 15-20h
- **Działania:**
  - Wybór narzędzia (Nova/Filament/Breeze)
  - Implementacja panelu admin
  - Zarządzanie movies, people, flags

#### 25. TASK-010 - Analytics Dashboards
- **Branch:** `feature/TASK-010-analytics-dashboards`
- **Status:** ⏳ PENDING
- **Czas:** 10-12h
- **Działania:**
  - Dashboard queue jobs status
  - Monitoring failed jobs
  - Analytics metrics (API usage, generation stats)

#### 26. TASK-030 - Dokumentacja "trzech linii"
- **Branch:** `feature/TASK-030-three-line-tests-doc`
- **Status:** ⏳ PENDING
- **Czas:** 1-2h
- **Zależności:** TASK-029
- **Działania:**
  - Dokument w `docs/knowledge/tutorials/`
  - Przykłady kodu, korzyści i ograniczenia
  - Konwencje nazewnicze
  - Integracja z PHPUnit

#### 27. TASK-042 - Analiza rozszerzeń
- **Branch:** `feature/TASK-042-extension-analysis`
- **Status:** ⏳ PENDING
- **Czas:** 4-6h
- **Działania:**
  - Analiza obecnej struktury
  - Identyfikacja potencjalnych rozszerzeń
  - Analiza wpływu na API, DB, joby
  - Dokumentacja rekomendacji

---

## 🔄 Workflow dla Każdego Zadania

### Krok 1: Przygotowanie
```bash
git checkout main
git pull origin main
git checkout -b feature/TASK-XXX-opis
```

### Krok 2: Aktualizacja Statusu
- Zmiana statusu w `docs/issue/pl/TASKS.md` na `🔄 IN_PROGRESS`
- Wpisanie czasu rozpoczęcia

### Krok 3: Implementacja
- Wykonanie zadania zgodnie z opisem
- Testy (jeśli wymagane)
- Aktualizacja dokumentacji

### Krok 4: Pre-Commit Checks
```bash
cd api && vendor/bin/pint
cd api && vendor/bin/phpstan analyse --memory-limit=2G
cd api && php artisan test
cd .. && gitleaks protect --source . --verbose --no-banner
cd api && composer audit
```

### Krok 5: Commit
```bash
git add .
git commit -m "feat: TASK-XXX - opis zadania"
```

### Krok 6: Push i PR
```bash
git push origin feature/TASK-XXX-opis
# Utworzenie PR na GitHub
```

### Krok 7: Finalizacja
- Merge PR
- Aktualizacja statusu w `docs/issue/pl/TASKS.md` na `✅ COMPLETED`
- Wpisanie czasu zakończenia i realizacji
- Przeniesienie zadania do sekcji "Zakończone"
- Usunięcie brancha lokalnego: `git branch -d feature/TASK-XXX-opis`

---

## 📊 Podsumowanie

### Statystyki
- **Łączna liczba zadań:** 27
- **Wysoki priorytet:** 4 zadania
- **Średni priorytet:** 18 zadań
- **Niski priorytet:** 5 zadań

### Szacowany czas
- **Wysoki:** ~20-29h
- **Średni:** ~80-95h
- **Niski:** ~38-50h
- **Łącznie:** ~138-174h

### Kolejność wykonania
1. TASK-048 (dokończenie)
2. TASK-043
3. TASK-037 (F2-3)
4. TASK-038 (F2)
5. TASK-013, TASK-022, TASK-024, TASK-025, TASK-026
6. TASK-011, TASK-015, TASK-019
7. TASK-032, TASK-033, TASK-028, TASK-029, TASK-018
8. TASK-031, TASK-040, TASK-020, TASK-041, TASK-046
9. TASK-008, TASK-009, TASK-010, TASK-030, TASK-042

---

**Ostatnia aktualizacja:** 2025-01-10

