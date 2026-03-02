# Strategia testowania: PostgreSQL (testy i produkcja)

## Obecna sytuacja

**Testy i produkcja używają PostgreSQL.**

- **Testy:** PostgreSQL (lokalnie: Docker `db`; CI: service container PostgreSQL 16). Baza testowa: `moviemind_test`.
- **Produkcja:** PostgreSQL. Ten sam dialekt SQL i te same funkcje (partial unique index, JSONB, `genres::text`, TO_CHAR itd.).

Szczegóły konfiguracji: [TESTING_DATABASE.md](TESTING_DATABASE.md).

## Czy testy są wiarygodne?

### ✅ **TAK** – środowisko testowe = PostgreSQL jak produkcja

**Testy weryfikują:**
- Logikę aplikacji, API, routing, walidację
- Eloquent ORM i relacje
- Raw SQL w stylu PostgreSQL (w tym partial unique index, JSONB, formatowanie dat)
- Ograniczenia bazy (np. unikalność aktywnych opisów)

### Poziomy testów

#### 1. **Unit Tests**
- **Środowisko:** PostgreSQL (ta sama konfiguracja co Feature)
- **Cel:** Logika biznesowa, walidacja, formatowanie

#### 2. **Feature Tests**
- **Środowisko:** PostgreSQL (Docker/CI)
- **Cel:** Endpointy API, np. `GET /api/v1/movies`, `POST /api/v1/generate`
- **RefreshDatabase** uruchamia migracje na bazie testowej

Klasa `PostgreSQLSpecificTest` (obecnie: testy funkcji bazy) nie pomija już testów – wszystkie testy działają na PostgreSQL.

## Rekomendacje

1. **Utrzymuj jeden stack:** PostgreSQL dla testów i produkcji (obecnie wdrożone).
2. **Lokalnie:** Zawsze uruchamiaj testy w Dockerze: `docker compose exec php php artisan test`.
3. **CI:** Job `test` w `.github/workflows/ci.yml` używa PostgreSQL 16.

## Powiązane dokumenty

- [TESTING_DATABASE.md](TESTING_DATABASE.md) – konfiguracja bazy dla testów
- [docs/qa/POSTGRESQL_TESTING.md](../../qa/POSTGRESQL_TESTING.md) – testy i CI

---

*Poniżej zachowano historyczną treść o SQLite vs PostgreSQL dla kontekstu. Obecnie wszystkie testy używają PostgreSQL.*

---

## Historyczna treść: Różnica SQLite vs Produkcja (przed migracją)

### Problem (dawniej)

**Testy:** SQLite `:memory:` – inny dialekt SQL niż produkcja.  
**Produkcja:** PostgreSQL – inne funkcje i ograniczenia.

### Kiedy SQLite mógł się różnić (nieaktualne – testy są na PostgreSQL)

1. Zaawansowane SQL (ILIKE, array_agg, JSON operators).
2. Partial unique index – SQLite nie obsługuje.
3. Różne formatowanie dat (strftime vs TO_CHAR).

### Obecne rozwiązanie

Wszystkie testy uruchamiane są na PostgreSQL. Różnice SQLite/PostgreSQL nie mają zastosowania.

--- 

