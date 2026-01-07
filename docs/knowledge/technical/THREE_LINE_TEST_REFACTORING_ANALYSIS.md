# Analiza refaktoryzacji testów do techniki "Three-Line Test"

> **Data utworzenia:** 2025-01-07  
> **Zadanie:** TASK-030  
> **Status:** 📊 Analiza

---

## 📋 Podsumowanie

**Wniosek:** ✅ **WARTO** zrefaktoryzować wybrane testy, ale **selektywnie** - nie wszystkie testy skorzystają z tej techniki.

**Rekomendacja:** Refaktoryzacja w **fazach**, zaczynając od testów z największym potencjałem.

---

## 🔍 Analiza obecnego stanu

### Statystyki

- **Testy używające komentarzy GIVEN/WHEN/THEN:** 133 wystąpień w 6 plikach
- **Główne pliki testowe:**
  - `AdminFlagsTest.php` - 7 testów
  - `MissingEntityGenerationTest.php` - 20+ testów (bardzo długie)
  - `GenerateApiTest.php` - 20+ testów (powtarzające się wzorce)
  - `MoviesApiTest.php` - wiele testów z prostymi scenariuszami
  - `PeopleApiTest.php` - podobne do MoviesApiTest
  - `MovieLocaleTest.php` / `MovieApiLocaleTest.php` - testy lokalizacji

### Obecne wzorce

#### ✅ Dobry przykład (już używa GWT komentarzy)

```php
public function test_toggle_flag(): void
{
    // GIVEN: Flag is deactivated
    Feature::deactivate('ai_description_generation');

    // WHEN: Toggling flag to 'on'
    $res = $this->postJson('/api/v1/admin/flags/ai_description_generation', ['state' => 'on']);

    // THEN: Should return OK with flag activated
    $res->assertOk()->assertJson(['name' => 'ai_description_generation', 'active' => true]);
}
```

#### ❌ Przykład do refaktoryzacji (długi, powtarzający się kod)

```php
public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
{
    // GIVEN: AI generation is enabled and movie exists in TMDb
    Feature::activate('ai_description_generation');
    $fake = $this->fakeEntityVerificationService();
    $fake->setMovie('annihilation', [
        'title' => 'Annihilation',
        'release_date' => '2018-02-23',
        'overview' => 'A biologist signs up for a dangerous expedition.',
        'id' => 300668,
        'director' => 'Alex Garland',
    ]);

    // WHEN: Requesting a movie that doesn't exist locally
    $res = $this->getJson('/api/v1/movies/annihilation');

    // THEN: Should return 202 with job details
    $res->assertStatus(202)
        ->assertJsonStructure(['job_id', 'status', 'slug', 'confidence', 'confidence_level'])
        ->assertJson(['locale' => 'en-US']);

    // THEN: Confidence fields should be properly set
    $this->assertNotNull($res->json('confidence'));
    $this->assertNotSame('unknown', $res->json('confidence_level'));
    $this->assertContains($res->json('confidence_level'), ['high', 'medium', 'low', 'very_low']);
}
```

**Po refaktoryzacji (Three-Line Test):**

```php
public function test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb(): void
{
    $this->givenAiGenerationEnabled()
        ->andMovieExistsInTmdb('annihilation', [
            'title' => 'Annihilation',
            'release_date' => '2018-02-23',
            'overview' => 'A biologist signs up for a dangerous expedition.',
            'id' => 300668,
            'director' => 'Alex Garland',
        ])
        ->whenRequestingMovie('annihilation')
        ->thenShouldReturn202WithJobDetails()
        ->andConfidenceFieldsShouldBeSet();
}
```

---

## 📊 Kategorie testów

### 1. ✅ Wysoki potencjał refaktoryzacji

**Kryteria:**
- Długie testy (20+ linii)
- Powtarzające się wzorce setupu
- Wielokrotne weryfikacje (THEN/AND)
- Złożone scenariusze z wieloma krokami

**Pliki:**
- `MissingEntityGenerationTest.php` ⭐⭐⭐ (20+ testów, bardzo długie)
- `GenerateApiTest.php` ⭐⭐⭐ (20+ testów, powtarzające się wzorce)
- `AdminFlagsTest.php` ⭐⭐ (7 testów, średnia złożoność)

**Przykłady:**
- `test_movie_missing_returns_202_when_flag_on_and_found_in_tmdb()`
- `test_generate_movie_allowed_when_flag_on()`
- `test_toggle_flag()`

**Korzyści:**
- ✅ Znaczna redukcja duplikacji kodu
- ✅ Lepsza czytelność
- ✅ Łatwiejsze utrzymanie
- ✅ Reużywalne helpery

### 2. 🟡 Średni potencjał refaktoryzacji

**Kryteria:**
- Średniej długości testy (10-20 linii)
- Niektóre powtarzające się wzorce
- Proste scenariusze

**Pliki:**
- `MoviesApiTest.php` ⭐⭐
- `PeopleApiTest.php` ⭐⭐
- `MovieLocaleTest.php` ⭐

**Przykłady:**
- `test_list_movies_returns_ok()`
- `test_show_movie_returns_ok()`

**Korzyści:**
- ✅ Umiarkowana redukcja duplikacji
- ✅ Lepsza czytelność
- ⚠️ Może być overkill dla prostych testów

### 3. ❌ Niski potencjał refaktoryzacji

**Kryteria:**
- Krótkie testy (< 10 linii)
- Proste asercje
- Brak powtarzających się wzorców
- Jednorazowe scenariusze

**Pliki:**
- `ExampleTest.php`
- Proste testy walidacji
- Testy jednostkowe

**Przykłady:**
- `test_that_true_is_true()`
- Proste testy asercji

**Korzyści:**
- ❌ Overkill - prosty AAA jest lepszy
- ❌ Dodatkowa abstrakcja nie jest potrzebna

---

## 💡 Rekomendacje

### Faza 1: Wysoki priorytet (⭐⭐⭐)

**Cel:** Refaktoryzacja testów z największym potencjałem

**Pliki:**
1. `MissingEntityGenerationTest.php` - **najwyższy priorytet**
   - 20+ bardzo długich testów
   - Dużo duplikacji (setup TMDb, feature flags, weryfikacje)
   - Szacowany czas: 4-6h

2. `GenerateApiTest.php` - **wysoki priorytet**
   - 20+ testów z powtarzającymi się wzorcami
   - Duplikacja setupu feature flags i event assertions
   - Szacowany czas: 3-4h

**Helpery do utworzenia:**
```php
// MissingEntityGenerationTest helpers
- givenAiGenerationEnabled()
- givenTmdbVerificationEnabled()
- andMovieExistsInTmdb(string $slug, array $data)
- andPersonExistsInTmdb(string $slug, array $data)
- whenRequestingMovie(string $slug)
- whenRequestingPerson(string $slug)
- thenShouldReturn202WithJobDetails()
- thenShouldReturn404WithError(string $error)
- andConfidenceFieldsShouldBeSet()

// GenerateApiTest helpers
- givenFeatureFlagEnabled(string $feature)
- givenFeatureFlagDisabled(string $feature)
- whenGeneratingMovie(string $slug, array $options = [])
- whenGeneratingPerson(string $slug, array $options = [])
- thenShouldReturn202WithJobId()
- thenEventShouldBeDispatched(string $eventClass, callable $assertion)
```

### Faza 2: Średni priorytet (⭐⭐)

**Cel:** Refaktoryzacja testów z umiarkowanym potencjałem

**Pliki:**
1. `AdminFlagsTest.php`
   - 7 testów, średnia złożoność
   - Szacowany czas: 1-2h

2. `MoviesApiTest.php` / `PeopleApiTest.php`
   - Wiele prostych testów, niektóre mogą skorzystać
   - Szacowany czas: 2-3h

**Helpery do utworzenia:**
```php
// AdminFlagsTest helpers
- givenFlagIsDeactivated(string $flag)
- whenTogglingFlag(string $flag, string $state)
- thenFlagShouldBeActivated(string $flag)
- thenFlagShouldBeDeactivated(string $flag)

// MoviesApiTest helpers
- givenMoviesExistInDatabase()
- whenRequestingMovieList()
- whenRequestingMovie(string $slug)
- thenShouldReturnOkWithStructure(array $structure)
```

### Faza 3: Niski priorytet (⭐)

**Cel:** Opcjonalna refaktoryzacja prostych testów

**Pliki:**
- `MovieLocaleTest.php`
- Proste testy walidacji

**Uwaga:** Większość testów w tej fazie **nie powinna** być refaktoryzowana - prosty AAA jest lepszy.

---

## 📈 Szacowane korzyści

### Przed refaktoryzacją

- **MissingEntityGenerationTest.php:** ~550 linii kodu
- **GenerateApiTest.php:** ~600 linii kodu
- **Duplikacja:** Wysoka (setup TMDb, feature flags, assertions)

### Po refaktoryzacji (Faza 1)

- **MissingEntityGenerationTest.php:** ~350 linii kodu (-36%)
- **GenerateApiTest.php:** ~400 linii kodu (-33%)
- **Helpery:** ~200 linii (reużywalne)
- **Duplikacja:** Niska
- **Czytelność:** Znacznie lepsza

### Łączne oszczędności (Faza 1 + 2)

- **Redukcja kodu:** ~30-40%
- **Czytelność:** Znacznie lepsza
- **Utrzymanie:** Łatwiejsze (zmiany w jednym miejscu)
- **Onboarding:** Szybszy (jasne wzorce)

---

## ⚠️ Ryzyka i wyzwania

### Ryzyka

1. **Over-engineering prostych testów**
   - ⚠️ Nie wszystkie testy skorzystają
   - ✅ Rozwiązanie: Refaktoryzować tylko złożone testy

2. **Zbyt wiele abstrakcji**
   - ⚠️ Helper hell - zbyt wiele helperów
   - ✅ Rozwiązanie: Utrzymywać helpery w jednej klasie, dokumentować

3. **Trudniejsze debugowanie**
   - ⚠️ Więcej warstw abstrakcji
   - ✅ Rozwiązanie: Dobre nazewnictwo, dokumentacja

4. **Czas refaktoryzacji**
   - ⚠️ Wymaga czasu i dyscypliny
   - ✅ Rozwiązanie: Fazy, zaczynać od największych korzyści

### Wyzwania

1. **Utrzymanie helperów**
   - Helpery muszą być aktualizowane wraz ze zmianami w testach
   - Rozwiązanie: Dobre nazewnictwo, dokumentacja, code review

2. **Nauka nowych wzorców**
   - Nowi deweloperzy muszą poznać helpery
   - Rozwiązanie: Dokumentacja, przykłady, code review

---

## ✅ Checklist refaktoryzacji

### Przed rozpoczęciem

- [ ] Przeczytać dokumentację `THREE_LINE_TEST_TECHNIQUE.pl.md`
- [ ] Zidentyfikować testy do refaktoryzacji (Faza 1)
- [ ] Zaplanować helpery
- [ ] Uzgodnić z zespołem

### Podczas refaktoryzacji

- [ ] Tworzyć helpery zgodnie z konwencjami (`given*`, `when*`, `then*`, `and*`)
- [ ] Utrzymywać helpery w jednej klasie testowej
- [ ] Testy powinny być czytelne jak specyfikacje
- [ ] Uruchamiać testy po każdej zmianie
- [ ] Nie refaktoryzować prostych testów (overkill)

### Po refaktoryzacji

- [ ] Wszystkie testy przechodzą
- [ ] Kod jest czytelny
- [ ] Helpery są udokumentowane
- [ ] Code review przeprowadzone
- [ ] Zaktualizować dokumentację jeśli potrzeba

---

## 🎯 Rekomendacja końcowa

### ✅ TAK - warto refaktoryzować, ale selektywnie

**Rozpocząć od:**
1. **Faza 1:** `MissingEntityGenerationTest.php` i `GenerateApiTest.php` (największe korzyści)
2. **Faza 2:** `AdminFlagsTest.php` (średnie korzyści)
3. **Faza 3:** Opcjonalnie, tylko jeśli jest potrzeba

**Nie refaktoryzować:**
- Proste testy jednostkowe (< 10 linii)
- Testy z jednorazowymi scenariuszami
- Testy bez powtarzających się wzorców

**Szacowany czas:**
- Faza 1: 7-10h
- Faza 2: 3-5h
- **Łącznie:** 10-15h

**ROI:** Wysoki dla Fazy 1, średni dla Fazy 2, niski dla Fazy 3.

---

## 📚 Referencje

- [`THREE_LINE_TEST_TECHNIQUE.pl.md`](../tutorials/THREE_LINE_TEST_TECHNIQUE.pl.md) - Pełna dokumentacja techniki
- [`TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](../tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md) - Wzorce testów

---

**Ostatnia aktualizacja:** 2025-01-07  
**Autor:** MovieMind API Team  
**Zadanie:** TASK-030

