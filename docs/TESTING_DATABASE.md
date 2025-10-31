# Jak działają testy w CI bez uruchomionej bazy danych?

## Krótka odpowiedź

**Baza działa w pamięci!** Używamy **SQLite `:memory:`** - nie potrzebujemy zewnętrznego serwera bazy danych.

## Szczegóły

### 1. Konfiguracja w `phpunit.xml`

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**`:memory:`** to specjalna nazwa bazy SQLite - oznacza bazę w pamięci RAM:
- ✅ Szybka (RAM jest najszybsza)
- ✅ Automatycznie tworzona przed testem
- ✅ Automatycznie usuwana po teście
- ✅ Nie zostawia śladów na dysku
- ✅ Nie potrzebuje zewnętrznego serwera

### 2. RefreshDatabase Trait

Wszystkie testy Feature używają:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MoviesApiTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');      // Uruchamia migracje
        $this->artisan('db:seed');      // Uruchamia seedery
    }
}
```

**RefreshDatabase** automatycznie:
1. Tworzy bazę przed każdym testem
2. Uruchamia wszystkie migracje
3. Może uruchomić seedery (jeśli `setUp()` ma `db:seed`)
4. Czyści bazę po teście

### 3. Jak to działa w CI?

```yaml
- name: Run unit/feature tests
  working-directory: api
  run: composer test
```

**Proces:**
1. PHPUnit ładuje `phpunit.xml`
2. Ustawia `DB_CONNECTION=sqlite` i `DB_DATABASE=:memory:`
3. Dla każdego testu Feature:
   - Tworzy nową bazę w pamięci
   - Uruchamia migracje (`$this->artisan('migrate')`)
   - Uruchamia seedery (`$this->artisan('db:seed')`)
   - Wykonuje test
   - Czyści bazę
4. Następny test zaczyna od nowa

### 4. Dlaczego to działa?

**SQLite** to:
- Wbudowana biblioteka PHP (nie serwer)
- Działa w tym samym procesie co PHP
- Nie wymaga instalacji, konfiguracji, czy uruchomienia serwera
- Idealna do testów

## Porównanie: Pamięć vs Plik vs Serwer

| Typ | Szybkość | Setup | Dla testów? |
|-----|----------|-------|-------------|
| `:memory:` | ⚡ Najszybsza | ✅ Zero setup | ✅ Idealna |
| Plik SQLite | 🐢 Wolniejsza | ✅ Zero setup | ✅ OK |
| PostgreSQL/MySQL | 🚀 Szybka | ❌ Wymaga serwera | ⚠️ Zbyt skomplikowane |

## Dlaczego nie używać prawdziwej bazy w CI?

❌ **Wymaga:**
- Instalacji MySQL/PostgreSQL
- Konfiguracji serwera
- Tworzenia użytkowników
- Dłuższy czas wykonania
- Więcej zasobów

✅ **SQLite `:memory:`:**
- Zero konfiguracji
- Szybka
- Wystarczająca do testów funkcjonalnych

## Czy można używać prawdziwej bazy w testach?

**Tak**, ale nie jest potrzebne:

```php
// W phpunit.xml zamiast :memory:
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="test_db"/>
```

Ale to wymaga:
- Uruchomionego MySQL/PostgreSQL w CI
- Więcej czasu na setup
- Często niepotrzebne - SQLite pokrywa 95% przypadków

## Aktualna konfiguracja w projekcie

```xml
<!-- api/phpunit.xml -->
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

**Rezultat:**
- ✅ Testy działają w CI bez setupu bazy
- ✅ Każdy test ma czystą bazę
- ✅ Migracje uruchamiane automatycznie
- ✅ Seedery uruchamiane w `setUp()`

## Sprawdzenie lokalne

```bash
# Sprawdź czy SQLite jest dostępne
php -r "echo extension_loaded('sqlite3') ? 'SQLite OK' : 'SQLite missing';"

# Uruchom testy
php artisan test

# Sprawdź co się dzieje
php artisan test --verbose
```

## Wymagania

- ✅ PHP z rozszerzeniem `sqlite3` (standardowo włączone)
- ✅ Migracje w `database/migrations/`
- ✅ Seedery w `database/seeders/`

## Podsumowanie

**Testy w CI działają bez zewnętrznej bazy, bo:**
1. Używamy SQLite `:memory:` (baza w RAM)
2. RefreshDatabase automatycznie tworzy bazę przed testem
3. Migracje uruchamiane są w `setUp()` każdego testu
4. SQLite nie wymaga serwera - to biblioteka w PHP

**To standardowe rozwiązanie w Laravel!** 🎯

