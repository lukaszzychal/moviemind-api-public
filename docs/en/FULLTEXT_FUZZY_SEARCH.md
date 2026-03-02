# Full-Text and Fuzzy Search (Explanatory Document)

This document describes mechanisms for improving search in the MovieMind API: **full-text search** (stemming, ranking) and **fuzzy search** (typo tolerance). It is intended to support a future implementation task (see `TASK-053` in the task backlog).

---

## Current Behaviour

Search today uses substring matching:

- **Movies:** `LOWER(title) LIKE LOWER(?)`, and similarly for `director` and `genres` (see [MovieRepository](../../api/app/Repositories/MovieRepository.php) in the API).
- **TV shows / people:** Similar `LIKE`-based patterns in their repositories.

Characteristics:

- No **stemming** (e.g. "running" does not match "run").
- No **typo tolerance** (e.g. "Matix" does not match "Matrix").
- No **ranking** by relevance.

---

## Full-Text Search (FTS)

**Goal:** Match different word forms and improve relevance.

**PostgreSQL approach:**

- **`tsvector` / `tsquery`:** Store a normalized, searchable representation of text.
- **`to_tsvector('config', text)`:** Produces a `tsvector` (tokenized, optionally stemmed).
- **`to_tsquery('config', query)`:** Produces a `tsquery` for matching.
- **Ranking:** Use `ts_rank()` or `ts_rank_cd()` to order by relevance.
- **Index:** GIN index on the `tsvector` column for fast lookups.

**Config:** Language-specific (e.g. `'english'`, `'polish'`) for stemming.

**Typical steps for implementation:**

1. Add a migration: new column (e.g. `title_tsvector`) or generated column, updated by trigger or in application code.
2. In [MovieRepository](../../api/app/Repositories/MovieRepository.php) (and optionally [TvShowRepository](../../api/app/Repositories/TvShowRepository.php), [PersonRepository](../../api/app/Repositories/PersonRepository.php)), replace or complement `LIKE` with `@@` (e.g. `whereRaw('title_tsvector @@ to_tsquery(?, ?)', [$config, $query])`) and add ordering by rank.

---

## Fuzzy / Typo-Tolerant Search

**Goal:** Tolerate typos and minor spelling variations.

**PostgreSQL approach:**

- **Extension `pg_trgm`:** Provides trigram-based similarity.
- **`similarity(a, b)`:** Returns a score between 0 and 1.
- **`word_similarity(query, text)`:** Useful for matching a query within longer text.
- **`ILIKE '%query%'`:** Already used; no typo tolerance, but simple substring.

**Typical steps for implementation:**

1. Enable extension: `CREATE EXTENSION IF NOT EXISTS pg_trgm;`
2. Optionally add a GIN index on `gin(column gin_trgm_ops)` for trigram searches.
3. In the repository, use `similarity()` or `word_similarity()` in the `WHERE` clause and/or `ORDER BY` to prefer better matches.

---

## Possible Approaches

| Approach | Pros | Cons |
|----------|------|------|
| **FTS only** | Stemming, ranking, language-aware | No typo tolerance |
| **Trigram only** | Typo tolerance, no new text model | Less semantic than FTS, can be slower without careful indexing |
| **Hybrid** | FTS for primary match + trigram as fallback or for fuzzy suggestions | More complexity, two code paths |

Implementation would require:

- **Migrations:** New columns and/or indexes (e.g. `tsvector`, GIN trigram).
- **Repository changes:** [MovieRepository](../../api/app/Repositories/MovieRepository.php), and possibly [TvShowRepository](../../api/app/Repositories/TvShowRepository.php) and [PersonRepository](../../api/app/Repositories/PersonRepository.php) if scope is extended.

---

## References

- PostgreSQL: [Full Text Search](https://www.postgresql.org/docs/current/textsearch.html)
- PostgreSQL: [pg_trgm](https://www.postgresql.org/docs/current/pgtrgm.html)

---

**Related task:** `TASK-053` (Full-text / fuzzy search) in the task backlog.
