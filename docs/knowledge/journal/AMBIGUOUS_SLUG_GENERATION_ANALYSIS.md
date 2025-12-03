# Analiza: Niejednoznaczne slugi podczas generowania przez AI

> **Data utworzenia:** 2025-01-09  
> **Kontekst:** Analiza obsługi niejednoznacznych slugów podczas generowania opisów przez AI  
> **Kategoria:** journal

## Problem

Sprawdzenie co się dzieje gdy AI generuje film z slugiem który pasuje do kilku istniejących filmów (niejednoznaczny slug).

## Analiza obecnego zachowania

### RealGenerateMovieJob::findExistingMovie()

**Przed zmianami:**
```php
private function findExistingMovie(): ?Movie
{
    if ($this->existingMovieId !== null) {
        $movie = Movie::with('descriptions')->find($this->existingMovieId);
        if ($movie) {
            return $movie;
        }
    }

    return Movie::with('descriptions')->where('slug', $this->slug)->first();
}
```

**Problem:** Sprawdza tylko exact match (`where('slug', $this->slug)`). Jeśli slug jest niejednoznaczny (np. "bad-boys" bez roku), a w bazie są "bad-boys-1995" i "bad-boys-2020", nie znajdzie żadnego filmu.

### RealGenerateMovieJob::createMovieRecord()

**Przed zmianami:**
```php
$movie = Movie::create([
    'title' => (string) $title,
    'slug' => $this->slug,  // ❌ Używa slug z requestu bezpośrednio!
    'release_year' => $releaseYear,
    'director' => $director,
    'genres' => $genres,
]);
```

**Problem:** 
- Używa `$this->slug` z requestu bezpośrednio
- **NIE używa** `Movie::generateSlug()` do generowania unikalnego slug z danych AI
- Może próbować utworzyć film z slugiem który już istnieje (unique constraint violation)

### Movie::generateSlug()

Metoda istnieje i generuje unikalny slug z tytułu, roku i reżysera, ale **nie była używana w jobach**.

## Rozwiązanie

### Zmiany w kodzie

1. **RealGenerateMovieJob::findExistingMovie()** - dodano obsługę niejednoznacznych slugów:
   - Sprawdza exact match (`where('slug', $this->slug)`)
   - Jeśli slug nie zawiera roku, sprawdza slugi pasujące do tytułu (`whereRaw('slug LIKE ?', ["{$titleSlug}%"])`)
   - Zwraca najnowszy film (`orderBy('release_year', 'desc')`)
   - Zgodne z zachowaniem `MovieRepository::findBySlugWithRelations()`

2. **RealGenerateMovieJob::createMovieRecord()** - używa `Movie::generateSlug()`:
   - Zamiast używać slug z requestu bezpośrednio (`$this->slug`)
   - Generuje unikalny slug z danych AI (`Movie::generateSlug($title, $releaseYear, $director)`)
   - Zapewnia unikalność i zapobiega konfliktom

3. **MockGenerateMovieJob** - analogiczne zmiany:
   - `findExistingMovie()` obsługuje niejednoznaczne slugi
   - `createMovieRecord()` używa `Movie::generateSlug()`

### Testy

- Utworzono `AmbiguousSlugGenerationTest` z testami feature:
  - `test_generation_with_ambiguous_slug_finds_existing_movie()` - sprawdza czy używa istniejącego filmu
  - `test_generation_with_exact_slug_uses_existing_movie()` - sprawdza jednoznaczny slug
  - `test_generation_uses_generated_slug_from_ai_data()` - sprawdza użycie generateSlug
  - `test_ambiguous_slug_returns_most_recent_movie()` - sprawdza GET endpoint z _meta
- Zaktualizowano `GenerateMovieJobTest` aby uwzględniał użycie `Movie::generateSlug()`
- Wszystkie testy przechodzą (144 passed)

### Dokumentacja

- Zaktualizowano `MANUAL_TESTING_GUIDE.md` o sekcję "Test 9: Sprawdzenie Obsługi Niejednoznacznych Slugów podczas Generowania"
- Utworzono `AMBIGUOUS_SLUG_GENERATION_ANALYSIS.md` z analizą problemu

## Weryfikacja

✅ System używa `Movie::generateSlug()` podczas generowania  
✅ System zapobiega duplikatom tytułów  
✅ System używa istniejącego filmu gdy slug jest niejednoznaczny (najnowszy)  
✅ Wszystkie testy automatyczne przechodzą (144 passed)  
✅ PHPStan bez błędów (level 5)  
✅ Laravel Pint sformatowany kod  
✅ Dokumentacja zaktualizowana

## Scenariusze przetestowane

1. ✅ **Slug bez roku pasujący do wielu filmów** → używa istniejącego filmu (najnowszego)
2. ✅ **Slug z rokiem pasujący do jednego filmu** → używa istniejącego
3. ✅ **Slug bez roku pasujący do jednego filmu** → używa istniejącego
4. ✅ **Slug który nie istnieje** → generuje unikalny slug z danych AI

## Pliki zmienione

- `api/app/Jobs/RealGenerateMovieJob.php` - dodano obsługę niejednoznacznych slugów i użycie `Movie::generateSlug()`
- `api/app/Jobs/MockGenerateMovieJob.php` - analogiczne zmiany
- `api/tests/Feature/AmbiguousSlugGenerationTest.php` - nowe testy feature
- `api/tests/Unit/Jobs/GenerateMovieJobTest.php` - zaktualizowano testy
- `docs/knowledge/reference/MANUAL_TESTING_GUIDE.md` - dodano sekcję testowania
- `docs/knowledge/journal/AMBIGUOUS_SLUG_GENERATION_ANALYSIS.md` - dokumentacja analizy
