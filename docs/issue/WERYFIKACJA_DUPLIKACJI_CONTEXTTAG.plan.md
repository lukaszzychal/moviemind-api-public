# Plan: Weryfikacja duplikacji i uzupełnienie testów ContextTag dla istniejących funkcjonalności

## Analiza obecnego stanu

### Obecna obsługa ContextTag:
- **Enum ContextTag:** `DEFAULT`, `MODERN`, `CRITICAL`, `HUMOROUS`
- **POST /api/v1/generate:** Obsługuje `context_tag` w body
- **GET /api/v1/movies/{slug}:** Obsługuje tylko `description_id` jako query parameter
  - ⚠️ **Uwaga:** Dodanie obsługi `context_tag` jako query parameter to osobne zadanie (TASK-034)
- **Database constraint:** Unique na `(movie_id, locale, context_tag)` - zapobiega duplikatom
- **Logika generowania:** `determineContextTag()` wybiera ContextTag lub używa `nextContextTag()`

### Obecne testy:
- ✅ `GenerateApiTest::test_generate_movie_passes_context_tag()` - testuje POST z context_tag="modern"
- ✅ `GenerateApiTest::test_generate_person_passes_context_tag()` - testuje POST z context_tag="critical"
- ✅ `MoviesApiTest::test_show_movie_can_select_specific_description()` - testuje wybór przez description_id
- ✅ `MissingEntityGenerationTest` - testy duplikacji dla concurrent requests (ten sam slug)
- ❌ **BRAK testów dla domyślnego ContextTag** przy generowaniu (gdy context_tag nie jest podany)
- ❌ **BRAK testów dla sytuacji gdy context_tag jest null/nie podany**
- ❌ **BRAK testów duplikacji dla różnych ContextTag** - KLUCZOWY TASK
- ❌ **BRAK testów weryfikujących unique constraint** dla (movie_id, locale, context_tag)

## Zadania do wykonania

### 1. Weryfikacja obecnej implementacji ContextTag

**1.1. Sprawdzenie logiki generowania domyślnego ContextTag**
- Przeczytać implementację `determineContextTag()` i `nextContextTag()` w `RealGenerateMovieJob.php`
- Zweryfikować czy domyślny ContextTag jest generowany gdy nie podano parametru
- Zrozumieć kolejność wyboru ContextTag (MODERN → CRITICAL → HUMOROUS → DEFAULT_2, DEFAULT_3...)

**1.2. Sprawdzenie obsługi konkretnego ContextTag**
- Zweryfikować czy POST /api/v1/generate prawidłowo przekazuje context_tag do joba
- Sprawdzić czy job używa podanego context_tag zamiast domyślnego
- Zweryfikować walidację context_tag (czy nieprawidłowy tag jest odrzucany)

**1.3. Sprawdzenie obsługi braku ContextTag**
- Co się dzieje gdy context_tag jest null w POST request
- Co się dzieje gdy context_tag jest nieprawidłowy
- Czy aplikacja fallbackuje do domyślnego ContextTag

### 2. Weryfikacja duplikacji (KLUCZOWY TASK)

**2.1. Sprawdzenie istniejących testów duplikacji**
- Przejrzeć `MissingEntityGenerationTest` - sprawdza duplikację dla concurrent requests
- Zweryfikować czy testy sprawdzają duplikację dla różnych ContextTag
- Sprawdzić czy unique constraint w bazie zapobiega duplikatom

**2.2. Identyfikacja brakujących scenariuszy duplikacji**
- ✅ Duplikacja dla tego samego (slug, locale, context_tag) - powinna być zapobiegana przez slot management
- ✅ Concurrent requests z tym samym context_tag - powinny zwrócić ten sam job_id (już testowane w MissingEntityGenerationTest)
- ❌ **BRAK:** Concurrent requests z różnymi ContextTag - powinny zwrócić różne job_id i utworzyć różne opisy
- ❌ **BRAK:** Testy weryfikujące unique constraint w bazie danych dla (movie_id, locale, context_tag)
- ❌ **BRAK:** Testy sprawdzające czy można utworzyć wiele opisów z różnymi ContextTag dla tego samego filmu

### 3. Dodanie brakujących testów automatycznych

**3.1. Testy dla ContextTag w POST /api/v1/generate**
- `test_generate_movie_with_default_context_tag()` - gdy context_tag nie jest podany
- `test_generate_movie_with_specific_context_tag()` - już istnieje dla "modern"
- `test_generate_movie_with_invalid_context_tag()` - powinien fallbackować do domyślnego
- `test_generate_movie_with_humorous_context_tag()` - nowy test dla "humorous"
- `test_generate_movie_context_tag_null()` - explicit null context_tag

**3.2. Testy duplikacji z ContextTag (KLUCZOWY FOKUS)**
- ✅ `test_concurrent_requests_same_context_tag_same_job()` - już istnieje w MissingEntityGenerationTest
- ❌ `test_concurrent_requests_different_context_tag_different_jobs()` - **NOWY, KLUCZOWY**
  - Dwa concurrent requesty dla tego samego slug, ale z różnymi context_tag (np. "modern" i "humorous")
  - Powinny zwrócić różne job_id i utworzyć różne opisy
- ❌ `test_multiple_context_tags_for_same_movie_allowed()` - **NOWY**
  - Weryfikacja że można utworzyć wiele opisów z różnymi ContextTag dla tego samego filmu
  - Sprawdzenie że unique constraint pozwala na różne ContextTag
- ❌ `test_unique_constraint_prevents_duplicate_same_context_tag()` - **NOWY**
  - Próba utworzenia dwóch opisów z tym samym (movie_id, locale, context_tag)
  - Powinno się nie udać (unique constraint violation)

**3.3. Testy edge cases**
- `test_next_context_tag_rotation()` - weryfikacja kolejności wyboru ContextTag
- `test_context_tag_with_different_locales()` - ten sam context_tag dla różnych locale

### 4. Dodanie brakujących testów manualnych do MANUAL_TESTING_GUIDE.md

**4.1. Test 9: Generowanie z domyślnym ContextTag**
- POST /api/v1/generate bez context_tag
- Weryfikacja że został użyty domyślny ContextTag

**4.2. Test 10: Generowanie z konkretnym ContextTag**
- POST /api/v1/generate z context_tag="humorous"
- Weryfikacja że opis został wygenerowany z właściwym ContextTag

**4.3. Test 11: Edge Case - Nieprawidłowy ContextTag**
- POST /api/v1/generate z context_tag="invalid"
- Weryfikacja fallback do domyślnego lub błąd walidacji

**4.4. Test 12: Duplikacja - Różne ContextTag (KLUCZOWY)**
- Generowanie dla tego samego filmu z różnymi ContextTag (np. najpierw "modern", potem "humorous")
- Weryfikacja że każdy ContextTag ma osobny opis w bazie danych (nie duplikat)
- Sprawdzenie że concurrent requests z różnymi ContextTag zwracają różne job_id

**4.5. Test 13: Co się dzieje gdy nie ma ContextTag w bazie**
- Pobranie filmu który nie ma opisu z danym ContextTag
- Weryfikacja zachowania (zwrócenie default_description lub 404)

### 5. Rozszerzenie dokumentacji MANUAL_TESTING_GUIDE.md

**5.1. Aktualizacja tabeli "Przegląd Przypadków Użycia"**
- Dodanie nowych przypadków (Test 9-13, usunięto Test 11 o GET z context_tag - to TASK-034)
- Opis każdego testu z krótkim wyjaśnieniem
- Oznaczenie kluczowych testów duplikacji

**5.2. Dodanie szczegółowych instrukcji dla każdego nowego testu**
- Kroki testowania
- Oczekiwane wyniki
- Przykłady komend curl

### 6. Weryfikacja które use case'y nie są potrzebne

**Analiza use case'ów:**

✅ **Potrzebne (happy path):**
- Generowanie z domyślnym ContextTag (gdy nie podano)
- Generowanie z konkretnym ContextTag (modern, critical, humorous)
- Możliwość utworzenia wielu opisów z różnymi ContextTag dla tego samego filmu
- Duplikacja zapobieganie dla tego samego (slug, locale, context_tag)

✅ **Potrzebne (bad path):**
- Nieprawidłowy ContextTag - fallback do domyślnego
- Concurrent requests z różnymi ContextTag - różne job_id i opisy

❌ **NIE potrzebne (osobne zadanie TASK-034):**
- Pobieranie z ContextTag przez GET /api/v1/movies/{slug}?context_tag=humorous

## Pliki do modyfikacji

### Pliki testowe automatyczne:
- `api/tests/Feature/GenerateApiTest.php` - rozszerzenie testów ContextTag (domyślny, null, nieprawidłowy)
- `api/tests/Feature/MissingEntityGenerationTest.php` - rozszerzenie testów duplikacji o różne ContextTag (KLUCZOWY)
- `api/tests/Unit/Jobs/GenerateMovieJobTest.php` - testy dla determineContextTag() i nextContextTag() (jeśli istnieje)

### Pliki dokumentacji:
- `docs/knowledge/reference/MANUAL_TESTING_GUIDE.md` - dodanie Testów 9-13
- `docs/knowledge/reference/MANUAL_TESTING_GUIDE.en.md` - angielska wersja

### Uwaga:
- Obsługa context_tag jako query parameter w GET /api/v1/movies/{slug} to osobne zadanie TASK-034
- Ten plan skupia się na weryfikacji duplikacji i testach dla istniejących funkcjonalności

## Kryteria akceptacji

1. ✅ Wszystkie scenariusze ContextTag są przetestowane (domyślny, konkretny, null, nieprawidłowy)
2. ✅ **Testy duplikacji sprawdzają różne ContextTag** - KLUCZOWY KRYTERIUM
   - Concurrent requests z różnymi ContextTag zwracają różne job_id
   - Możliwość utworzenia wielu opisów z różnymi ContextTag dla tego samego filmu
   - Unique constraint zapobiega duplikatom dla tego samego (movie_id, locale, context_tag)
3. ✅ Dokumentacja manualna zawiera wszystkie nowe przypadki użycia (Test 9-13)
4. ✅ Tabela "Przegląd Przypadków Użycia" jest zaktualizowana
5. ✅ Wszystkie nowe testy przechodzą
6. ✅ Dokumentacja jest zsynchronizowana (PL i EN)

## Szacowany czas

- Weryfikacja obecnej implementacji: 30 min
- Dodanie testów automatycznych (szczególnie duplikacji): 1-2h
- Dodanie testów manualnych do dokumentacji: 1h
- Aktualizacja tabeli użyć: 15 min
- Testowanie: 30 min

**Razem: ~3-4h**

## To-dos

- [ ] Zweryfikować logikę generowania domyślnego ContextTag i obsługi konkretnego ContextTag w kodzie (determineContextTag, nextContextTag)
- [ ] Zweryfikować istniejące testy duplikacji i zidentyfikować brakujące scenariusze dla ContextTag
- [ ] **KLUCZOWE:** Dodać testy automatyczne duplikacji dla różnych ContextTag (concurrent requests z różnymi context_tag)
- [ ] Dodać testy automatyczne dla ContextTag: domyślny, null, nieprawidłowy
- [ ] Dodać testy weryfikujące unique constraint dla (movie_id, locale, context_tag)
- [ ] Dodać testy manualne (Test 9-13) do MANUAL_TESTING_GUIDE.md z pełnymi instrukcjami
- [ ] Zaktualizować tabelę 'Przegląd Przypadków Użycia' w dokumentacji o nowe przypadki
- [ ] Zsynchronizować wersję angielską dokumentacji (MANUAL_TESTING_GUIDE.en.md)

---

**Ostatnia aktualizacja:** 2025-11-29

