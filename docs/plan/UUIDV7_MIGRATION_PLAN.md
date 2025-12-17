# Plan migracji z autoinkrementacji int na UUIDv7

## 📋 Przegląd

Zmiana identyfikatorów z autoinkrementujących liczb całkowitych (`bigIncrements`) na UUIDv7 w całej aplikacji.

## 🎯 Cele

1. **Bezpieczeństwo** - UUID nie ujawnia informacji o liczbie rekordów
2. **Skalowalność** - łatwiejsze łączenie danych z wielu źródeł
3. **UUIDv7** - sortowalne, oparte na czasie (lepsze niż UUIDv4)

## 📊 Tabele do zmiany

### Główne tabele (wymagają zmiany ID + foreign keys):
1. `movies` - główna tabela filmów
2. `movie_descriptions` - opisy filmów
3. `people` - osoby (aktorzy, reżyserzy)
4. `person_bios` - biografie osób
5. `actors` - (deprecated, ale jeszcze używane)
6. `actor_bios` - (deprecated, ale jeszcze używane)
7. `genres` - gatunki
8. `tmdb_snapshots` - snapshoty z TMDb
9. `ai_jobs` - zadania AI

### Tabele pomocnicze (wymagają zmiany foreign keys):
- `movie_person` - relacja wiele-do-wielu
- `movie_genre` - relacja wiele-do-wielu

### Tabele systemowe (opcjonalnie):
- `users` - użytkownicy (Laravel default)
- `jobs` - kolejka Laravel (Laravel default)
- `cache` - cache Laravel (Laravel default)

## 🔧 Wymagane zmiany

### 1. Instalacja pakietu (jeśli potrzebny)

Laravel 12 domyślnie wspiera UUIDv7 przez trait `HasUuids`, ale sprawdź czy działa:

```bash
composer require symfony/uid
```

### 2. Migracje - zmiana struktury

#### Dla każdej tabeli głównej:
```php
// PRZED:
$table->id();

// PO:
$table->uuid('id')->primary();
```

#### Dla foreign keys:
```php
// PRZED:
$table->foreignId('movie_id')->constrained('movies');
$table->unsignedBigInteger('default_description_id');

// PO:
$table->foreignUuid('movie_id')->constrained('movies');
$table->uuid('default_description_id')->nullable();
```

### 3. Modele - dodanie traitu

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Movie extends Model
{
    use HasFactory, HasUuids;
    
    // ...
}
```

### 4. Kod aplikacji - aktualizacja typów

- Wszystkie miejsca używające `int` jako ID muszą być zmienione na `string`
- Type hints w metodach: `int $movieId` → `string $movieId`
- Casts w modelach: `'id' => 'integer'` → `'id' => 'string'` (lub usunąć)

## 📝 Lista plików do zmiany

### Migracje (12 plików):
1. `2025_10_30_000100_create_movies_table.php`
2. `2025_10_30_000110_create_movie_descriptions_table.php`
3. `2025_10_30_000120_create_actors_table.php`
4. `2025_10_30_000130_create_actor_bios_table.php`
5. `2025_10_30_000140_create_ai_jobs_table.php`
6. `2025_10_30_000150_create_genres_table.php`
7. `2025_10_30_000160_create_people_and_movie_person_tables.php`
8. `2025_10_30_000170_add_default_bio_and_person_bios.php`
9. `2025_12_17_020001_create_tmdb_snapshots_table.php`
10. `0001_01_01_000000_create_users_table.php` (opcjonalnie)
11. `0001_01_01_000002_create_jobs_table.php` (opcjonalnie)

### Modele (8+ plików):
1. `Movie.php`
2. `MovieDescription.php`
3. `Person.php`
4. `PersonBio.php`
5. `Actor.php` (deprecated)
6. `ActorBio.php` (deprecated)
7. `Genre.php`
8. `TmdbSnapshot.php`
9. `User.php` (opcjonalnie)

### Serwisy i inne klasy:
- Wszystkie miejsca używające `int` jako ID
- Type hints w metodach
- Testy (factory, assertions)

## ⚠️ Uwagi

1. **Migracja danych** - jeśli baza już zawiera dane, potrzebna będzie migracja danych (konwersja int → UUID)
2. **Testy** - wszystkie testy używające ID muszą być zaktualizowane
3. **Factories** - factory definitions mogą wymagać zmian
4. **Seeders** - seeders używające konkretnych ID
5. **Cache keys** - jeśli używają ID, mogą wymagać zmian

## 🚀 Plan wdrożenia

### Etap 1: Przygotowanie
- [ ] Dodać pakiet `symfony/uid` (jeśli potrzebny)
- [ ] Utworzyć nowe migracje zmieniające strukturę
- [ ] Dodać trait `HasUuids` do modeli
- [ ] Zaktualizować foreign keys w migracjach

### Etap 2: Kod aplikacji
- [ ] Zaktualizować type hints w serwisach
- [ ] Zaktualizować repositories
- [ ] Zaktualizować controllers
- [ ] Zaktualizować actions/jobs

### Etap 3: Testy
- [ ] Zaktualizować factories
- [ ] Zaktualizować testy jednostkowe
- [ ] Zaktualizować testy integracyjne
- [ ] Uruchomić wszystkie testy

### Etap 4: Migracja danych (jeśli potrzebna)
- [ ] Utworzyć skrypt migracji danych
- [ ] Przetestować na kopii produkcyjnej
- [ ] Wykonać migrację

### Etap 5: Weryfikacja
- [ ] Testy manualne
- [ ] Testy wydajnościowe
- [ ] Code review

## 📚 Dokumentacja

- Laravel UUID: https://laravel.com/docs/12.x/eloquent#uuid-and-ulid-keys
- Symfony UID: https://symfony.com/doc/current/components/uid.html
- UUIDv7 spec: https://www.ietf.org/rfc/rfc4122.txt

---

**Status:** 📝 Plan przygotowany  
**Data:** 2024-12-17  
**Priorytet:** Wysoki (duża zmiana, wymaga testów)

