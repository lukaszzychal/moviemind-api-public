# Wyszukiwanie pełnotekstowe i rozmyte (dokument objaśniający)

Dokument opisuje mechanizmy rozszerzenia wyszukiwania w MovieMind API: **wyszukiwanie pełnotekstowe** (stemming, ranking) oraz **wyszukiwanie rozmyte** (tolerancja literówek). Ma wspierać przyszłe zadanie wdrożeniowe (zob. `TASK-053` w backlogu zadań).

---

## Obecne zachowanie

Obecnie wyszukiwanie opiera się na dopasowaniu podciągu:

- **Filmy:** `LOWER(title) LIKE LOWER(?)` oraz analogicznie dla `director` i `genres` (zob. [MovieRepository](../../api/app/Repositories/MovieRepository.php)).
- **Seriale / osoby:** Podobne wzorce `LIKE` w odpowiednich repozytoriach.

Cechy:

- Brak **stemmingu** (np. „running” nie dopasuje „run”).
- Brak **tolerancji literówek** (np. „Matix” nie dopasuje „Matrix”).
- Brak **rankingu** według trafności.

---

## Wyszukiwanie pełnotekstowe (FTS)

**Cel:** Dopasowanie różnych form wyrazów i lepsza trafność.

**Podejście w PostgreSQL:**

- **`tsvector` / `tsquery`:** Przechowywanie znormalizowanej, przeszukiwalnej reprezentacji tekstu.
- **`to_tsvector('config', text)`:** Tworzy `tsvector` (tokenizacja, opcjonalnie stemming).
- **`to_tsquery('config', query)`:** Tworzy `tsquery` do dopasowania.
- **Ranking:** `ts_rank()` lub `ts_rank_cd()` do sortowania po trafności.
- **Indeks:** Indeks GIN na kolumnie `tsvector` dla szybkiego wyszukiwania.

**Konfiguracja:** Język (np. `'english'`, `'polish'`) dla stemmingu.

**Typowe kroki wdrożenia:**

1. Migracja: nowa kolumna (np. `title_tsvector`) lub kolumna generowana, uaktualniana triggerem lub w aplikacji.
2. W [MovieRepository](../../api/app/Repositories/MovieRepository.php) (oraz opcjonalnie [TvShowRepository](../../api/app/Repositories/TvShowRepository.php), [PersonRepository](../../api/app/Repositories/PersonRepository.php)) zastąpić lub uzupełnić `LIKE` przez `@@` (np. `whereRaw('title_tsvector @@ to_tsquery(?, ?)', [$config, $query])`) oraz dodać sortowanie po rankingu.

---

## Wyszukiwanie rozmyte (tolerancja literówek)

**Cel:** Uwzględnienie literówek i drobnych wariantów pisowni.

**Podejście w PostgreSQL:**

- **Rozszerzenie `pg_trgm`:** Podobieństwo na podstawie trigramów.
- **`similarity(a, b)`:** Wartość od 0 do 1.
- **`word_similarity(query, text)`:** Dopasowanie zapytania w dłuższym tekście.
- **`ILIKE '%query%'`:** Już używane; bez tolerancji literówek, za to prosty podciąg.

**Typowe kroki wdrożenia:**

1. Włączenie rozszerzenia: `CREATE EXTENSION IF NOT EXISTS pg_trgm;`
2. Opcjonalnie indeks GIN na `gin(kolumna gin_trgm_ops)` dla wyszukiwania trigramowego.
3. W repozytorium użycie `similarity()` lub `word_similarity()` w `WHERE` i/lub `ORDER BY`, aby faworyzować lepsze dopasowania.

---

## Możliwe podejścia

| Podejście | Zalety | Wady |
|-----------|--------|------|
| **Tylko FTS** | Stemming, ranking, uwzględnienie języka | Brak tolerancji literówek |
| **Tylko trigramy** | Tolerancja literówek, bez modelu tekstu | Mniej „semantyczne” niż FTS, wolniejsze bez starannego indeksowania |
| **Hybryda** | FTS jako główne dopasowanie + trigramy jako fallback lub sugestie | Większa złożoność, dwa ścieżki kodu |

Wdrożenie wymagałoby:

- **Migracji:** Nowe kolumny i/lub indeksy (np. `tsvector`, GIN dla trigramów).
- **Zmian w repozytoriach:** [MovieRepository](../../api/app/Repositories/MovieRepository.php), ewentualnie [TvShowRepository](../../api/app/Repositories/TvShowRepository.php) i [PersonRepository](../../api/app/Repositories/PersonRepository.php) przy szerszym zakresie.

---

## Odniesienia

- PostgreSQL: [Full Text Search](https://www.postgresql.org/docs/current/textsearch.html)
- PostgreSQL: [pg_trgm](https://www.postgresql.org/docs/current/pgtrgm.html)

---

**Powiązane zadanie:** `TASK-053` (Wyszukiwanie pełnotekstowe / tolerancja literówek) w backlogu zadań.
