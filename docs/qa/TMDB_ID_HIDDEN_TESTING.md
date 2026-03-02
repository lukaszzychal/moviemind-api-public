# Testowanie ukrycia `tmdb_id` w API

## 🎯 Cel

Upewnić się, że `tmdb_id` jest przechowywane w bazie danych, ale **nie jest widoczne** w publicznych odpowiedziach API.

## 🧪 Testy automatyczne

### Uruchomienie testów

```bash
cd api
php artisan test --filter=TmdbIdHiddenTest
```

### Co testują?

1. **Movie API responses** - sprawdza że `GET /api/v1/movies/{slug}` nie zwraca `tmdb_id`
2. **Person API responses** - sprawdza że `GET /api/v1/people/{slug}` nie zwraca `tmdb_id`
3. **Movie list API** - sprawdza że `GET /api/v1/movies` nie zwraca `tmdb_id` w żadnym filmie
4. **Person list API** - sprawdza że `GET /api/v1/people` nie zwraca `tmdb_id` w żadnej osobie
5. **Movie search API** - sprawdza że `GET /api/v1/movies/search` nie zwraca `tmdb_id` w wynikach
6. **Movie with people relation** - sprawdza że relacje people również nie zawierają `tmdb_id`

## 🔍 Testowanie manualne

### 1. Sprawdź Movie API

```bash
# Utwórz film z tmdb_id (przez bazę danych lub API)
# Następnie sprawdź odpowiedź:

curl http://localhost:8000/api/v1/movies/the-matrix-1999 | jq .

# Sprawdź że:
# ✅ Jest pole "id", "title", "slug", "release_year"
# ❌ NIE MA pola "tmdb_id"
```

### 2. Sprawdź Person API

```bash
curl http://localhost:8000/api/v1/people/keanu-reeves | jq .

# Sprawdź że:
# ✅ Jest pole "id", "name", "slug"
# ❌ NIE MA pola "tmdb_id"
```

### 3. Sprawdź listy (Movie i Person)

```bash
# Lista filmów
curl http://localhost:8000/api/v1/movies | jq '.data[0]'

# Lista osób
curl http://localhost:8000/api/v1/people | jq '.data[0]'

# Sprawdź że żaden element nie ma "tmdb_id"
```

### 4. Sprawdź wyszukiwanie

```bash
curl "http://localhost:8000/api/v1/movies/search?q=matrix" | jq '.results[0]'

# Sprawdź że:
# ✅ Jest "title", "release_year", "director"
# ❌ NIE MA "tmdb_id"
```

### 5. Sprawdź relacje (Movie z People)

```bash
curl http://localhost:8000/api/v1/movies/the-matrix-1999 | jq '.people[0]'

# Sprawdź że:
# ✅ Jest "id", "name", "slug", "role"
# ❌ NIE MA "tmdb_id" w żadnej osobie z relacji
```

## ✅ Weryfikacja w bazie danych

### Sprawdź że `tmdb_id` istnieje w bazie:

```sql
-- PostgreSQL
SELECT id, title, tmdb_id FROM movies LIMIT 5;
SELECT id, name, tmdb_id FROM people LIMIT 5;
```

### Sprawdź że `tmdb_id` NIE jest w odpowiedziach API:

```bash
# Użyj jq do sprawdzenia
curl http://localhost:8000/api/v1/movies/the-matrix-1999 | jq 'has("tmdb_id")'
# Powinno zwrócić: false

curl http://localhost:8000/api/v1/movies/the-matrix-1999 | jq '.people[0] | has("tmdb_id")'
# Powinno zwrócić: false
```

## 🐛 Debugowanie

### Jeśli `tmdb_id` pojawia się w odpowiedzi:

1. **Sprawdź MovieResource** - czy ręcznie buduje tablicę (nie używa `parent::toArray()`)
2. **Sprawdź PersonResource** - czy ma `unset($data['tmdb_id'])`
3. **Sprawdź MovieSearchService** - czy nie dodaje `tmdb_id` do wyników wyszukiwania
4. **Sprawdź cache** - wyczyść cache jeśli używasz:
   ```bash
   php artisan cache:clear
   ```

### Sprawdź wszystkie miejsca gdzie zwracamy dane:

```bash
# Znajdź wszystkie użycia toArray w Resources
grep -r "toArray" api/app/Http/Resources/

# Znajdź wszystkie miejsca gdzie zwracamy Movie/Person
grep -r "MovieResource\|PersonResource" api/app/Http/
```

## 📝 Przykładowe odpowiedzi

### ✅ Poprawna odpowiedź Movie (BEZ tmdb_id):

```json
{
  "id": 1,
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "release_year": 1999,
  "director": "Lana Wachowski",
  "genres": [],
  "descriptions_count": 1
}
```

### ❌ Niepoprawna odpowiedź (Z tmdb_id):

```json
{
  "id": 1,
  "tmdb_id": 603,  // ❌ TO NIE POWINNO BYĆ WIDOCZNE
  "title": "The Matrix",
  ...
}
```

## 🎯 Checklist testowania

- [x] Movie API (`GET /api/v1/movies/{slug}`) nie zwraca `tmdb_id` ✅
- [x] Person API (`GET /api/v1/people/{slug}`) nie zwraca `tmdb_id` ✅
- [x] Lista filmów (`GET /api/v1/movies`) nie zwraca `tmdb_id` w żadnym filmie ✅
- [x] Lista osób (`GET /api/v1/people`) nie zwraca `tmdb_id` w żadnej osobie ✅
- [x] Wyszukiwanie (`GET /api/v1/movies/search`) nie zwraca `tmdb_id` w wynikach ✅
- [x] Relacje people w Movie nie zawierają `tmdb_id` ✅
- [x] `tmdb_id` istnieje w bazie danych (sprawdź SQL) ✅
- [x] Testy automatyczne przechodzą (`php artisan test --filter=TmdbIdHiddenTest`) ✅

**Status:** ✅ **Wszystkie testy przeszły pomyślnie (7 passed, 52 assertions)**

**Data weryfikacji:** 2025-12-17

