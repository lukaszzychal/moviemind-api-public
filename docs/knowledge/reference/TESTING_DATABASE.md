# Baza danych dla testów (PostgreSQL)

## Krótka odpowiedź

**Wszystkie testy używają PostgreSQL.** Lokalnie wymagany jest Docker z serwisem `db` (PostgreSQL). W CI używany jest service container PostgreSQL 16.

## Konfiguracja

### 1. `phpunit.xml.dist`

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_HOST" value="db"/>
<env name="DB_PORT" value="5432"/>
<env name="DB_DATABASE" value="moviemind_test"/>
<env name="DB_USERNAME" value="moviemind"/>
<env name="DB_PASSWORD" value="moviemind"/>
```

- **Lokalnie (Docker):** `DB_HOST=db` to nazwa serwisu PostgreSQL w `compose.yml`.
- **W CI:** Zmienne są nadpisywane przez workflow (`DB_HOST=localhost`, `DB_USERNAME=postgres`, `DB_PASSWORD=...`).

### 2. RefreshDatabase

Feature testy używają:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }
}
```

**RefreshDatabase** przy pierwszym teście wykonuje `migrate:fresh`. Baza `moviemind_test` musi istnieć (tworzy ją PostgreSQL w Docker/CI).

### 3. Uruchomienie testów

**Lokalnie (wymagany Docker):**

```bash
docker compose up -d
docker compose exec php php artisan test
```

Albo z katalogu `api`: `composer test` (skrypt uruchamia testy w kontenerze).

**W CI:** Job `test` w `.github/workflows/ci.yml` uruchamia PostgreSQL 16 jako service container i ustawia zmienne środowiskowe dla bazy testowej.

## Wymagania

- **Lokalnie:** Docker Compose z serwisem PostgreSQL (`db`). W `.env` (używanym przez kontener) ustawione `DB_CONNECTION=pgsql`, `DB_HOST=db`, oraz dane dostępowe do bazy.
- **CI:** Service container PostgreSQL 16, rozszerzenia PHP `pdo_pgsql`, `pgsql`.

## Dlaczego PostgreSQL dla testów?

- Jedna baza dla testów i produkcji – brak różnic składni (np. partial unique index, `genres::text`, TO_CHAR).
- Testy weryfikują te same zapytania i ograniczenia co produkcja.
- Brak osobnej konfiguracji SQLite i gałęzi w migracjach/serwisach.

## Powiązane pliki

- `api/phpunit.xml.dist` – zmienne środowiskowe dla testów
- `.github/workflows/ci.yml` – job `test` z service container PostgreSQL
- `docs/qa/POSTGRESQL_TESTING.md` – opis testów i CI
