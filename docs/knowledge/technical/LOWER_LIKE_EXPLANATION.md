# 🔍 LOWER() LIKE - Wyjaśnienie

**Data:** 2025-01-27  
**Kontekst:** Naprawa kompatybilności SQLite w PersonRepository i MovieRepository

---

## 📋 **Co to jest LOWER() LIKE?**

**`LOWER() LIKE`** to technika SQL do **case-insensitive** (niewrażliwej na wielkość liter) wyszukiwania, która działa z **wszystkimi bazami danych** (SQLite, PostgreSQL, MySQL, MariaDB).

### **Składnia:**
```sql
WHERE LOWER(column_name) LIKE LOWER('%search_term%')
```

---

## 🎯 **Jak to działa?**

### **Krok po kroku:**

1. **`LOWER()`** - konwertuje tekst na małe litery
2. **`LIKE`** - wyszukuje wzorzec (z wildcards: `%` i `_`)
3. **Porównanie** - porównuje dwa teksty w małych literach

### **Przykład:**

```sql
-- Wyszukiwanie "Christopher" (case-insensitive)
SELECT * FROM people 
WHERE LOWER(name) LIKE LOWER('%christopher%');
```

**Co się dzieje:**
1. `LOWER(name)` → konwertuje "Christopher Nolan" na "christopher nolan"
2. `LOWER('%christopher%')` → konwertuje wzorzec na "%christopher%"
3. Porównanie: "christopher nolan" LIKE "%christopher%" → **TRUE** ✅

---

## 🔄 **Porównanie z ILIKE**

### **ILIKE (PostgreSQL-specific):**

```sql
-- PostgreSQL - działa ✅
WHERE name ILIKE '%christopher%'

-- SQLite - błąd ❌
-- SQLSTATE[HY000]: General error: 1 near "ILIKE": syntax error
```

**Wady:**
- ❌ Tylko PostgreSQL (i niektóre inne)
- ❌ SQLite **nie obsługuje** ILIKE
- ❌ MySQL **nie obsługuje** ILIKE
- ❌ Nie przenośne między bazami danych

---

### **LOWER() LIKE (Universal):**

```sql
-- Wszystkie bazy danych - działa ✅
WHERE LOWER(name) LIKE LOWER('%christopher%')
```

**Zalety:**
- ✅ **SQLite** - działa
- ✅ **PostgreSQL** - działa
- ✅ **MySQL** - działa
- ✅ **MariaDB** - działa
- ✅ **Przenośne** między bazami danych

---

## 💻 **Przykłady w kodzie**

### **Przed (ILIKE - nie działa z SQLite):**

```php
// ❌ Nie działa z SQLite
$builder->where('name', 'ILIKE', "%$query%")
    ->orWhere('birthplace', 'ILIKE', "%$query%");
```

**SQL wygenerowany:**
```sql
WHERE name ILIKE '%christopher%' 
   OR birthplace ILIKE '%christopher%'
```

**Błąd w SQLite:**
```
SQLSTATE[HY000]: General error: 1 near "ILIKE": syntax error
```

---

### **Po (LOWER() LIKE - działa wszędzie):**

```php
// ✅ Działa z SQLite i PostgreSQL
$builder->whereRaw('LOWER(name) LIKE ?', [strtolower("%$query%")])
    ->orWhereRaw('LOWER(birthplace) LIKE ?', [strtolower("%$query%")]);
```

**SQL wygenerowany:**
```sql
WHERE LOWER(name) LIKE '%christopher%' 
   OR LOWER(birthplace) LIKE '%christopher%'
```

**Działa w SQLite:** ✅  
**Działa w PostgreSQL:** ✅

---

## 🔍 **Szczegółowe Wyjaśnienie**

### **1. Funkcja LOWER()**

**Definicja:**
- `LOWER(string)` - konwertuje wszystkie znaki w stringu na małe litery

**Przykłady:**
```sql
LOWER('Christopher')      → 'christopher'
LOWER('NOLAN')            → 'nolan'
LOWER('John Doe')         → 'john doe'
LOWER('The Matrix 1999')  → 'the matrix 1999'
```

### **2. Operator LIKE**

**Definicja:**
- `LIKE pattern` - wyszukuje tekst pasujący do wzorca
- `%` - wildcard (dowolna liczba znaków)
- `_` - wildcard (jeden znak)

**Przykłady:**
```sql
'christopher' LIKE '%christopher%'  → TRUE
'Christopher' LIKE '%christopher%'  → FALSE (case-sensitive!)
'John' LIKE 'Jo%'                   → TRUE
'John' LIKE 'Jo_'                   → TRUE
```

### **3. LOWER() + LIKE = Case-Insensitive**

**Kombinacja:**
```sql
LOWER('Christopher') LIKE LOWER('%christopher%')
-- 'christopher' LIKE '%christopher%' → TRUE ✅
```

**Teraz:**
- ✅ `'Christopher'` → `'christopher'`
- ✅ `'%christopher%'` → `'%christopher%'`
- ✅ Porównanie: `'christopher' LIKE '%christopher%'` → **TRUE**

---

## 📊 **Tabela Porównawcza**

| Metoda | SQLite | PostgreSQL | MySQL | MariaDB | Przenośność |
|--------|--------|------------|-------|---------|-------------|
| **ILIKE** | ❌ | ✅ | ❌ | ❌ | Niska |
| **LIKE** | ✅ | ✅ | ✅ | ✅ | Wysoka (ale case-sensitive) |
| **LOWER() LIKE** | ✅ | ✅ | ✅ | ✅ | **Wysoka (case-insensitive)** |

---

## 🎯 **Implementacja w Laravel**

### **Wariant 1: whereRaw() (używane w projekcie)**

```php
$builder->whereRaw('LOWER(name) LIKE ?', [strtolower("%$query%")])
    ->orWhereRaw('LOWER(birthplace) LIKE ?', [strtolower("%$query%")]);
```

**Zalety:**
- ✅ Pełna kontrola nad SQL
- ✅ Działa z wszystkimi bazami danych
- ✅ Bezpieczne (parametryzowane zapytania)

**Wady:**
- ⚠️ Trzeba ręcznie dodać `strtolower()` w PHP

---

### **Wariant 2: whereRaw() z DB::raw()**

```php
use Illuminate\Support\Facades\DB;

$builder->whereRaw('LOWER(name) LIKE ?', [DB::raw("LOWER('%$query%')")]);
```

**Zalety:**
- ✅ LOWER() w SQL (nie w PHP)

**Wady:**
- ⚠️ Ryzyko SQL injection jeśli nie użyjemy parametrów

---

### **Wariant 3: Eloquent Scope (alternatywa)**

```php
// W modelu Person
public function scopeWhereCaseInsensitive($query, $column, $value)
{
    return $query->whereRaw('LOWER(?) LIKE ?', [
        DB::raw($column),
        strtolower("%$value%")
    ]);
}

// Użycie:
Person::whereCaseInsensitive('name', 'christopher')->get();
```

**Zalety:**
- ✅ Reusable (używalne wielokrotnie)
- ✅ Czytelne
- ✅ Encapsulated (enkapsulowane)

---

## 🚀 **Optymalizacja Wydajności**

### **Wydajność LOWER() LIKE:**

**Potencjalny problem:**
- `LOWER()` może **spowolnić** zapytania na dużych tabelach
- Nie można użyć **indeksów** bezpośrednio na `LOWER(column)`

**Rozwiązanie dla PostgreSQL:**
```sql
-- Utworzenie indeksu na LOWER(column)
CREATE INDEX idx_people_name_lower ON people (LOWER(name));
```

**SQLite:**
- SQLite automatycznie optymalizuje `LOWER()` w niektórych przypadkach
- Dla większych tabel można użyć **triggers** do utrzymania kolumny `name_lower`

---

## 💡 **Alternatywy dla Case-Insensitive Search**

### **1. Collation (PostgreSQL, MySQL)**

```sql
-- PostgreSQL
WHERE name ILIKE '%christopher%' COLLATE "C"

-- MySQL
WHERE name LIKE '%christopher%' COLLATE utf8_general_ci
```

**Wady:**
- ❌ Nie działa z SQLite
- ❌ Wymaga konfiguracji collation

---

### **2. LIKE z COLLATE (MySQL)**

```sql
WHERE name LIKE '%christopher%' COLLATE utf8_general_ci
```

**Wady:**
- ❌ Nie działa z SQLite
- ❌ Nie działa z PostgreSQL (ILIKE jest lepsze)

---

### **3. Full-Text Search (zaawansowane)**

```sql
-- PostgreSQL
WHERE to_tsvector('english', name) @@ to_tsquery('christopher')

-- SQLite
WHERE name MATCH 'christopher'
```

**Zalety:**
- ✅ Bardzo szybkie
- ✅ Zaawansowane wyszukiwanie

**Wady:**
- ❌ Wymaga konfiguracji indeksów FTS
- ❌ Różne składnie dla różnych baz danych

---

## 🎯 **Dlaczego LOWER() LIKE w tym projekcie?**

### **Kontekst:**
- **Development:** SQLite (in-memory dla testów)
- **Production:** PostgreSQL (prawdopodobnie)
- **CI/CD:** SQLite (szybkie testy)

### **Wymagania:**
1. ✅ Działa z SQLite (testy)
2. ✅ Działa z PostgreSQL (production)
3. ✅ Case-insensitive search
4. ✅ Prosta implementacja

### **Rozwiązanie:**
**`LOWER() LIKE`** - spełnia wszystkie wymagania! ✅

---

## 📝 **Przykłady z Projektu**

### **PersonRepository:**

```php
public function searchPeople(?string $query, ?string $role = null, int $limit = 50): Collection
{
    return Person::query()
        ->when($query, function ($builder) use ($query) {
            // Use LOWER() for case-insensitive search (works with both SQLite and PostgreSQL)
            $builder->whereRaw('LOWER(name) LIKE ?', [strtolower("%$query%")])
                ->orWhereRaw('LOWER(birthplace) LIKE ?', [strtolower("%$query%")]);
        })
        // ...
}
```

**Co robi:**
1. Jeśli `$query = "Christopher"`:
   - `LOWER(name) LIKE '%christopher%'`
   - Znajdzie: "Christopher Nolan", "christopher nolan", "CHRISTOPHER NOLAN"
2. Jeśli `$query = "nolan"`:
   - `LOWER(name) LIKE '%nolan%'`
   - Znajdzie: "Christopher Nolan", "Nolan", "nolan"

---

### **MovieRepository:**

```php
public function searchMovies(?string $query, int $limit = 50): Collection
{
    return Movie::query()
        ->when($query, function ($builder) use ($query) {
            // Use LOWER() for case-insensitive search (works with both SQLite and PostgreSQL)
            $builder->whereRaw('LOWER(title) LIKE ?', [strtolower("%$query%")])
                ->orWhereRaw('LOWER(director) LIKE ?', [strtolower("%$query%")])
                ->orWhereHas('genres', function ($qg) use ($query) {
                    $qg->whereRaw('LOWER(name) LIKE ?', [strtolower("%$query%")]);
                });
        })
        // ...
}
```

**Co robi:**
1. Wyszukuje w `title` (case-insensitive)
2. Wyszukuje w `director` (case-insensitive)
3. Wyszukuje w `genres.name` (case-insensitive)

---

## 🔍 **Testowanie**

### **Przykładowe zapytania:**

```php
// Test 1: Case-insensitive
$people = PersonRepository::searchPeople('christopher');
// Znajdzie: "Christopher Nolan", "christopher nolan", "CHRISTOPHER NOLAN"

// Test 2: Partial match
$people = PersonRepository::searchPeople('nolan');
// Znajdzie: "Christopher Nolan", "Nolan", "nolan"

// Test 3: Multiple words
$people = PersonRepository::searchPeople('john');
// Znajdzie: "John Doe", "Johnny Depp", "john smith"
```

---

## ⚠️ **Uwagi i Ograniczenia**

### **1. Wydajność na dużych tabelach:**

**Problem:**
- `LOWER()` może spowolnić zapytania jeśli nie ma indeksów

**Rozwiązanie:**
- Dla PostgreSQL: utwórz indeks na `LOWER(column)`
- Dla SQLite: użyj triggers do utrzymania kolumny `column_lower`
- Dla dużych tabel: rozważ Full-Text Search

---

### **2. Unicode i Collation:**

**Problem:**
- `LOWER()` może nie działać poprawnie dla wszystkich języków (np. turecki)

**Rozwiązanie:**
- Użyj collation specyficznego dla języka jeśli potrzebne
- Dla większości przypadków `LOWER()` jest wystarczające

---

### **3. SQL Injection:**

**Zawsze używaj parametrów:**

```php
// ✅ DOBRZE - bezpieczne
$builder->whereRaw('LOWER(name) LIKE ?', [strtolower("%$query%")]);

// ❌ ŹLE - ryzyko SQL injection
$builder->whereRaw("LOWER(name) LIKE '%$query%'");
```

---

## 🎯 **Podsumowanie**

### **LOWER() LIKE:**
- ✅ **Case-insensitive** wyszukiwanie
- ✅ **Przenośne** między bazami danych (SQLite, PostgreSQL, MySQL)
- ✅ **Proste** w implementacji
- ✅ **Bezpieczne** z parametrami

### **Kiedy używać:**
- ✅ Gdy potrzebujesz case-insensitive search
- ✅ Gdy wspierasz wiele baz danych
- ✅ Gdy prostota jest ważniejsza niż wydajność (dla małych/średnich tabel)

### **Kiedy nie używać:**
- ❌ Gdy potrzebujesz bardzo szybkiego wyszukiwania na dużych tabelach
- ❌ Gdy potrzebujesz zaawansowanego Full-Text Search
- ❌ Gdy możesz użyć native ILIKE (tylko PostgreSQL)

---

**Ostatnia aktualizacja:** 2025-01-27

