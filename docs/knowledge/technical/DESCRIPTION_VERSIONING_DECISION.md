# ADR: Decyzja o wersjonowaniu opisów AI

> **Data utworzenia:** 2025-01-27  
> **Status:** ✅ Decyzja podjęta  
> **Kategoria:** technical  
> **Zadanie:** TASK-031

## 🎯 Kontekst

System MovieMind API generuje opisy filmów, seriali i osób przy użyciu AI. Obecnie istnieje niespójność w podejściu do przechowywania opisów:

1. **Normalna generacja** (`RealGenerateMovieJob::persistDescription()`): używa **upsert** - zastępuje istniejący opis dla kombinacji `(movie_id, locale, context_tag)`
2. **Regeneracja** (`RegenerateMovieDescriptionJob`): używa **wersjonowania** - archiwizuje stary opis i tworzy nowy z incremented `version_number`

Dodatkowo, w bazie danych istnieje migracja `2025_12_20_151647_add_versioning_to_movie_descriptions_table.php`, która dodaje pola `version_number` i `archived_at`, ale nie jest w pełni wykorzystywana.

## 📋 Analiza opcji

### Opcja 1: Utrzymać obecne podejście (upsert) ✅ **WYBRANA**

**Mechanizm:**
- Jeden aktywny opis na kombinację `(movie_id, locale, context_tag)`
- Nowa generacja zastępuje istniejący opis
- Unikalność: `UNIQUE (movie_id, locale, context_tag)`

**Zalety:**
- ✅ Prosta implementacja - jeden rekord na kombinację
- ✅ Mniejsze zużycie miejsca - brak historii
- ✅ Szybsze zapytania - prostsze indeksy
- ✅ Spójność - zawsze najnowsza wersja
- ✅ Łatwiejsze cache - jeden klucz cache per kombinacja

**Wady:**
- ❌ Brak historii - nie można cofnąć do poprzedniej wersji
- ❌ Brak audytu - nie widać zmian w czasie
- ❌ Utrata danych - stary opis jest tracony
- ❌ Brak porównań - nie można porównać wersji

### Opcja 2: Pełne wersjonowanie

**Mechanizm:**
- Wszystkie wersje opisów są zachowane
- Archiwizacja starych wersji (`archived_at IS NOT NULL`)
- Unikalność: `UNIQUE (movie_id, locale, context_tag) WHERE archived_at IS NULL`

**Zalety:**
- ✅ Historia zmian - wszystkie wersje zachowane
- ✅ Możliwość rollbacku - powrót do poprzedniej wersji
- ✅ Audyt - śledzenie zmian w czasie
- ✅ Porównywanie wersji - analiza jakości
- ✅ Spójność z `RegenerateMovieDescriptionJob`

**Wady:**
- ❌ Większe zużycie miejsca - więcej rekordów
- ❌ Złożoność zapytań - filtrowanie po `archived_at IS NULL`
- ❌ Złożoność cache - potrzeba uwzględnienia wersji
- ❌ Migracja danych - konwersja istniejących rekordów
- ❌ Zmiany w API - nowe parametry (`version`, `history`)

### Opcja 3: Hybrydowe podejście

**Mechanizm:**
- Upsert dla normalnej generacji
- Wersjonowanie tylko dla regeneracji (już zaimplementowane)
- Feature flag `description_versioning` do kontroli

**Zalety:**
- ✅ Zachowuje prostotę dla normalnej generacji
- ✅ Wersjonowanie dla regeneracji (gdy potrzebne)

**Wady:**
- ❌ Niespójność - różne podejścia dla różnych scenariuszy
- ❌ Złożoność - trzeba zarządzać dwoma mechanizmami

## ✅ Decyzja

**Wybrano: Opcja 1 - Utrzymać obecne podejście (upsert)**

### Uzasadnienie

1. **Faza projektu:** System jest w fazie MVP → produkcja, gdzie priorytetem jest prostota i wydajność
2. **Brak wymagań:** Obecnie nie ma wymagań biznesowych dotyczących historii zmian opisów
3. **Prostota:** Upsert jest prostszy w implementacji i utrzymaniu
4. **Wydajność:** Mniejsze zużycie miejsca i szybsze zapytania
5. **Spójność:** Uproszczenie kodu - usunięcie niespójności między normalną generacją a regeneracją

### Konsekwencje

1. **Kod:**
   - Utrzymać `persistDescription()` z upsert
   - Uprościć `RegenerateMovieDescriptionJob` - użyć upsert zamiast wersjonowania
   - Opcjonalnie: usunąć pola `version_number` i `archived_at` z modeli (lub pozostawić dla przyszłości)

2. **Baza danych:**
   - Utrzymać unikalność: `UNIQUE (movie_id, locale, context_tag)`
   - Pola `version_number` i `archived_at` mogą pozostać (dla przyszłości), ale nie są używane

3. **API:**
   - Brak zmian w API
   - Brak parametrów `version` lub `history`

4. **Cache:**
   - Brak zmian - jeden klucz cache per kombinacja

## 🔄 Plan migracji (przyszłość)

Jeśli w przyszłości pojawi się potrzeba pełnego wersjonowania, plan migracji:

1. **Faza 1: Przygotowanie**
   - Dodać feature flag `description_versioning`
   - Przygotować migrację danych (opcjonalnie)

2. **Faza 2: Implementacja**
   - Zmienić `persistDescription()` na wersjonowanie (archiwizacja + nowy rekord)
   - Dodać cleanup - automatyczne usuwanie starych wersji (np. starszych niż 6 miesięcy)
   - Rozszerzyć API - parametry `?version=X` i `?history=true`

3. **Faza 3: Cache i optymalizacja**
   - Zaktualizować cache - uwzględnienie wersji w kluczach
   - Dodać endpointy - historia wersji, porównywanie, rollback

4. **Faza 4: Wdrożenie**
   - Włączyć feature flag na stagingu
   - Testy i monitoring
   - Rollout na produkcję

## 📌 Warunki zmiany na pełne wersjonowanie

Pełne wersjonowanie powinno być rozważone, gdy:

1. ✅ Pojawi się wymaganie biznesowe dotyczące historii zmian
2. ✅ Potrzebny będzie audyt zmian opisów
3. ✅ Użytkownicy będą potrzebować możliwości rollbacku
4. ✅ System będzie stabilny i gotowy na większą złożoność
5. ✅ Będzie wystarczająca pojemność bazy danych

## 🔗 Powiązane dokumenty

- [TASK-031](../issue/pl/TASKS.md#task-031)
- [TASK-012](../issue/pl/TASKS.md#task-012) - Lock + Multi-Description Handling
- [TASK-024](../issue/pl/TASKS.md#task-024) - Baseline Locking Plan
- [AI Baseline Locking Plan](./AI_BASELINE_LOCKING_PLAN.md)
- [AI Baseline Locking Rollout](./AI_BASELINE_LOCKING_ROLLOUT.md)

## 📝 Notatki implementacyjne

### Obecna implementacja

**Normalna generacja:**
```php
// api/app/Jobs/RealGenerateMovieJob.php:467-486
private function persistDescription(...) {
    $existing = MovieDescription::where(...)->first();
    if ($existing) {
        $existing->fill($attributes);
        $existing->save(); // ZASTĘPUJE istniejący
        return $existing->fresh();
    }
    return MovieDescription::create(...);
}
```

**Regeneracja (niespójne):**
```php
// api/app/Jobs/RegenerateMovieDescriptionJob.php:105-126
// Archive old description (versioning)
$description->update(['archived_at' => now()]);
// Create new description with incremented version number
$newDescription = MovieDescription::create([
    'version_number' => $maxVersion + 1,
    ...
]);
```

### Rekomendowane zmiany (opcjonalne)

1. **Uprościć `RegenerateMovieDescriptionJob`:**
   - Użyć upsert zamiast archiwizacji
   - Usunąć logikę `version_number`

2. **Opcjonalnie usunąć pola z modeli:**
   - `version_number` i `archived_at` z `fillable` (lub pozostawić dla przyszłości)

3. **Dokumentacja:**
   - Zaktualizować dokumentację API - opisanie podejścia upsert
   - Dodać notatkę o możliwości przyszłego wersjonowania

---

**Ostatnia aktualizacja:** 2025-01-27  
**Status decyzji:** ✅ Zatwierdzona - Opcja 1 (upsert)

