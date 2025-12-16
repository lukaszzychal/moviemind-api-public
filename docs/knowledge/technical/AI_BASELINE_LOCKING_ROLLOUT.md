# AI Generation Baseline Locking – Rollout Plan

> **Data utworzenia:** 2025-12-16  
> **Status:** ✅ Gotowe do wdrożenia  
> **Kategoria:** technical  
> **Zadanie:** TASK-024

## 🎯 Cel

Kontrolowane wdrożenie mechanizmu baseline locking dla generacji AI opisów filmów i osób. Mechanizm zapobiega race conditions i zapewnia stabilne aktualizacje baseline'owych opisów.

## 📋 Stan Implementacji

### ✅ Zrealizowane

1. **Implementacja mechanizmu** (TASK-012)
   - Flaga `ai_generation_baseline_locking` (default: `false`, togglable: `true`)
   - Logika w `RealGenerateMovieJob` i `RealGeneratePersonJob`
   - Locki Redis dla zapobiegania race conditions
   - Testy jednostkowe pokrywające oba tryby (flag on/off)

2. **Logowanie i monitoring** (TASK-024)
   - Logi informujące o aktywności baseline locking
   - Logi z wynikiem operacji (baseline_updated vs alternative_appended)
   - Metryki w logach: job_id, slug, entity_id, baseline_id, result

3. **Dokumentacja środowiskowa**
   - Komentarze w `.env.example` dla wszystkich środowisk
   - Instrukcje toggle flagi przez admin API

## 🚀 Plan Rollout

### Faza 1: Walidacja na Staging (1-2 dni)

**Kroki:**
1. Włączyć flagę na stagingu:
   ```bash
   POST /api/v1/admin/flags/ai_generation_baseline_locking
   Body: {"state": "on"}
   ```

2. Monitorować Horizon dashboard:
   - Sprawdzić liczbę jobów w kolejce
   - Porównać z wariantem `off` (przed włączeniem)
   - Sprawdzić czasy wykonania jobów

3. Monitorować logi:
   ```bash
   # Szukaj logów baseline locking
   grep "Baseline locking active" storage/logs/laravel.log
   grep "Baseline locking result" storage/logs/laravel.log
   ```

4. Weryfikować wyniki:
   - Sprawdzić czy baseline są aktualizowane (nie appendowane)
   - Sprawdzić czy nie ma duplikacji opisów
   - Sprawdzić czy cache jest poprawnie invalidowany

**Kryteria sukcesu:**
- ✅ Brak błędów w logach
- ✅ Joby wykonują się poprawnie
- ✅ Baseline są aktualizowane (nie appendowane)
- ✅ Brak regresji w funkcjonalności

### Faza 2: Testy obciążeniowe (opcjonalnie, 1 dzień)

**Kroki:**
1. Wygenerować większą liczbę jobów równolegle (10-20)
2. Sprawdzić czy locki działają poprawnie
3. Sprawdzić czy nie ma deadlocków
4. Sprawdzić czy wszystkie joby zakończyły się sukcesem

**Kryteria sukcesu:**
- ✅ Wszystkie joby zakończone sukcesem
- ✅ Brak deadlocków
- ✅ Poprawne aktualizacje baseline

### Faza 3: Rollout do Produkcji (stopniowy)

**Kroki:**
1. **Przygotowanie:**
   - Sprawdzić czy staging działa poprawnie przez minimum 24h
   - Przygotować plan rollback
   - Poinformować zespół o wdrożeniu

2. **Włączenie flagi w produkcji:**
   ```bash
   POST /api/v1/admin/flags/ai_generation_baseline_locking
   Body: {"state": "on"}
   ```

3. **Monitoring (pierwsze 2-4 godziny):**
   - Sprawdzać Horizon dashboard co 15-30 minut
   - Monitorować logi pod kątem błędów
   - Sprawdzać metryki (liczba jobów, czasy wykonania)

4. **Weryfikacja (pierwsze 24h):**
   - Sprawdzić czy baseline są aktualizowane
   - Sprawdzić czy nie ma regresji
   - Sprawdzić czy cache działa poprawnie

**Kryteria sukcesu:**
- ✅ Brak błędów w produkcji
- ✅ Poprawne działanie baseline locking
- ✅ Brak regresji w funkcjonalności

## 🔄 Plan Rollback

### Szybki Rollback (jeśli wystąpią problemy)

**Kroki:**
1. Wyłączyć flagę natychmiast:
   ```bash
   POST /api/v1/admin/flags/ai_generation_baseline_locking
   Body: {"state": "off"}
   ```

2. System automatycznie wróci do trybu append (bez baseline locking)

3. Monitorować czy problemy zniknęły

**Uwaga:** Rollback jest natychmiastowy i bezpieczny - flaga jest togglable, więc można ją wyłączyć w każdej chwili.

### Analiza problemów

Jeśli wystąpią problemy:
1. Zalogować szczegóły błędu
2. Sprawdzić logi Horizon
3. Sprawdzić logi aplikacji
4. Zidentyfikować przyczynę
5. Przygotować poprawkę lub alternatywne rozwiązanie

## 📊 Metryki do Monitorowania

### W Horizon Dashboard

- **Liczba jobów w kolejce** - porównać przed/po włączeniu flagi
- **Czasy wykonania jobów** - sprawdzić czy nie ma degradacji
- **Failed jobs** - sprawdzić czy nie ma wzrostu błędów
- **Throughput** - sprawdzić czy nie ma spadku wydajności

### W Logach

- **Baseline locking active** - liczba jobów z aktywną flagą
- **Baseline locking result** - rozkład wyników (baseline_updated vs alternative_appended)
- **Błędy** - wszelkie błędy związane z baseline locking

### W Bazie Danych

- **Liczba opisów per film/osoba** - sprawdzić czy nie ma nieoczekiwanego wzrostu
- **Aktualizacje baseline** - sprawdzić czy baseline są aktualizowane (nie appendowane)

## 🔍 Checklista Przed Rollout

### Przed włączeniem na stagingu

- [x] Implementacja zakończona
- [x] Testy jednostkowe przechodzą
- [x] Logowanie dodane
- [x] Dokumentacja przygotowana
- [ ] Backup bazy danych (staging)
- [ ] Zespół poinformowany

### Przed włączeniem w produkcji

- [ ] Staging działa poprawnie przez minimum 24h
- [ ] Backup bazy danych (produkcja)
- [ ] Plan rollback przygotowany
- [ ] Zespół poinformowany
- [ ] Monitoring skonfigurowany
- [ ] Dostęp do Horizon dashboard

## 📝 Notatki

- Flaga jest domyślnie wyłączona (`default: false`)
- Flaga jest togglable, więc można ją włączyć/wyłączyć w każdej chwili
- Rollback jest natychmiastowy i bezpieczny
- System automatycznie wraca do trybu append gdy flaga jest wyłączona

## 🔗 Powiązane Dokumenty

- [TASK-012 dokumentacja](../issue/pl/TASKS.md)
- [AI_BASELINE_LOCKING_PLAN.md](./AI_BASELINE_LOCKING_PLAN.md)
- `config/pennant.php` – definicja flag
- `app/Jobs/RealGenerate*Job.php` – implementacja

---

**Ostatnia aktualizacja:** 2025-12-16

