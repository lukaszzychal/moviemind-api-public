# 🔍 Analiza Brakujących Testów - MovieMind API

**Data analizy:** 2025-01-XX  
**Cel:** Identyfikacja luk w pokryciu testami i ważnych scenariuszy, które nie są testowane

---

## 📊 Podsumowanie

### Pokrycie testami (szacunkowe)
- **Feature Tests:** ~25 testów
- **Unit Tests (Services):** ~10 testów
- **Główne luki:** Edge cases, error handling, integracja między serwisami

---

## 🚨 Krytyczne Brakujące Testy

### 1. MovieSearchService - Filtrowanie

#### ❌ Brakuje: Filtrowanie po director dla wyników TMDB
**Scenariusz:** `searchTmdb()` nie filtruje wyników po `director` - tylko lokalne wyniki są filtrowane.

**Test potrzebny:**
```php
public function test_search_filters_tmdb_results_by_director(): void
{
    // TMDB zwraca filmy różnych reżyserów
    // Filtrowanie po director powinno działać dla wyników z TMDB
}
```

#### ❌ Brakuje: Filtrowanie po actor dla wyników TMDB
**Scenariusz:** `searchTmdb()` nie filtruje wyników po `actor` - tylko lokalne wyniki są filtrowane.

**Test potrzebny:**
```php
public function test_search_filters_tmdb_results_by_actor(): void
{
    // TMDB zwraca filmy z różnymi aktorami
    // Filtrowanie po actor powinno działać dla wyników z TMDB
}
```

#### ❌ Brakuje: Kombinacja filtrów (year + director + actor)
**Scenariusz:** Użycie wielu filtrów jednocześnie.

**Test potrzebny:**
```php
public function test_search_with_multiple_filters_combines_correctly(): void
{
    // year=1999 + director=Wachowski + actor=Keanu
    // Powinno zwrócić tylko filmy pasujące do WSZYSTKICH filtrów
}
```

---

### 2. MovieSearchService - Edge Cases

#### ❌ Brakuje: TMDB zwraca null/empty release_date
**Scenariusz:** TMDB może zwrócić film bez `release_date` lub z pustym stringiem.

**Test potrzebny:**
```php
public function test_search_handles_tmdb_movie_without_release_date(): void
{
    // TMDB zwraca film z release_date = null lub ''
    // System powinien obsłużyć to gracefully (year = null)
}
```

#### ❌ Brakuje: TMDB zwraca nieprawidłowy format release_date
**Scenariusz:** `release_date` może być w nieoczekiwanym formacie.

**Test potrzebny:**
```php
public function test_search_handles_invalid_release_date_format(): void
{
    // release_date = "1999" (bez daty) lub "invalid"
    // extractYearFromReleaseDate() powinno zwrócić null lub obsłużyć błąd
}
```

#### ❌ Brakuje: TMDB search throws exception
**Scenariusz:** TMDB API może zwrócić błąd (timeout, 500, rate limit).

**Test potrzebny:**
```php
public function test_search_handles_tmdb_api_error_gracefully(): void
{
    // TMDB throws exception
    // System powinien zwrócić tylko lokalne wyniki (nie crashować)
}
```

#### ❌ Brakuje: Duplikaty między lokalnymi a TMDB wynikami
**Scenariusz:** Ten sam film istnieje lokalnie i w TMDB (różne identyfikatory).

**Test potrzebny:**
```php
public function test_search_removes_duplicates_between_local_and_tmdb(): void
{
    // Lokalny film: "The Matrix" (1999)
    // TMDB zwraca: "The Matrix" (1999)
    // Powinien być tylko jeden wynik (lokalny ma priorytet)
}
```

#### ❌ Brakuje: Paginacja z filtrowaniem
**Scenariusz:** Paginacja + filtry (year, director, actor).

**Test potrzebny:**
```php
public function test_search_pagination_with_filters(): void
{
    // year=1999 + page=2 + per_page=10
    // Powinno zwrócić drugą stronę przefiltrowanych wyników
}
```

---

### 3. MovieRetrievalService - Edge Cases

#### ❌ Brakuje: Slug bez roku (ambiguous) - zwraca najnowszy film
**Scenariusz:** Slug "the-matrix" (bez roku) - powinien zwrócić najnowszy film z 200 statusem.

**Test potrzebny:**
```php
public function test_retrieve_movie_ambiguous_slug_returns_most_recent(): void
{
    // Slug: "the-matrix" (bez roku)
    // Istnieją: "the-matrix-1999" i "the-matrix-2021"
    // Powinien zwrócić 200 z najnowszym filmem (2021)
}
```

#### ❌ Brakuje: description_id nie istnieje dla filmu
**Scenariusz:** UUID `description_id` istnieje, ale nie należy do tego filmu.

**Test potrzebny:**
```php
public function test_retrieve_movie_with_invalid_description_id(): void
{
    // description_id istnieje, ale należy do innego filmu
    // Powinien zwrócić błąd 422 lub domyślny opis
}
```

#### ❌ Brakuje: TMDB zwraca film z niepasującym rokiem
**Scenariusz:** Slug ma rok 1999, ale TMDB zwraca film z 2000.

**Test potrzebny:**
```php
public function test_retrieve_movie_year_mismatch_handles_correctly(): void
{
    // Slug: "the-matrix-1999"
    // TMDB zwraca film z 2000
    // Powinien zwrócić odpowiedni komunikat błędu lub disambiguation
}
```

#### ❌ Brakuje: Cache invalidation po refresh
**Scenariusz:** Po `refresh()` cache powinien być invalidowany.

**Test potrzebny:**
```php
public function test_retrieve_movie_cache_invalidated_after_refresh(): void
{
    // 1. GET /movies/the-matrix (cache)
    // 2. POST /movies/the-matrix/refresh
    // 3. GET /movies/the-matrix (powinien być fresh, nie z cache)
}
```

---

### 4. MovieController - Endpointy

#### ❌ Brakuje: GET /movies/{slug}/related - edge cases
**Scenariusz:** Film bez relacji, relacje z różnymi typami.

**Test potrzebny:**
```php
public function test_movie_related_returns_empty_when_no_relationships(): void
public function test_movie_related_filters_by_relationship_type(): void
public function test_movie_related_handles_circular_relationships(): void
```

#### ❌ Brakuje: POST /movies/{slug}/refresh - edge cases
**Scenariusz:** Film bez snapshot, snapshot bez TMDB ID, TMDB API error.

**Test potrzebny:**
```php
public function test_refresh_movie_without_snapshot_returns_error(): void
public function test_refresh_movie_tmdb_api_error_handles_gracefully(): void
public function test_refresh_movie_invalidates_cache(): void
```

#### ❌ Brakuje: GET /movies/search - fallback do retrieveMovie
**Scenariusz:** Search nie zwraca wyników, ale query jest prawidłowym slugiem.

**Test potrzebny:**
```php
public function test_search_fallback_to_retrieve_when_query_is_valid_slug(): void
{
    // Search: q=the-matrix-1999 (brak wyników w search)
    // Powinien spróbować retrieveMovie() i zwrócić 202 jeśli nie istnieje
}
```

---

### 5. Integracja - Scenariusze End-to-End

#### ❌ Brakuje: Pełny flow: Search → Generate → Retrieve
**Scenariusz:** Użytkownik szuka filmu, generuje opis, następnie pobiera film.

**Test potrzebny:**
```php
public function test_end_to_end_search_generate_retrieve_flow(): void
{
    // 1. Search: q=matrix (zwraca external results)
    // 2. Generate: POST /generate (queue job)
    // 3. Wait for job completion
    // 4. Retrieve: GET /movies/the-matrix-1999 (powinien mieć opis)
}
```

#### ❌ Brakuje: Concurrent requests - race conditions
**Scenariusz:** Wiele requestów jednocześnie dla tego samego filmu.

**Test potrzebny:**
```php
public function test_concurrent_requests_same_movie_handles_correctly(): void
{
    // 10 równoczesnych requestów GET /movies/the-matrix-1999
    // Powinny wszystkie zwrócić ten sam wynik (cache)
}
```

---

### 6. Error Handling

#### ❌ Brakuje: Database connection errors
**Scenariusz:** Baza danych jest niedostępna.

**Test potrzebny:**
```php
public function test_search_handles_database_connection_error(): void
{
    // Mock database exception
    // Powinien zwrócić odpowiedni błąd HTTP (500 lub 503)
}
```

#### ❌ Brakuje: Cache errors (Redis down)
**Scenariusz:** Redis jest niedostępny, ale aplikacja powinna działać.

**Test potrzebny:**
```php
public function test_search_handles_cache_errors_gracefully(): void
{
    // Mock cache exception
    // Powinien działać bez cache (fallback)
}
```

---

### 7. Performance & Limits

#### ❌ Brakuje: Limit wyników z TMDB
**Scenariusz:** TMDB zwraca 100 wyników, ale limit to 20.

**Test potrzebny:**
```php
public function test_search_respects_limit_parameter(): void
{
    // limit=5, ale TMDB zwraca 100 wyników
    // Powinien zwrócić tylko 5 wyników
}
```

#### ❌ Brakuje: Very large result sets
**Scenariusz:** Wyszukiwanie zwraca 1000+ wyników.

**Test potrzebny:**
```php
public function test_search_handles_large_result_sets(): void
{
    // 1000+ lokalnych filmów + 1000+ z TMDB
    // Powinien obsłużyć bez timeout
}
```

---

## 📝 Priorytetyzacja

### 🔴 Wysoki Priorytet (Krytyczne)
1. ✅ Filtrowanie TMDB po year (NAPRAWIONE)
2. ❌ Filtrowanie TMDB po director
3. ❌ Filtrowanie TMDB po actor
4. ❌ TMDB API error handling
5. ❌ Duplikaty między lokalnymi a TMDB wynikami

### 🟡 Średni Priorytet (Ważne)
1. ❌ Kombinacja filtrów (year + director + actor)
2. ❌ Paginacja z filtrowaniem
3. ❌ Edge cases dla release_date (null, invalid format)
4. ❌ Cache invalidation po refresh
5. ❌ End-to-end flow (search → generate → retrieve)

### 🟢 Niski Priorytet (Nice to have)
1. ❌ Concurrent requests handling
2. ❌ Database/Cache error handling
3. ❌ Performance tests (large result sets)
4. ❌ Circular relationships handling

---

## 🎯 Rekomendacje

### Natychmiastowe działania:
1. **Dodać testy dla filtrowania TMDB po director i actor** (podobny problem jak z year)
2. **Dodać testy dla error handling** (TMDB API errors, database errors)
3. **Dodać testy dla edge cases** (null release_date, invalid formats)

### Długoterminowe:
1. **Zwiększyć pokrycie testami do 80%+** (obecnie ~60-70%)
2. **Dodać testy integracyjne** (end-to-end flows)
3. **Dodać testy performance** (load testing dla dużych zbiorów danych)

---

## 📚 Powiązane Dokumenty

- `docs/TESTING_STRATEGY.md` - Strategia testowania
- `docs/MANUAL_TESTING_GUIDE.md` - Przewodnik testowania manualnego
- `docs/issue/NEW_SEARCH_USE_CASE_IMPLEMENTATION_PLAN.md` - Plan implementacji

---

**Ostatnia aktualizacja:** 2025-01-XX  
**Autor:** AI Assistant (Claude)

