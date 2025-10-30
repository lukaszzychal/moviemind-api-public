# Handling Duplicate Movie Titles

## Problem

Many movies share the same title but are different films. This can occur in two scenarios:

### 1. Same Title, Different Years

- **"Bad Boys"** – film z 1983 roku z Seanem Pennem oraz znany z lat 90. (1995)
- **"Crash"** – film Davida Cronenberga (1996) oraz Oscarowy "Crash" z 2004 roku
- **"The Avengers"** – film z 1998 roku różni się od późniejszych adaptacji Marvela
- **"Frozen"** – animacja Disneya (2013) oraz inne niezależne produkcje
- **"Heat"** – wersja z Burtem Reynoldsem (1986) oraz późniejsza wersja z Alem Pacino (1995)

### 2. Same Title, Same Year (Edge Case)

Rzadziej, ale możliwe:
- Różne wersje językowe wydane w tym samym roku
- Remaki w tym samym roku
- Filmy niezależne z różnych krajów
- Różne wersje (reżyserska vs kinowa)

## Solution

Slugs now include the release year to ensure uniqueness:

### Slug Format

The system uses a priority-based slug generation that handles duplicates automatically:

1. **Basic format** (unique): `title-slug-YYYY` 
   - Example: `bad-boys-1995`, `crash-2004`

2. **With director disambiguation** (if duplicate exists and director available): `title-slug-YYYY-director-slug`
   - Example: `heat-1995-michael-mann` (if another "Heat 1995" exists)
   - Example: `the-prestige-2006-christopher-nolan` (if duplicate exists)

3. **With numeric suffix** (fallback): `title-slug-YYYY-2`, `title-slug-YYYY-3`, etc.
   - Example: `heat-1995-2` (if director not available or also duplicates)

4. **Legacy format** (backward compatibility): `title-slug` (e.g., `bad-boys`)
   - Only for old movies without year in slug

### API Behavior

#### 1. Specific Movie (with year in slug)
```
GET /api/v1/movies/bad-boys-1995
```
Returns the specific movie from 1995.

#### 2. Ambiguous Request (slug without year)
```
GET /api/v1/movies/bad-boys
```

If multiple movies exist with this title:
- Returns the **most recent** movie (highest release year)
- Includes `_meta.ambiguous: true` in response
- Provides list of alternatives in `_meta.alternatives`

#### 3. Same Title, Same Year (with disambiguation)
```
GET /api/v1/movies/heat-1995
GET /api/v1/movies/heat-1995-michael-mann
GET /api/v1/movies/heat-1995-2
```

If multiple movies share the same title AND year:
- First movie gets: `heat-1995`
- Second movie (if director helps): `heat-1995-michael-mann`
- Third movie (fallback): `heat-1995-2`

Example response:
```json
{
  "id": 123,
  "title": "Bad Boys",
  "slug": "bad-boys-1995",
  "release_year": 1995,
  ...
  "_meta": {
    "ambiguous": true,
    "message": "Multiple movies found with this title. Showing most recent. Use slug with year (e.g., \"bad-boys-1995\") for specific version.",
    "alternatives": [
      {
        "slug": "bad-boys-1995",
        "title": "Bad Boys",
        "release_year": 1995,
        "url": "http://localhost:8000/api/v1/movies/bad-boys-1995"
      },
      {
        "slug": "bad-boys-1983",
        "title": "Bad Boys",
        "release_year": 1983,
        "url": "http://localhost:8000/api/v1/movies/bad-boys-1983"
      }
    ]
  }
}
```

If only one movie exists, returns it normally without `_meta`.

## Implementation Details

### Generating Slugs

Use `Movie::generateSlug()` method:

```php
// Basic format (unique) - recommended
$slug = Movie::generateSlug('Bad Boys', 1995); 
// Returns: "bad-boys-1995"

// With director (helps with disambiguation if duplicates exist)
$slug = Movie::generateSlug('Heat', 1995, 'Michael Mann'); 
// If "heat-1995" exists, returns: "heat-1995-michael-mann"
// If that also exists, returns: "heat-1995-2"

// Without year (not recommended for new movies)
$slug = Movie::generateSlug('Bad Boys'); 
// Returns: "bad-boys"

// When updating existing movie (exclude self from duplicate check)
$slug = Movie::generateSlug('Heat', 1995, 'Michael Mann', $movie->id);
```

**Note**: The method automatically handles duplicates by:
1. Trying basic format first (`title-year`)
2. If exists, trying with director (`title-year-director`)
3. If still exists, using numeric suffix (`title-year-2`, `title-year-3`, etc.)

### Parsing Slugs

Use `Movie::parseSlug()` to extract title, year, director, and suffix:

```php
// Basic format
$parsed = Movie::parseSlug('bad-boys-1995');
// Returns: [
//   'title' => 'Bad Boys', 
//   'year' => 1995, 
//   'director' => null, 
//   'suffix' => null
// ]

// With director disambiguation
$parsed = Movie::parseSlug('heat-1995-michael-mann');
// Returns: [
//   'title' => 'Heat', 
//   'year' => 1995, 
//   'director' => 'Michael Mann', 
//   'suffix' => null
// ]

// With numeric suffix
$parsed = Movie::parseSlug('heat-1995-2');
// Returns: [
//   'title' => 'Heat', 
//   'year' => 1995, 
//   'director' => null, 
//   'suffix' => '2'
// ]

// Without year (legacy)
$parsed = Movie::parseSlug('bad-boys');
// Returns: ['title' => 'Bad Boys', 'year' => null, 'director' => null, 'suffix' => null]
```

### Database Constraints

- Slug column has `UNIQUE` constraint
- This ensures no two movies can have identical slugs
- Including year in slug prevents collisions for same-title movies

## Migration Notes

Existing movies with slugs without years will continue to work:
- Repository will match them by title slug pattern
- If multiple matches, returns most recent
- New movies should always include year in slug

## Best Practices

1. **Always include year when generating slugs** for new movies
2. **Include director name** when generating slugs - it helps with disambiguation if duplicates occur
3. **Use specific slugs** (with year, or year+director) when you know the exact movie
4. **Handle ambiguous responses** by checking `_meta.ambiguous` and providing user choice
5. **The system handles duplicates automatically** - if same title+year exists, it will:
   - First try: add director name to slug
   - Fallback: add numeric suffix (2, 3, etc.)
6. **Update existing data** gradually by adding year to slugs where duplicates exist

