# Search Use Cases, Fixtures, and Why Manual Testing Can Disagree With Automated Tests

> **For:** QA, Developers  
> **Purpose:** Explain why automated tests can pass while manual testing shows "errors", and ensure every search use case has documented expected behaviour and fixtures.

---

## Why Automated Tests Passed But Manual Testing Showed Errors

### 1. Different data sources

| Environment | Data source | Actor/director/year search |
|-------------|------------|----------------------------|
| **Feature tests (PHPUnit)** | `RefreshDatabase` + `db:seed` + **per-test data** | Each test creates the exact movies/people it needs (e.g. `Movie::firstOrCreate` + `Person` + `movie_person`). So e.g. `?actor=Keanu Reeves` gets a movie because the test (or seed) created one. |
| **Manual testing** | Whatever is in your DB (often only `migrate --seed`) | If you ran only `migrate` without `--seed`, or use staging/production where seeders **skip** test data, there may be **no** movies with actors attached. Then `?actor=Keanu Reeves` correctly returns 0 results – it's a **data** gap, not a code bug. |

So: **tests pass** because they arrange data; **manual testing "fails"** when the DB doesn't contain the data the use case expects.

### 2. E2E tests do not cover search API

The e2e suite (`tests/e2e/specs/`) currently calls `/api/v1/movies`, `/api/v1/generate`, `/api/v1/health`, and admin endpoints. **There are no e2e tests for `GET /api/v1/movies/search`.** So search bugs (e.g. actor-only returning nothing) would never be caught by e2e – only by feature tests or manual checks.

### 3. What to do

- **Manual testing:** Run `php artisan migrate --seed` (or use the **Search fixtures** seeder) so the DB contains data for every search use case.
- **Documentation:** Use the use-case matrix below and the fixtures seeder so each case has a predictable result.
- **E2E (optional):** Add e2e tests for critical search scenarios (e.g. `q=Matrix`, `actor=Keanu Reeves`) against a seeded DB.

---

## Search Use Case Matrix

Each row is one use case: required query params, expected outcome, and which fixture is required so that **both** automated and manual testing get the same result.

| # | Use case | Example params | Expected (with fixtures) | Fixture required |
|---|-----------|----------------|---------------------------|------------------|
| 1 | Text query only | `q=Matrix` | 200, results with title/director/genres matching "Matrix" | Movie "The Matrix" (MovieSeeder) |
| 2 | Query + year | `q=Matrix&year=1999` | 200, only movies with release_year=1999 | Movie "The Matrix" (1999) |
| 3 | Query + director | `q=Matrix&director=Wachowski` | 200, movies matching q and director substring | Movie with director containing "Wachowski" |
| 4 | Query + actor | `q=Matrix&actor=Keanu` | 200, movies matching q and having ACTOR matching "Keanu" | Movie + Person(ACTOR) "Keanu Reeves" in movie_person (ActorSeeder) |
| 5 | **Actor only** (no q) | `actor=Keanu Reeves` | 200, all movies that have an ACTOR whose name contains "Keanu Reeves" | At least one movie with that actor (ActorSeeder links Keanu to The Matrix) |
| 6 | **Director only** (no q) | `director=Wachowski` | 200, all movies with director containing "Wachowski" | Movie with director "Wachowski" (MovieSeeder: The Matrix) |
| 7 | **Year only** (no q) | `year=1999` | 200, all movies with release_year=1999 | At least one movie from 1999 (MovieSeeder: The Matrix) |
| 8 | Multiple actors | `actor[]=Keanu&actor[]=Laurence` | 200, movies that have at least one ACTOR matching any of the names | Movies with those actors (tests create; seed can add) |
| 9 | No results | `q=NonexistentMovieXYZ123` | 404, match_type=none, results=[] | No fixture needed |
| 10 | Pagination | `q=matrix&page=1&per_page=5` | 200, pagination object + slice of results | Enough movies matching "matrix" (seed + optional) |
| 11 | Page beyond last | `q=matrix&page=99&per_page=10` | 200, last page returned (effective page clamped) | Any |
| 12 | Sort | `q=star&sort=title&order=asc` | 200, results sorted by title asc | Movies matching "star" (seed or test data) |
| 13 | Source filter local | `q=matrix&source=local` | 200, only local results | Local movies matching q |
| 14 | Source filter external | `q=matrix&source=external` | 200, only external (TMDB) results | TMDB feature on; no fixture for external |
| 15 | Limits | `q=matrix&local_limit=2&external_limit=2` | 200, at most 2 local + 2 external | Any |

**Fixtures in codebase today:**

- **MovieSeeder:** The Matrix (1999, director The Wachowskis), Inception (2010, Christopher Nolan). No actors attached.
- **PeopleSeeder:** Attaches directors (Wachowskis, Nolan) to movies – director search works.
- **ActorSeeder:** Creates Keanu Reeves, attaches to The Matrix as ACTOR – **actor-only and q+actor** work after seed.

So after `migrate --seed` (with seeders that run in non-production):

- Use cases 1–7 and 9–10 are covered for manual testing (actor-only requires ActorSeeder).
- Use cases 8, 11–15 depend on seed or test-created data.

---

## Fixtures Seeder for All Search Use Cases

**SearchFixturesSeeder** (`api/database/seeders/SearchFixturesSeeder.php`) is called from `DatabaseSeeder` in non-production. It:

1. **Year-only:** Creates a movie with `release_year=1985` ("Search Fixture Year 1985") so `?year=1985` returns at least one result.
2. **Multiple actors:** Attaches Laurence Fishburne to The Matrix so `?actor[]=Keanu&actor[]=Laurence` (or `q=Matrix&actor[]=Keanu&actor[]=Laurence`) returns The Matrix from seed data.

Together with **MovieSeeder** (Matrix, Inception), **PeopleSeeder** (directors), and **ActorSeeder** (Keanu Reeves → Matrix), running `php artisan migrate --seed` gives a single dataset so every use case in the matrix above has predictable data for manual testing.

---

## References

- Feature tests: `api/tests/Feature/SearchMoviesTest.php`, `MovieSearchLimitPerSourceTest.php`, `MovieSearchSortingTest.php`
- Seeders: `api/database/seeders/MovieSeeder.php`, `PeopleSeeder.php`, `ActorSeeder.php`
- Manual testing: `docs/MANUAL_TESTING_GUIDE.md` (Movies Search section), `docs/qa/MANUAL_TEST_PLANS.md`
- E2E: `tests/e2e/specs/` (no search endpoint coverage yet)
