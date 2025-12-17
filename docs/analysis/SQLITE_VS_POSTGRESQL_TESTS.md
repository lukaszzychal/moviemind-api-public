# SQLite vs PostgreSQL dla testów - Analiza

## 📊 Porównanie

### SQLite (obecne rozwiązanie)

#### ✅ Zalety:
- **Szybkość** - in-memory database, bardzo szybkie testy
- **Prostota** - nie wymaga zewnętrznej bazy danych
- **Zero konfiguracji** - działa out-of-the-box
- **Lekkość** - minimalne zasoby systemowe
- **CI/CD friendly** - łatwe uruchomienie w CI bez dodatkowej konfiguracji

#### ❌ Wady:
- **Ograniczenia SQL** - brak niektórych funkcji (np. `REGEXP_REPLACE`, `ILIKE`)
- **Różnice z produkcją** - PostgreSQL ma inne funkcje SQL
- **Brak zaawansowanych typów** - brak array, JSONB, etc.
- **Różne zachowania** - niektóre zapytania działają inaczej niż w PostgreSQL

### PostgreSQL (propozycja)

#### ✅ Zalety:
- **Zgodność z produkcją** - identyczne zapytania SQL
- **Pełne funkcje SQL** - wszystkie funkcje PostgreSQL dostępne
- **Zaawansowane typy** - array, JSONB, full-text search
- **Lepsze testowanie** - testy weryfikują rzeczywiste zapytania produkcyjne
- **Brak niespodzianek** - to co działa w testach, działa w produkcji

#### ❌ Wady:
- **Wolniejsze testy** - wymaga zewnętrznej bazy danych
- **Większa konfiguracja** - trzeba skonfigurować PostgreSQL w CI/CD
- **Większe zużycie zasobów** - więcej pamięci i CPU
- **Złożoność CI/CD** - trzeba dodać PostgreSQL do pipeline'u

## 🎯 Rekomendacja

### Opcja A: Pozostać przy SQLite (REKOMENDOWANE)

**Dlaczego:**
1. **Szybkość testów** - kluczowa dla TDD workflow
2. **Prostota** - łatwiejsze utrzymanie
3. **Wystarczające** - większość zapytań działa tak samo
4. **CI/CD** - zero dodatkowej konfiguracji

**Kiedy użyć PostgreSQL:**
- Tylko dla testów integracyjnych wymagających zaawansowanych funkcji SQL
- Dla testów wydajnościowych (performance tests)
- Dla testów migracji (migration tests)

### Opcja B: Hybrydowe podejście

**Struktura:**
- **SQLite** - dla większości testów (Unit + większość Feature)
- **PostgreSQL** - dla wybranych testów integracyjnych (oznaczone tagiem `@requires-postgresql`)

**Implementacja:**
```php
// W phpunit.xml.dist
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
        <env name="DB_CONNECTION" value="pgsql"/>
    </testsuite>
</testsuites>
```

### Opcja C: Przejść na PostgreSQL

**Kiedy rozważyć:**
- Jeśli często napotykamy problemy z różnicami SQLite vs PostgreSQL
- Jeśli potrzebujemy zaawansowanych funkcji SQL w testach
- Jeśli mamy dedykowany CI/CD z PostgreSQL

**Wymagania:**
- Konfiguracja PostgreSQL w CI/CD
- Docker Compose dla lokalnych testów
- Dłuższy czas wykonania testów

## 📝 Aktualne problemy z SQLite

### 1. Brak `REGEXP_REPLACE`
**Problem:** Nie można użyć zaawansowanych funkcji regexp  
**Rozwiązanie:** Używać prostszych zapytań SQL (jak w `findAllByTitleSlug`)

### 2. Różnice w `LIKE` vs `ILIKE`
**Problem:** SQLite nie ma `ILIKE` (case-insensitive LIKE)  
**Rozwiązanie:** Używać `LOWER()` w obu bazach dla spójności

### 3. Różne zachowania zapytań
**Problem:** Niektóre zapytania działają inaczej  
**Rozwiązanie:** Testować na obu bazach w CI/CD (opcjonalnie)

## 🔧 Aktualne rozwiązanie

Używamy **SQLite dla testów** z następującymi praktykami:

1. **Unikanie funkcji specyficznych dla PostgreSQL** w kodzie testowanym
2. **Używanie `LOWER()` zamiast `ILIKE`** dla case-insensitive search
3. **Proste zapytania SQL** zamiast zaawansowanych funkcji
4. **Testy integracyjne** na PostgreSQL w CI/CD (opcjonalnie)

## 💡 Rekomendacja końcowa

**Pozostać przy SQLite** z następującymi ulepszeniami:

1. ✅ Uprościć zapytania SQL (jak w `findAllByTitleSlug`)
2. ✅ Dodać testy integracyjne na PostgreSQL w CI/CD (opcjonalnie)
3. ✅ Dokumentować różnice między SQLite a PostgreSQL
4. ✅ Używać abstrakcji Eloquent zamiast raw SQL gdzie to możliwe

**Alternatywa:** Jeśli często napotykamy problemy z różnicami, rozważyć hybrydowe podejście (Opcja B).

---

**Ostatnia aktualizacja:** 2024-12-17

