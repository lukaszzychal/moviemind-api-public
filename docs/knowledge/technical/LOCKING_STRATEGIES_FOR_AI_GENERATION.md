# Strategie blokad dla generowania AI (MovieMind)

> **Data utworzenia:** 2025-11-12  
> **Kontekst:** Analiza przyczyn duplikacji opisów filmów podczas równoległego uruchamiania jobów generujących treści AI  
> **Kategoria:** technical

## 🎯 Cel

Porównać stosowane i planowane mechanizmy blokad w procesie generowania opisów filmów, pokazać przykłady implementacji oraz uzasadnić rekomendację odejścia od `Cache::lock` na rzecz obsługi wyjątków unikalnego indeksu `movies.slug`.

## 📋 Warianty blokad

1. **`Cache::lock` (Redis lock przez Laravel Cache)**
   - *Jak działa?*  
     ```php
     Cache::lock("lock:movie:create:$slug", 30)->block(10, function () {
         // krytyczna sekcja – utworzenie filmu i opisu
     });
     ```
   - *Plusy:* prosty, dostępny „out of the box”, chroni szerszy fragment kodu (np. również promowanie opisu domyślnego).
   - *Minusy:* globalny mutex spowalnia równoległe joby; jeśli lock zwróci się po utworzeniu rekordu przez inny proces, kod musi samodzielnie wykryć nowy stan (w naszym przypadku powodowało to regenerację dodatkowych opisów).

2. **Unikalny indeks + obsługa wyjątku (rekomendowane)**
   - *Mechanizm:* polega na unikalnym indeksie `movies.slug`, który już mamy (`migrations/2025_10_30_000200_add_slugs_to_movies_and_people.php`). Tworzenie filmu odbywa się bez locka:
     ```php
     try {
         Movie::create([... 'slug' => $slug ...]);
     } catch (QueryException $e) {
         if ($this->isUniqueSlugViolation($e)) {
             $existing = Movie::whereSlug($slug)->first();
             $this->markDoneUsingExisting($existing);
         } else {
             throw $e;
         }
     }
     ```
   - *Plusy:* brak globalnej blokady, naturalna synchronizacja (baza gwarantuje brak duplikatów), prostszy kod, szybsze równoległe joby.
   - *Minusy:* wymaga dokładnej identyfikacji wyjątku (np. sprawdzenia kodu błędu PDO), nie chroni logiki poza samym `INSERT`.

3. **Blokada transakcyjna `SELECT ... FOR UPDATE`**
   - *Opis:* wybranie rekordu „bazowego” i zablokowanie go na czas generowania. Działa dobrze, gdy mamy rekord kontrolny (np. `movies` istnieje). W naszym scenariuszu brak jeszcze rekordu, więc trzeba użyć dodatkowej tabeli „locków”, co komplikuje rozwiązanie.
   - *Plusy:* gwarantowana spójność w obrębie transakcji, pełna kontrola nad zakresem blokady.
   - *Minusy:* wymaga PostgreSQL (w produkcji tak), ale komplikuje logikę w środowiskach testowych (SQLite ma ograniczone wsparcie), trzeba pilnować czasu życia transakcji.

4. **`SETNX` w Redis / Redlock**
   - *Opis:* niskopoziomowa blokada w Redisie (np. `SET resource my_random_value NX PX 30000`). Laravel Horizon i tak korzysta z Redisa, więc możemy użyć niestandardowego klienta.
   - *Plusy:* atomowe, szybkie, działa między procesami/hostami.
   - *Minusy:* trzeba pisać własny kod (lub użyć biblioteki), znów utrzymujemy zewnętrzny mutex, który nie eliminuje ryzyka „self-healingu” po wykryciu, że rekord już istnieje.

## 🔍 Porównanie

| Wariant                         | Overhead | Spójność | Złożoność | Ryzyko duplikacji opisów | Uwagi |
|---------------------------------|----------|----------|-----------|---------------------------|-------|
| `Cache::lock`                  | średni   | zależy od kodu po wyjściu z locka | niski | **Wysokie** (potrzeba dodatkowej logiki) | Obecnie obserwowany efekt „drugiego opisu” |
| Unikalny indeks + wyjątek      | niski    | gwarantowana przez DB | niski | niskie | Rekomendacja: prosty i deterministyczny |
| `SELECT ... FOR UPDATE`        | średni   | wysoka w obrębie transakcji | średni | niskie | Trzeba mieć rekord kontrolny lub dodatkową tabelę |
| `SETNX` / Redlock              | niski    | zależy od implementacji | średni | średnie | Nadal wymaga „manualnego” wykrywania stanu po zwolnieniu locka |

## ✅ Rekomendacja

- Usuwamy `Cache::lock` z `RealGenerateMovieJob`.
- Opieramy się na istniejącym indeksie `movies.slug`.
- Łapiemy `QueryException` i sprawdzamy, czy kod błędu PDO oznacza naruszenie unikalności (`23000` + `UNIQUE constraint failed: movies.slug` w SQLite, `23505` w PostgreSQL).
- Po złapaniu wyjątku pobieramy najnowszy film i aktualizujemy cache/job status, bez ponownego generowania opisu.
- Zachowujemy `Cache::lock` tylko w wąskich miejscach, gdzie naprawdę potrzebny (np. awans opisu domyślnego, jeśli wciąż chcemy mieć zabezpieczenie przed wyścigiem podczas zmiany `default_description_id`).

## 🧪 Przykład przepływu po zmianie

1. **Request A** (`slug = matrix-1999`): Job 1 startuje, tworzy film ✅.
2. **Request B** (ten sam slug, zanim A skończy): Job 2 startuje, nie widzi filmu, próbuje `INSERT`.
3. Job 2 dostaje `IntegrityConstraintViolationException`, łapie ją, pobiera świeży rekord, ustawia status `DONE` bez dodatkowego opisu.
4. Oba joby kończą z tym samym `description_id`.

## 🔗 Powiązane Dokumenty

- [Queue Async Explanation](./QUEUE_ASYNC_EXPLANATION.md)
- [Detecting ongoing queue jobs (EN)](./DETECTING_ONGOING_QUEUE_JOBS.en.md)
- [Locking Strategies for AI Generation (EN)](./LOCKING_STRATEGIES_FOR_AI_GENERATION.en.md)

## 📌 Notatki

- Po wdrożeniu warto dodać test funkcjonalny, który symuluje równoległe odpytanie endpointu (np. przy użyciu `ParallelTesting` lub ręcznego dispatchu jobów).
- W razie opóźnień po stronie AI można rozważyć osobną tabelę logów „generacji”, ale nie ma potrzeby dodawać kolejnego mechanizmu locków.
- Zastąpienie całego środowiska PostgreSQL-em i użycie `SELECT ... FOR UPDATE` dałoby deterministyczną blokadę, ale znacząco podniosłoby koszt utrzymania (brak wsparcia w SQLite dla testów, dodatkowe transakcje, konieczność osobnej tabeli „locków”). Dlatego preferujemy lekką blokadę Redis (`Cache::add`) + unikalny indeks.

---

**Ostatnia aktualizacja:** 2025-11-12

