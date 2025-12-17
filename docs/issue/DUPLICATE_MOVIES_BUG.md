# 🐛 Bug: Duplikaty filmów dla tego samego slug

## Problem

Dla requestu `GET /api/v1/movies/the-matrix` system tworzy dwa filmy z różnymi ID pod tym samym slug.

## Analiza

### Flow problematyczny:

1. **Request 1:** `GET /api/v1/movies/the-matrix`
   - Controller sprawdza `findBySlugWithRelations('the-matrix')` → nie znajduje
   - Queue job z slug `the-matrix`

2. **Job 1:**
   - Sprawdza `findBySlugForJob('the-matrix')` → nie znajduje
   - Parsuje slug: `the-matrix` → `{title: "the matrix", year: null}`
   - Generuje NOWY slug: `the-matrix-1999` (jeśli rok jest w danych AI/TMDb)
   - Tworzy film z slug `the-matrix-1999`

3. **Request 2:** `GET /api/v1/movies/the-matrix`
   - Controller sprawdza `findBySlugWithRelations('the-matrix')` → nie znajduje (bo szuka exact match, a w bazie jest `the-matrix-1999`)
   - Queue kolejny job z slug `the-matrix`

4. **Job 2:**
   - Sprawdza `findBySlugForJob('the-matrix')` → nie znajduje (bo slug ma rok w bazie, więc LIKE match nie działa)
   - Tworzy kolejny film z slug `the-matrix-1999` (lub `the-matrix-1999-2` jeśli pierwszy już istnieje)

### Przyczyna:

**W `MockGenerateMovieJob` i `RealGenerateMovieJob`:**

```php
// Linia 128 (Mock) / 1030 (Real)
$generatedSlug = Movie::generateSlug((string) $title, $releaseYear, $director);
// ❌ Generuje NOWY slug zamiast używać slug z requestu
```

**W `findBySlugForJob()`:**

```php
// Sprawdza tylko exact match lub LIKE dla slugów bez roku
// Jeśli slug ma rok w bazie, nie znajdzie go przy sprawdzaniu slug bez roku
```

## Rozwiązania

### Rozwiązanie 1: Używać slug z requestu (ZALECANE)

**Zmiana w `createMovieRecord()`:**

```php
// Zamiast generować nowy slug, użyj slug z requestu
// Ale sprawdź czy jest unikalny przed użyciem
$slugToUse = $this->slug;

// Sprawdź czy slug z requestu jest unikalny
if (Movie::where('slug', $slugToUse)->exists()) {
    // Jeśli nie, wygeneruj unikalny
    $slugToUse = Movie::generateSlug((string) $title, $releaseYear, $director);
}

$movie = Movie::create([
    'title' => (string) $title,
    'slug' => $slugToUse, // ✅ Użyj slug z requestu jeśli możliwe
    // ...
]);
```

**Zalety:**
- ✅ Zachowuje zgodność z requestem
- ✅ Zapobiega duplikatom
- ✅ Proste w implementacji

**Wady:**
- ⚠️ Może wymagać zmiany logiki dla ambiguous slugs

### Rozwiązanie 2: Poprawić `findBySlugForJob()` (ZALECANE)

**Dodać sprawdzanie po tytule + roku:**

```php
public function findBySlugForJob(string $slug, ?int $existingId = null): ?Movie
{
    // ... existing code ...
    
    // Try exact match first
    $movie = Movie::with('descriptions')->where('slug', $slug)->first();
    if ($movie) {
        return $movie;
    }
    
    // Parse slug to get title and year
    $parsed = Movie::parseSlug($slug);
    
    // NEW: Check if movie exists by title + year (even if slug differs)
    if ($parsed['year'] !== null) {
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);
        $movie = Movie::with('descriptions')
            ->whereRaw('slug LIKE ?', ["{$titleSlug}-{$parsed['year']}%"])
            ->first();
        if ($movie) {
            return $movie;
        }
    }
    
    // If slug doesn't contain year, try to find by title only
    if ($parsed['year'] === null) {
        $titleSlug = \Illuminate\Support\Str::slug($parsed['title']);
        return Movie::with('descriptions')
            ->whereRaw('slug LIKE ?', ["{$titleSlug}%"])
            ->orderBy('release_year', 'desc')
            ->first();
    }
    
    return null;
}
```

**Zalety:**
- ✅ Znajdzie film nawet jeśli slug się różni
- ✅ Zapobiega duplikatom
- ✅ Nie wymaga zmiany logiki tworzenia

**Wady:**
- ⚠️ Może zwrócić niewłaściwy film dla bardzo podobnych tytułów

### Rozwiązanie 3: Użyć `firstOrCreate` z slug z requestu

**Zmiana w `createMovieRecord()`:**

```php
// Użyj firstOrCreate z slug z requestu
$movie = Movie::firstOrCreate(
    ['slug' => $this->slug], // ✅ Użyj slug z requestu
    [
        'title' => (string) $title,
        'release_year' => $releaseYear,
        'director' => $director,
        'genres' => $genres,
    ]
);

// Jeśli film już istnieje, użyj go
if ($movie->wasRecentlyCreated === false) {
    // Film już istnieje - użyj go zamiast tworzyć nowy
    return $this->handleExistingMovie($movie);
}
```

**Zalety:**
- ✅ Atomic operation (zapobiega race conditions)
- ✅ Proste w implementacji
- ✅ Automatycznie zapobiega duplikatom

**Wady:**
- ⚠️ Wymaga zmiany całej logiki tworzenia

## Rekomendacja

**Kombinacja Rozwiązania 1 + 2:**

1. **Używać slug z requestu** jeśli jest unikalny
2. **Poprawić `findBySlugForJob()`** żeby sprawdzał też po tytule+roku
3. **Dodać dodatkowe sprawdzenie** przed utworzeniem filmu

## Implementacja

### Krok 1: Popraw `findBySlugForJob()`

Dodać sprawdzanie po tytule + roku przed utworzeniem filmu.

### Krok 2: Zmienić `createMovieRecord()`

Używać slug z requestu zamiast generować nowy, ale sprawdzać czy jest unikalny.

### Krok 3: Dodać dodatkowe sprawdzenie

Przed utworzeniem filmu, sprawdzić czy film już istnieje używając tytułu + roku.

## Testy

1. Request `GET /api/v1/movies/the-matrix` dwa razy → powinien zwrócić ten sam film
2. Request z różnymi slugami dla tego samego filmu → powinien zwrócić ten sam film
3. Request z ambiguous slug → powinien obsłużyć disambiguation

## Priorytet

**WYSOKI** - powoduje duplikaty w bazie danych i niespójność danych.

---

## ✅ Implementacja (Rozwiązanie 1)

**Status:** ✅ Zaimplementowane

**Zmiany:**
1. ✅ Poprawiono `MovieRepository::findBySlugForJob()` - dodano sprawdzanie po tytule + roku
2. ✅ Poprawiono `PersonRepository::findBySlugForJob()` - dodano sprawdzanie po imieniu + roku urodzenia
3. ✅ Dodano sprawdzanie przed utworzeniem w jobach - zapobiega duplikatom przy race conditions

**Jak działa:**
- Jeśli slug z requestu nie ma roku, ale w bazie jest film/osoba z rokiem → znajdzie go
- Jeśli slug z requestu ma rok, sprawdza czy istnieje film/osoba z tym samym tytułem/imieniem i rokiem
- **PRZED utworzeniem** filmu/osoby, job sprawdza:
  1. Czy już istnieje z wygenerowanym slugiem
  2. Czy już istnieje po tytule/imieniu + roku/dacie urodzenia
- Zapobiega tworzeniu duplikatów nawet przy race conditions

**Pliki zmienione:**
- `api/app/Repositories/MovieRepository.php`
- `api/app/Repositories/PersonRepository.php`
- `api/app/Jobs/MockGenerateMovieJob.php`
- `api/app/Jobs/RealGenerateMovieJob.php`
- `api/app/Jobs/MockGeneratePersonJob.php`
- `api/app/Jobs/RealGeneratePersonJob.php`

**Data implementacji:** 2025-01-15

## 🔄 Restart kontenerów wymagany

**WAŻNE:** Po zmianach w kodzie, musisz zrestartować kontenery Docker, aby nowy kod został załadowany:

```bash
# Restart kontenerów PHP i Horizon (gdzie działają joby)
docker compose restart php horizon

# Lub pełny restart
docker compose restart

# Lub przebuduj kontenery (jeśli potrzebne)
docker compose up -d --build
```

**Dlaczego?**
- Kontenery PHP i Horizon ładują kod przy starcie
- Zmiany w plikach PHP wymagają restartu kontenerów
- Cache opcode (OPcache) może przechowywać stary kod

