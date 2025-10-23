# 🚀 Laravel Tutorial dla MovieMind API
## 🇵🇱 Przewodnik Laravel / 🇬🇧 Laravel Guide

---

## 📋 Spis Treści / Table of Contents

### 🇵🇱
1. [Wprowadzenie do Laravel](#wprowadzenie-do-laravel)
2. [Instalacja i Konfiguracja](#instalacja-i-konfiguracja)
3. [Struktura Projektu](#struktura-projektu)
4. [Modele i Migracje](#modele-i-migracje)
5. [Kontrolery i Routing](#kontrolery-i-routing)
6. [Laravel Nova - Admin Panel](#laravel-nova---admin-panel)
7. [Laravel Sanctum - API Authentication](#laravel-sanctum---api-authentication)
8. [Laravel Queue - Asynchroniczne Zadania](#laravel-queue---asynchroniczne-zadania)
9. [Laravel Telescope - Debugging](#laravel-telescope---debugging)
10. [Testy](#testy)
11. [Git Trunk Flow](#git-trunk-flow)
12. [Feature Flags](#feature-flags)
13. [Deployment](#deployment)

### 🇬🇧
1. [Laravel Introduction](#laravel-introduction)
2. [Installation and Configuration](#installation-and-configuration)
3. [Project Structure](#project-structure)
4. [Models and Migrations](#models-and-migrations)
5. [Controllers and Routing](#controllers-and-routing)
6. [Laravel Nova - Admin Panel](#laravel-nova---admin-panel-en)
7. [Laravel Sanctum - API Authentication](#laravel-sanctum---api-authentication-en)
8. [Laravel Queue - Asynchronous Tasks](#laravel-queue---asynchronous-tasks-en)
9. [Laravel Telescope - Debugging](#laravel-telescope---debugging-en)
10. [Testing](#testing)
11. [Git Trunk Flow](#git-trunk-flow-en)
12. [Feature Flags](#feature-flags-en)
13. [Deployment](#deployment-en)

---

## 🇵🇱 Wprowadzenie do Laravel

### 🎯 Co to jest Laravel?
Laravel to nowoczesny framework PHP, który znacząco ułatwia tworzenie aplikacji webowych. Został stworzony przez Taylor Otwell w 2011 roku i od tego czasu stał się jednym z najpopularniejszych frameworków PHP na świecie. Dla MovieMind API używamy Laravel 11 - najnowszej wersji, która wprowadza wiele ulepszeń wydajnościowych i nowych funkcji.

**Kluczowe cechy Laravel:**
- **Elegant Syntax** - czytelny i ekspresyjny kod
- **Artisan CLI** - potężne narzędzia wiersza poleceń
- **Blade Templating** - system szablonów z komponentami
- **Service Container** - zaawansowany system dependency injection
- **Eloquent ORM** - aktywny wzorzec ORM dla bazy danych

### 🧩 Dlaczego Laravel dla MovieMind API?

#### **Laravel Nova** - Gotowy Admin Panel
Laravel Nova to oficjalny pakiet administracyjny, który automatycznie generuje interfejs zarządzania danymi. Dla MovieMind API oznacza to:
- **Automatyczne CRUD** - tworzenie, czytanie, aktualizacja i usuwanie filmów/aktorów
- **Filtrowanie i wyszukiwanie** - zaawansowane opcje wyszukiwania
- **Relacje** - łatwe zarządzanie powiązaniami między filmami a opisami
- **Custom Actions** - niestandardowe akcje jak "Generuj opis AI"

#### **Eloquent ORM** - Zarządzanie Bazą Danych
Eloquent to aktywny wzorzec ORM, który mapuje tabele bazy danych na klasy PHP:
```php
// Zamiast surowego SQL:
// SELECT * FROM movies WHERE release_year > 2020

// Używamy eleganckiego Eloquent:
Movie::where('release_year', '>', 2020)->get();
```

#### **Laravel Sanctum** - Autoryzacja API
Sanctum zapewnia prostą autoryzację API dla Single Page Applications (SPA), aplikacji mobilnych i prostych tokenów API:
- **API Tokens** - dla klientów zewnętrznych (RapidAPI)
- **SPA Authentication** - dla panelu administracyjnego
- **Session Authentication** - dla tradycyjnych aplikacji webowych

#### **Laravel Queue** - Asynchroniczne Zadania AI
System kolejek Laravel pozwala na wykonywanie zadań w tle, co jest kluczowe dla MovieMind API:
- **AI Generation** - generowanie opisów nie blokuje API
- **Webhook Delivery** - wysyłanie powiadomień asynchronicznie
- **Email Sending** - wysyłanie emaili w tle
- **File Processing** - przetwarzanie dużych plików

#### **Laravel Telescope** - Debugging i Monitoring
Telescope to narzędzie do debugowania aplikacji Laravel w czasie rzeczywistym:
- **Request Monitoring** - śledzenie wszystkich żądań HTTP
- **Query Logging** - monitorowanie zapytań do bazy danych
- **Job Monitoring** - śledzenie zadań w kolejkach
- **Exception Tracking** - śledzenie błędów i wyjątków

---

## 📚 Słownik Terminów Laravel / Laravel Glossary

### 🔤 Podstawowe Terminy / Basic Terms

| Termin / Term | Opis / Description | Przykład / Example |
|---------------|-------------------|-------------------|
| **Artisan** | CLI narzędzie Laravel do automatyzacji zadań | `php artisan make:controller MovieController` |
| **Blade** | System szablonów Laravel z składnią PHP | `@if($movie) {{ $movie->title }} @endif` |
| **Eloquent** | ORM (Object-Relational Mapping) Laravel | `Movie::find(1)` zamiast SQL |
| **Middleware** | Warstwa między żądaniem a odpowiedzią | Sprawdzanie autoryzacji, logowanie |
| **Migration** | Pliki definiujące zmiany w strukturze bazy | Tworzenie/usuwanie tabel |
| **Model** | Klasa reprezentująca tabelę w bazie danych | `Movie` model dla tabeli `movies` |
| **Route** | Definicja URL i odpowiadającej mu akcji | `Route::get('/movies', [MovieController::class, 'index'])` |
| **Service Container** | System dependency injection Laravel | Automatyczne wstrzykiwanie zależności |
| **Seeder** | Klasa do wypełniania bazy danych przykładowymi danymi | `MovieSeeder` z przykładowymi filmami |
| **Validation** | Sprawdzanie poprawności danych wejściowych | `'title' => 'required|string|max:255'` |

### 🏗️ Architektura / Architecture Terms

| Termin / Term | Opis / Description | Użycie w MovieMind API / Usage in MovieMind API |
|---------------|-------------------|--------------------------------------------------|
| **Controller** | Klasa obsługująca logikę HTTP | `MovieController` - obsługa żądań API |
| **Service** | Klasa zawierająca logikę biznesową | `AIService` - generowanie opisów AI |
| **Job** | Klasa do wykonywania zadań w tle | `GenerateDescriptionJob` - generowanie opisów |
| **Event** | Powiadomienie o wystąpieniu zdarzenia | `DescriptionGenerated` - po wygenerowaniu opisu |
| **Listener** | Klasa reagująca na eventy | `SendWebhookNotification` - wysyłanie webhooków |
| **Observer** | Klasa monitorująca zmiany w modelu | `MovieObserver` - logowanie zmian filmów |
| **Policy** | Klasa definiująca uprawnienia | `MoviePolicy` - kto może edytować filmy |
| **Resource** | Klasa transformująca dane do API | `MovieResource` - formatowanie odpowiedzi JSON |

### 🔧 Narzędzia Deweloperskie / Developer Tools

| Narzędzie / Tool | Opis / Description | Komenda / Command |
|------------------|-------------------|-------------------|
| **Telescope** | Debugging i monitoring aplikacji | `php artisan telescope:install` |
| **Horizon** | Dashboard dla kolejek Redis | `php artisan horizon:install` |
| **Nova** | Admin panel dla aplikacji | `composer require laravel/nova` |
| **Sanctum** | Autoryzacja API | `composer require laravel/sanctum` |
| **Scout** | Pełnotekstowe wyszukiwanie | `composer require laravel/scout` |
| **Cashier** | Płatności Stripe | `composer require laravel/cashier` |

---

## 🇬🇧 Laravel Introduction

### 🎯 What is Laravel?
Laravel is a PHP framework that makes web application development easier. For MovieMind API we use Laravel 11 - the latest version.

### 🧩 Why Laravel for MovieMind API?
- **Laravel Nova** - ready admin panel
- **Eloquent ORM** - easy database management
- **Laravel Sanctum** - API authentication
- **Laravel Queue** - asynchronous AI tasks
- **Laravel Telescope** - debugging and monitoring

---

## 🇵🇱 Instalacja i Konfiguracja

### 📦 Wymagania Systemowe
Przed instalacją Laravel upewnij się, że masz zainstalowane:

#### **PHP 8.3+** (Wymagane)
```bash
# Sprawdź wersję PHP
php --version

# Wymagane rozszerzenia PHP:
# - BCMath PHP Extension
# - Ctype PHP Extension  
# - cURL PHP Extension
# - DOM PHP Extension
# - Fileinfo PHP Extension
# - JSON PHP Extension
# - Mbstring PHP Extension
# - OpenSSL PHP Extension
# - PCRE PHP Extension
# - PDO PHP Extension
# - Tokenizer PHP Extension
# - XML PHP Extension
```

#### **Composer** (Menedżer Zależności)
```bash
# Instalacja Composer (Linux/macOS)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Sprawdź instalację
composer --version
```

#### **PostgreSQL** (Baza Danych)
```bash
# Ubuntu/Debian
sudo apt-get install postgresql postgresql-contrib

# macOS (Homebrew)
brew install postgresql

# Sprawdź instalację
psql --version
```

#### **Redis** (Cache i Kolejki)
```bash
# Ubuntu/Debian
sudo apt-get install redis-server

# macOS (Homebrew)
brew install redis

# Sprawdź instalację
redis-cli --version
```

### 🚀 Instalacja Laravel 11

#### **Krok 1: Tworzenie Projektu**
```bash
# Tworzenie nowego projektu Laravel 11
composer create-project laravel/laravel:^11.0 src-laravel

# Przejście do katalogu projektu
cd src-laravel

# Sprawdzenie struktury projektu
ls -la
```

#### **Krok 2: Instalacja Zależności**
```bash
# Instalacja wszystkich pakietów z composer.json
composer install

# Generowanie klucza aplikacji
php artisan key:generate

# Sprawdzenie konfiguracji
php artisan about
```

#### **Krok 3: Konfiguracja Środowiska**
```bash
# Kopiowanie pliku środowiskowego
cp .env.example .env

# Edycja konfiguracji
nano .env
```

### ⚙️ Szczegółowa Konfiguracja .env

#### **Podstawowe Ustawienia Aplikacji**
```bash
# .env - Podstawowe ustawienia
APP_NAME="MovieMind API"
APP_ENV=local                    # local, staging, production
APP_KEY=base64:...              # Generowany przez artisan key:generate
APP_DEBUG=true                   # true dla development, false dla production
APP_TIMEZONE=Europe/Warsaw       # Strefa czasowa
APP_URL=http://localhost:8001   # URL aplikacji
```

#### **Konfiguracja Bazy Danych PostgreSQL**
```bash
# .env - Baza danych
DB_CONNECTION=pgsql              # Typ bazy danych
DB_HOST=127.0.0.1               # Host bazy danych (lub 'db' dla Docker)
DB_PORT=5432                     # Port PostgreSQL
DB_DATABASE=moviemind            # Nazwa bazy danych
DB_USERNAME=moviemind            # Użytkownik bazy danych
DB_PASSWORD=moviemind            # Hasło bazy danych

# Opcjonalne ustawienia zaawansowane
DB_SCHEMA=public                 # Schemat PostgreSQL
DB_SSLMODE=prefer               # Tryb SSL (disable, prefer, require)
```

#### **Konfiguracja Redis (Cache i Kolejki)**
```bash
# .env - Redis
REDIS_HOST=127.0.0.1            # Host Redis (lub 'redis' dla Docker)
REDIS_PASSWORD=null              # Hasło Redis (null jeśli brak)
REDIS_PORT=6379                  # Port Redis
REDIS_DB=0                       # Numer bazy danych Redis

# Cache
CACHE_DRIVER=redis               # redis, file, database, array
CACHE_PREFIX=moviemind           # Prefiks dla kluczy cache

# Kolejki
QUEUE_CONNECTION=redis           # redis, database, sync, sqs
REDIS_QUEUE=default              # Nazwa kolejki Redis
```

#### **Konfiguracja AI i Zewnętrznych API**
```bash
# .env - AI i API
OPENAI_API_KEY=sk-...            # Klucz API OpenAI
OPENAI_MODEL=gpt-4o-mini         # Model AI do użycia
OPENAI_MAX_TOKENS=1000           # Maksymalna liczba tokenów
OPENAI_TEMPERATURE=0.7           # Kreatywność odpowiedzi (0.0-2.0)

# RapidAPI
RAPIDAPI_KEY=your-key-here       # Klucz RapidAPI
RAPIDAPI_PLAN=FREE               # Plan RapidAPI (FREE, PRO, ENTERPRISE)

# Rate Limiting
API_RATE_LIMIT=60/minute         # Limit żądań na minutę
```

#### **Konfiguracja Email i Powiadomień**
```bash
# .env - Email
MAIL_MAILER=smtp                 # smtp, sendmail, mailgun, ses
MAIL_HOST=127.0.0.1              # Host SMTP (lub 'mailhog' dla Docker)
MAIL_PORT=1025                   # Port SMTP
MAIL_USERNAME=null               # Użytkownik SMTP
MAIL_PASSWORD=null               # Hasło SMTP
MAIL_ENCRYPTION=null             # Szyfrowanie (tls, ssl, null)
MAIL_FROM_ADDRESS="noreply@moviemind.dev"
MAIL_FROM_NAME="MovieMind API"

# Webhooki
WEBHOOK_SECRET=your-webhook-secret
WEBHOOK_TIMEOUT=30               # Timeout dla webhooków (sekundy)
```

### 🔧 Pierwsze Kroki po Instalacji

#### **Krok 1: Uruchomienie Migracji**
```bash
# Tworzenie tabel w bazie danych
php artisan migrate

# Sprawdzenie statusu migracji
php artisan migrate:status
```

#### **Krok 2: Wypełnienie Bazy Danych**
```bash
# Uruchomienie seederów
php artisan db:seed

# Lub konkretnego seeder
php artisan db:seed --class=MovieSeeder
```

#### **Krok 3: Uruchomienie Serwera Deweloperskiego**
```bash
# Uruchomienie serwera na porcie 8001
php artisan serve --port=8001

# Aplikacja będzie dostępna pod adresem:
# http://localhost:8001
```

#### **Krok 4: Sprawdzenie Instalacji**
```bash
# Sprawdzenie wszystkich komponentów
php artisan about

# Sprawdzenie konfiguracji cache
php artisan config:cache

# Sprawdzenie tras
php artisan route:list
```

---

## 🇬🇧 Installation and Configuration

### 📦 Requirements
- PHP 8.3+
- Composer
- PostgreSQL
- Redis

### 🚀 Laravel Installation
```bash
# Install Laravel 11
composer create-project laravel/laravel:^11.0 src-laravel

# Navigate to directory
cd src-laravel

# Install dependencies
composer install
```

### ⚙️ .env Configuration
```bash
# .env
APP_NAME="MovieMind API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001

# Database
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=moviemind
DB_USERNAME=moviemind
DB_PASSWORD=moviemind

# Cache
CACHE_DRIVER=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis
```

---

## 🇵🇱 Struktura Projektu

### 📁 Struktura katalogów Laravel
```
src-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Services/
│   ├── Jobs/
│   └── Providers/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── nova.php
├── resources/
│   ├── views/
│   └── lang/
├── tests/
├── composer.json
└── artisan
```

### 🎯 Kluczowe katalogi dla MovieMind API:
- **app/Models/** - modele Eloquent (Movie, Actor, Description)
- **app/Http/Controllers/** - kontrolery API
- **app/Services/** - logika biznesowa (AIService, CacheService)
- **app/Jobs/** - asynchroniczne zadania AI
- **database/migrations/** - struktura bazy danych

---

## 🇬🇧 Project Structure

### 📁 Laravel Directory Structure
```
src-laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Services/
│   ├── Jobs/
│   └── Providers/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── nova.php
├── resources/
│   ├── views/
│   └── lang/
├── tests/
├── composer.json
└── artisan
```

### 🎯 Key directories for MovieMind API:
- **app/Models/** - Eloquent models (Movie, Actor, Description)
- **app/Http/Controllers/** - API controllers
- **app/Services/** - business logic (AIService, CacheService)
- **app/Jobs/** - asynchronous AI tasks
- **database/migrations/** - database structure

---

## 🇵🇱 Modele i Migracje

### 🎯 Wprowadzenie do Eloquent ORM

Eloquent ORM to aktywny wzorzec implementacji Object-Relational Mapping (ORM) w Laravel. Pozwala na interakcję z bazą danych używając składni obiektowej PHP zamiast surowego SQL.

#### **Kluczowe Koncepcje Eloquent:**
- **Model** - klasa reprezentująca tabelę w bazie danych
- **Migration** - plik definiujący strukturę tabeli
- **Relationship** - powiązania między modelami
- **Accessor/Mutator** - metody do formatowania danych
- **Scopes** - zapytania wielokrotnego użytku
- **Events** - hooki na zdarzenia modelu

### 🎬 Model Movie - Szczegółowa Analiza

```php
<?php
// app/Models/Movie.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movie extends Model
{
    use HasFactory; // Umożliwia tworzenie factory dla testów

    /**
     * Nazwa tabeli w bazie danych
     * Laravel automatycznie używa liczby mnogiej nazwy klasy
     */
    protected $table = 'movies';

    /**
     * Klucz główny tabeli
     * Domyślnie 'id'
     */
    protected $primaryKey = 'id';

    /**
     * Typ klucza głównego
     */
    protected $keyType = 'int';

    /**
     * Czy klucz główny jest auto-increment
     */
    public $incrementing = true;

    /**
     * Pola, które można masowo przypisywać (Mass Assignment Protection)
     * Bezpieczeństwo przed atakami mass assignment
     */
    protected $fillable = [
        'title',                    // Tytuł filmu
        'release_year',            // Rok wydania
        'director',                // Reżyser
        'genres',                  // Gatunki (JSON array)
        'default_description_id',  // ID domyślnego opisu
        'source_of_truth_locale'   // Lokalizacja źródła prawdy
    ];

    /**
     * Pola, które NIE mogą być masowo przypisywane
     * Alternatywa dla $fillable
     */
    protected $guarded = [
        'id',                      // ID nie może być zmieniane
        'created_at',              // Timestampy są automatyczne
        'updated_at'
    ];

    /**
     * Automatyczne rzutowanie typów danych
     * Zapewnia poprawne typy przy pobieraniu z bazy
     */
    protected $casts = [
        'genres' => 'array',           // JSON string → PHP array
        'release_year' => 'integer',   // String → Integer
        'created_at' => 'datetime',    // String → Carbon instance
        'updated_at' => 'datetime',
        'is_featured' => 'boolean',    // 0/1 → true/false
        'rating' => 'decimal:2'        // Float z 2 miejscami po przecinku
    ];

    /**
     * Relacja One-to-Many z MovieDescription
     * Jeden film może mieć wiele opisów (różne języki, konteksty)
     */
    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }

    /**
     * Relacja BelongsTo z MovieDescription (domyślny opis)
     * Jeden film ma jeden domyślny opis
     */
    public function defaultDescription(): BelongsTo
    {
        return $this->belongsTo(MovieDescription::class, 'default_description_id');
    }

    /**
     * Scope - zapytanie wielokrotnego użytku
     * Użycie: Movie::recent()->get()
     */
    public function scopeRecent($query)
    {
        return $query->where('release_year', '>=', 2020);
    }

    /**
     * Scope z parametrem
     * Użycie: Movie::byYear(2023)->get()
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('release_year', $year);
    }

    /**
     * Accessor - formatowanie danych przy pobieraniu
     * Użycie: $movie->formatted_title
     */
    public function getFormattedTitleAttribute(): string
    {
        return ucwords(strtolower($this->title));
    }

    /**
     * Mutator - formatowanie danych przed zapisem
     * Automatycznie wywoływany przy $movie->title = 'nowy tytuł'
     */
    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = trim($value);
    }

    /**
     * Metoda pomocnicza - sprawdza czy film ma opis
     */
    public function hasDescription(): bool
    {
        return $this->descriptions()->exists();
    }

    /**
     * Metoda pomocnicza - pobiera opis w określonym języku
     */
    public function getDescriptionInLocale(string $locale): ?MovieDescription
    {
        return $this->descriptions()
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Event - wywoływany przed zapisem modelu
     */
    protected static function booted(): void
    {
        static::creating(function ($movie) {
            // Automatyczne ustawienie source_of_truth_locale jeśli nie ustawione
            if (empty($movie->source_of_truth_locale)) {
                $movie->source_of_truth_locale = 'en-US';
            }
        });

        static::saved(function ($movie) {
            // Logowanie zmian filmu
            \Log::info("Movie saved: {$movie->title}");
        });
    }
}
```

### 🎭 Model Actor
```php
<?php
// app/Models/Actor.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actor extends Model
{
    protected $fillable = [
        'name',
        'birth_date',
        'birthplace',
        'default_bio_id',
        'source_of_truth_locale'
    ];

    protected $casts = [
        'birth_date' => 'date'
    ];

    public function bios(): HasMany
    {
        return $this->hasMany(ActorBio::class);
    }

    public function defaultBio()
    {
        return $this->belongsTo(ActorBio::class, 'default_bio_id');
    }
}
```

### 📝 Model MovieDescription
```php
<?php
// app/Models/MovieDescription.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieDescription extends Model
{
    protected $fillable = [
        'movie_id',
        'locale',
        'text',
        'context_tag',
        'origin',
        'ai_model',
        'quality_score',
        'plagiarism_score',
        'selected_default'
    ];

    protected $casts = [
        'quality_score' => 'float',
        'plagiarism_score' => 'float',
        'selected_default' => 'boolean'
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
```

### 🗄️ Migracje - Szczegółowy Przewodnik

Migracje to pliki definiujące zmiany w strukturze bazy danych. Pozwalają na wersjonowanie schematu bazy danych i współpracę w zespole.

#### **Tworzenie Migracji**
```bash
# Tworzenie migracji dla tabeli movies
php artisan make:migration create_movies_table

# Tworzenie migracji z modelem (automatycznie tworzy strukturę)
php artisan make:migration create_movies_table --create=movies

# Tworzenie migracji modyfikującej istniejącą tabelę
php artisan make:migration add_rating_to_movies_table --table=movies
```

#### **Szczegółowa Migracja Movies**
```php
<?php
// database/migrations/2025_01_27_000001_create_movies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nazwa tabeli docelowej
     */
    protected $table = 'movies';

    /**
     * Uruchamiane przy wykonaniu migracji
     * Tworzy strukturę tabeli
     */
    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            // Klucz główny - auto-increment integer
            $table->id();
            
            // Podstawowe informacje o filmie
            $table->string('title')->index(); // Indeks dla szybkiego wyszukiwania
            $table->smallInteger('release_year')->index(); // Indeks dla filtrowania po roku
            $table->string('director')->index(); // Indeks dla wyszukiwania po reżyserze
            
            // Gatunki jako JSON array
            $table->json('genres')->nullable(); // Może być null
            
            // Metadane
            $table->string('imdb_id', 20)->unique()->nullable(); // Unikalny ID z IMDb
            $table->string('tmdb_id', 20)->unique()->nullable(); // Unikalny ID z TMDb
            $table->decimal('rating', 3, 1)->nullable(); // Ocena 0.0-10.0
            $table->integer('runtime')->nullable(); // Czas trwania w minutach
            
            // Relacje
            $table->foreignId('default_description_id')
                  ->nullable()
                  ->constrained('movie_descriptions')
                  ->onDelete('set null'); // Jeśli opis zostanie usunięty, ustaw null
                  
            // Lokalizacja i język
            $table->string('source_of_truth_locale', 10)
                  ->default('en-US')
                  ->index(); // Język źródła danych
                  
            // Flagi i statusy
            $table->boolean('is_featured')->default(false); // Czy film jest wyróżniony
            $table->boolean('is_active')->default(true); // Czy film jest aktywny
            $table->enum('status', ['draft', 'published', 'archived'])
                  ->default('draft')
                  ->index(); // Status publikacji
                  
            // Timestamps - automatycznie dodaje created_at i updated_at
            $table->timestamps();
            
            // Indeksy złożone dla wydajności
            $table->index(['release_year', 'is_active']); // Filtrowanie aktywnych filmów z danego roku
            $table->index(['director', 'release_year']); // Wyszukiwanie po reżyserze i roku
            
            // Indeks pełnotekstowy dla wyszukiwania (PostgreSQL)
            $table->rawIndex('to_tsvector(\'english\', title || \' \' || director)', 'movies_search_idx');
        });
    }

    /**
     * Uruchamiane przy cofaniu migracji
     * Usuwa strukturę tabeli
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
```

#### **Migracja MovieDescriptions**
```php
<?php
// database/migrations/2025_01_27_000002_create_movie_descriptions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_descriptions', function (Blueprint $table) {
            $table->id();
            
            // Relacja z filmem
            $table->foreignId('movie_id')
                  ->constrained('movies')
                  ->onDelete('cascade'); // Jeśli film zostanie usunięty, usuń opisy
                  
            // Lokalizacja opisu
            $table->string('locale', 10)->index(); // pl-PL, en-US, de-DE
            $table->text('text'); // Treść opisu
            
            // Metadane opisu
            $table->string('context_tag')->nullable(); // modern, classic, technical
            $table->enum('origin', ['GENERATED', 'MANUAL', 'IMPORTED'])
                  ->default('GENERATED')
                  ->index();
                  
            // Informacje o AI
            $table->string('ai_model')->nullable(); // gpt-4, gpt-3.5-turbo
            $table->decimal('quality_score', 3, 2)->nullable(); // 0.00-1.00
            $table->decimal('plagiarism_score', 3, 2)->nullable(); // 0.00-1.00
            
            // Flagi
            $table->boolean('selected_default')->default(false); // Czy to domyślny opis
            $table->boolean('is_approved')->default(false); // Czy opis został zatwierdzony
            
            // Timestamps
            $table->timestamps();
            
            // Indeksy
            $table->index(['movie_id', 'locale']); // Unikalny opis per film per język
            $table->index(['origin', 'is_approved']); // Filtrowanie zatwierdzonych opisów
            
            // Unikalny indeks - jeden domyślny opis per film
            $table->unique(['movie_id', 'selected_default'], 'unique_default_per_movie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_descriptions');
    }
};
```

#### **Migracja Modyfikująca Istniejącą Tabelę**
```php
<?php
// database/migrations/2025_01_27_000003_add_rating_to_movies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            // Dodanie nowej kolumny
            $table->decimal('rating', 3, 1)->nullable()->after('runtime');
            
            // Dodanie indeksu
            $table->index('rating');
            
            // Dodanie ograniczenia (constraint)
            $table->check('rating >= 0 AND rating <= 10', 'rating_range_check');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            // Usunięcie ograniczenia
            $table->dropCheck('rating_range_check');
            
            // Usunięcie indeksu
            $table->dropIndex(['rating']);
            
            // Usunięcie kolumny
            $table->dropColumn('rating');
        });
    }
};
```

#### **Uruchamianie Migracji**
```bash
# Uruchomienie wszystkich migracji
php artisan migrate

# Uruchomienie migracji z wyświetlaniem SQL
php artisan migrate --pretend

# Uruchomienie konkretnej migracji
php artisan migrate --path=/database/migrations/2025_01_27_000001_create_movies_table.php

# Sprawdzenie statusu migracji
php artisan migrate:status

# Cofnięcie ostatniej migracji
php artisan migrate:rollback

# Cofnięcie wszystkich migracji
php artisan migrate:reset

# Cofnięcie i ponowne uruchomienie
php artisan migrate:refresh

# Cofnięcie, uruchomienie i seedowanie
php artisan migrate:refresh --seed
```

---

## 🇬🇧 Models and Migrations

### 🎬 Movie Model
```php
<?php
// app/Models/Movie.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    protected $fillable = [
        'title',
        'release_year',
        'director',
        'genres',
        'default_description_id',
        'source_of_truth_locale'
    ];

    protected $casts = [
        'genres' => 'array',
        'release_year' => 'integer'
    ];

    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }

    public function defaultDescription()
    {
        return $this->belongsTo(MovieDescription::class, 'default_description_id');
    }
}
```

### 🎭 Actor Model
```php
<?php
// app/Models/Actor.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actor extends Model
{
    protected $fillable = [
        'name',
        'birth_date',
        'birthplace',
        'default_bio_id',
        'source_of_truth_locale'
    ];

    protected $casts = [
        'birth_date' => 'date'
    ];

    public function bios(): HasMany
    {
        return $this->hasMany(ActorBio::class);
    }

    public function defaultBio()
    {
        return $this->belongsTo(ActorBio::class, 'default_bio_id');
    }
}
```

### 📝 MovieDescription Model
```php
<?php
// app/Models/MovieDescription.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieDescription extends Model
{
    protected $fillable = [
        'movie_id',
        'locale',
        'text',
        'context_tag',
        'origin',
        'ai_model',
        'quality_score',
        'plagiarism_score',
        'selected_default'
    ];

    protected $casts = [
        'quality_score' => 'float',
        'plagiarism_score' => 'float',
        'selected_default' => 'boolean'
    ];

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
```

### 🗄️ Migrations
```php
<?php
// database/migrations/2025_01_27_000001_create_movies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->smallInteger('release_year');
            $table->string('director');
            $table->json('genres');
            $table->foreignId('default_description_id')->nullable();
            $table->string('source_of_truth_locale', 10)->default('en-US');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
```

---

## 🇵🇱 Kontrolery i Routing

### 🎬 MovieController
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Movie::query();

        if ($request->has('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $movies = $query->with('defaultDescription')->paginate(20);

        return response()->json([
            'data' => $movies->items(),
            'total' => $movies->total(),
            'per_page' => $movies->perPage(),
            'current_page' => $movies->currentPage()
        ]);
    }

    public function show(Movie $movie): JsonResponse
    {
        $movie->load('defaultDescription');
        
        return response()->json([
            'data' => $movie
        ]);
    }

    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        $context = $request->input('context', 'modern');
        $locale = $request->input('locale', 'en-US');

        // Dispatch job to generate description
        GenerateDescriptionJob::dispatch($movie, $context, $locale);

        return response()->json([
            'message' => 'Description generation started',
            'movie_id' => $movie->id,
            'context' => $context,
            'locale' => $locale
        ], 202);
    }
}
```

### 🛣️ Routing
```php
<?php
// routes/api.php
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ActorController;

Route::prefix('v1')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/{movie}', [MovieController::class, 'show']);
    Route::post('/movies/{movie}/generate', [MovieController::class, 'generateDescription']);
    
    Route::get('/actors/{actor}', [ActorController::class, 'show']);
    Route::post('/actors/{actor}/generate', [ActorController::class, 'generateBio']);
});
```

---

## 🇬🇧 Controllers and Routing

### 🎬 MovieController
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Movie::query();

        if ($request->has('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $movies = $query->with('defaultDescription')->paginate(20);

        return response()->json([
            'data' => $movies->items(),
            'total' => $movies->total(),
            'per_page' => $movies->perPage(),
            'current_page' => $movies->currentPage()
        ]);
    }

    public function show(Movie $movie): JsonResponse
    {
        $movie->load('defaultDescription');
        
        return response()->json([
            'data' => $movie
        ]);
    }

    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        $context = $request->input('context', 'modern');
        $locale = $request->input('locale', 'en-US');

        // Dispatch job to generate description
        GenerateDescriptionJob::dispatch($movie, $context, $locale);

        return response()->json([
            'message' => 'Description generation started',
            'movie_id' => $movie->id,
            'context' => $context,
            'locale' => $locale
        ], 202);
    }
}
```

### 🛣️ Routing
```php
<?php
// routes/api.php
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ActorController;

Route::prefix('v1')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/{movie}', [MovieController::class, 'show']);
    Route::post('/movies/{movie}/generate', [MovieController::class, 'generateDescription']);
    
    Route::get('/actors/{actor}', [ActorController::class, 'show']);
    Route::post('/actors/{actor}/generate', [ActorController::class, 'generateBio']);
});
```

---

## 🇵🇱 Laravel Nova - Admin Panel

### 📦 Instalacja Laravel Nova
```bash
# Instalacja Nova (wymaga licencji)
composer require laravel/nova

# Publikacja zasobów
php artisan nova:install
```

### 🎬 Nova Resource - Movie
```php
<?php
// app/Nova/Movie.php
namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class Movie extends Resource
{
    public static string $model = \App\Models\Movie::class;

    public static string $title = 'title';

    public static array $search = [
        'title', 'director'
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Title')
                ->sortable()
                ->rules('required', 'max:255'),

            Number::make('Release Year')
                ->sortable()
                ->rules('required', 'integer', 'min:1900', 'max:2030'),

            Text::make('Director')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make('Genres')
                ->help('Enter genres separated by commas'),

            BelongsTo::make('Default Description', 'defaultDescription', MovieDescription::class)
                ->nullable(),

            HasMany::make('Descriptions', 'descriptions', MovieDescription::class),
        ];
    }
}
```

---

## 🇬🇧 Laravel Nova - Admin Panel

### 📦 Laravel Nova Installation
```bash
# Install Nova (requires license)
composer require laravel/nova

# Publish resources
php artisan nova:install
```

### 🎬 Nova Resource - Movie
```php
<?php
// app/Nova/Movie.php
namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Http\Requests\NovaRequest;

class Movie extends Resource
{
    public static string $model = \App\Models\Movie::class;

    public static string $title = 'title';

    public static array $search = [
        'title', 'director'
    ];

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Title')
                ->sortable()
                ->rules('required', 'max:255'),

            Number::make('Release Year')
                ->sortable()
                ->rules('required', 'integer', 'min:1900', 'max:2030'),

            Text::make('Director')
                ->sortable()
                ->rules('required', 'max:255'),

            Textarea::make('Genres')
                ->help('Enter genres separated by commas'),

            BelongsTo::make('Default Description', 'defaultDescription', MovieDescription::class)
                ->nullable(),

            HasMany::make('Descriptions', 'descriptions', MovieDescription::class),
        ];
    }
}
```

---

## 🇵🇱 Laravel Sanctum - API Authentication

### 📦 Instalacja Sanctum
```bash
# Instalacja Sanctum
composer require laravel/sanctum

# Publikacja migracji
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Uruchomienie migracji
php artisan migrate
```

### 🔐 Konfiguracja Sanctum
```php
<?php
// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

### 🎫 API Token Controller
```php
<?php
// app/Http/Controllers/ApiTokenController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiTokenController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($request->name);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $request->name
        ]);
    }

    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked']);
    }
}
```

---

## 🇬🇧 Laravel Sanctum - API Authentication

### 📦 Sanctum Installation
```bash
# Install Sanctum
composer require laravel/sanctum

# Publish migrations
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Run migrations
php artisan migrate
```

### 🔐 Sanctum Configuration
```php
<?php
// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

### 🎫 API Token Controller
```php
<?php
// app/Http/Controllers/ApiTokenController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiTokenController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($request->name);

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $request->name
        ]);
    }

    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked']);
    }
}
```

---

## 🇵🇱 Laravel Queue - Asynchroniczne Zadania

### 🎬 GenerateDescriptionJob
```php
<?php
// app/Jobs/GenerateDescriptionJob.php
namespace App\Jobs;

use App\Models\Movie;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Movie $movie,
        public string $context,
        public string $locale
    ) {}

    public function handle(AIService $aiService): void
    {
        try {
            $description = $aiService->generateDescription(
                $this->movie->title,
                $this->context,
                $this->locale
            );

            $movieDescription = $this->movie->descriptions()->create([
                'locale' => $this->locale,
                'text' => $description,
                'context_tag' => $this->context,
                'origin' => 'GENERATED',
                'ai_model' => 'gpt-4',
                'quality_score' => 0.8,
                'plagiarism_score' => 0.1,
                'selected_default' => false
            ]);

            // Set as default if it's the first description
            if ($this->movie->descriptions()->count() === 1) {
                $this->movie->update(['default_description_id' => $movieDescription->id]);
                $movieDescription->update(['selected_default' => true]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to generate description', [
                'movie_id' => $this->movie->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
```

### ⚙️ Konfiguracja Queue
```php
<?php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

---

## 🇬🇧 Laravel Queue - Asynchronous Tasks

### 🎬 GenerateDescriptionJob
```php
<?php
// app/Jobs/GenerateDescriptionJob.php
namespace App\Jobs;

use App\Models\Movie;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDescriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Movie $movie,
        public string $context,
        public string $locale
    ) {}

    public function handle(AIService $aiService): void
    {
        try {
            $description = $aiService->generateDescription(
                $this->movie->title,
                $this->context,
                $this->locale
            );

            $movieDescription = $this->movie->descriptions()->create([
                'locale' => $this->locale,
                'text' => $description,
                'context_tag' => $this->context,
                'origin' => 'GENERATED',
                'ai_model' => 'gpt-4',
                'quality_score' => 0.8,
                'plagiarism_score' => 0.1,
                'selected_default' => false
            ]);

            // Set as default if it's the first description
            if ($this->movie->descriptions()->count() === 1) {
                $this->movie->update(['default_description_id' => $movieDescription->id]);
                $movieDescription->update(['selected_default' => true]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to generate description', [
                'movie_id' => $this->movie->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
```

### ⚙️ Queue Configuration
```php
<?php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],
];
```

---

## 🇵🇱 Laravel Telescope - Debugging

### 📦 Instalacja Telescope
```bash
# Instalacja Telescope
composer require laravel/telescope --dev

# Publikacja migracji
php artisan telescope:install

# Uruchomienie migracji
php artisan migrate
```

### 🔍 Konfiguracja Telescope
```php
<?php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),

    'domain' => env('TELESCOPE_DOMAIN'),

    'path' => env('TELESCOPE_PATH', 'telescope'),

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    'watchers' => [
        Watchers\CacheWatcher::class => env('TELESCOPE_CACHE_WATCHER', true),
        Watchers\CommandWatcher::class => env('TELESCOPE_COMMAND_WATCHER', true),
        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
        Watchers\LogWatcher::class => env('TELESCOPE_LOG_WATCHER', true),
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
        Watchers\ModelWatcher::class => env('TELESCOPE_MODEL_WATCHER', true),
        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        Watchers\QueryWatcher::class => env('TELESCOPE_QUERY_WATCHER', true),
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
        Watchers\RequestWatcher::class => env('TELESCOPE_REQUEST_WATCHER', true),
    ],
];
```

---

## 🇬🇧 Laravel Telescope - Debugging

### 📦 Telescope Installation
```bash
# Install Telescope
composer require laravel/telescope --dev

# Publish migrations
php artisan telescope:install

# Run migrations
php artisan migrate
```

### 🔍 Telescope Configuration
```php
<?php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),

    'domain' => env('TELESCOPE_DOMAIN'),

    'path' => env('TELESCOPE_PATH', 'telescope'),

    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    'watchers' => [
        Watchers\CacheWatcher::class => env('TELESCOPE_CACHE_WATCHER', true),
        Watchers\CommandWatcher::class => env('TELESCOPE_COMMAND_WATCHER', true),
        Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
        Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
        Watchers\LogWatcher::class => env('TELESCOPE_LOG_WATCHER', true),
        Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
        Watchers\ModelWatcher::class => env('TELESCOPE_MODEL_WATCHER', true),
        Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        Watchers\QueryWatcher::class => env('TELESCOPE_QUERY_WATCHER', true),
        Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
        Watchers\RequestWatcher::class => env('TELESCOPE_REQUEST_WATCHER', true),
    ],
];
```

---

## 🇵🇱 Testy

### 🧪 Test MovieController
```php
<?php
// tests/Feature/MovieControllerTest.php
namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_movies(): void
    {
        Movie::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/movies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'release_year',
                        'director',
                        'genres'
                    ]
                ],
                'total',
                'per_page',
                'current_page'
            ]);
    }

    public function test_can_show_movie(): void
    {
        $movie = Movie::factory()->create();

        $response = $this->getJson("/api/v1/movies/{$movie->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $movie->id,
                    'title' => $movie->title
                ]
            ]);
    }

    public function test_can_generate_description(): void
    {
        $movie = Movie::factory()->create();

        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate", [
            'context' => 'modern',
            'locale' => 'en-US'
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Description generation started',
                'movie_id' => $movie->id
            ]);
    }
}
```

---

## 🇬🇧 Testing

### 🧪 MovieController Test
```php
<?php
// tests/Feature/MovieControllerTest.php
namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_movies(): void
    {
        Movie::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/movies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'release_year',
                        'director',
                        'genres'
                    ]
                ],
                'total',
                'per_page',
                'current_page'
            ]);
    }

    public function test_can_show_movie(): void
    {
        $movie = Movie::factory()->create();

        $response = $this->getJson("/api/v1/movies/{$movie->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $movie->id,
                    'title' => $movie->title
                ]
            ]);
    }

    public function test_can_generate_description(): void
    {
        $movie = Movie::factory()->create();

        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate", [
            'context' => 'modern',
            'locale' => 'en-US'
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Description generation started',
                'movie_id' => $movie->id
            ]);
    }
}
```

---

## 🇵🇱 Git Trunk Flow

### 🎯 Strategia Zarządzania Kodem
Używamy **Git Trunk Flow** jako głównej strategii zarządzania kodem dla MovieMind API.

### ✅ Zalety Trunk Flow:
- **Prostszy workflow** - jeden główny branch (main)
- **Szybsze integracje** - częste mergowanie do main
- **Mniej konfliktów** - krótsze żywotność feature branchy
- **Lepsze CI/CD** - każdy commit na main może być deployowany
- **Feature flags** - kontrola funkcji bez branchy
- **Rollback** - łatwy rollback przez feature flags

### 🔄 Workflow:
1. **Feature branch** - `feature/ai-description-generation`
2. **Pull Request** - code review i testy
3. **Merge do main** - po zatwierdzeniu
4. **Deploy** - automatyczny deploy z feature flags
5. **Feature flag** - kontrola włączenia funkcji

### 🛠️ Implementacja:
- **Main branch** - zawsze deployable
- **Feature branchy** - krótkoterminowe (1-3 dni)
- **Feature flags** - kontrola funkcji w runtime
- **CI/CD** - automatyczny deploy na każdy merge

---

## 🇵🇱 Feature Flags

### 🎛️ Strategia Kontroli Funkcji
Używamy **oficjalnej integracji Laravel Feature Flags** (`laravel/feature-flags`) zamiast własnej implementacji.

### ✅ Zalety oficjalnej integracji Laravel:
- **Oficjalne wsparcie** - wspierane przez Laravel team
- **Prostota** - gotowe API i funkcje
- **Bezpieczeństwo** - przetestowane przez społeczność
- **Integracja** - idealna integracja z Laravel
- **Funkcje** - więcej funkcji out-of-the-box
- **Maintenance** - utrzymywane przez zespół Laravel

### 🎛️ Typy Feature Flags:
1. **Boolean flags** - włącz/wyłącz funkcje
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - dla konkretnych użytkowników
4. **Environment flags** - różne ustawienia per środowisko

### 🔧 Implementacja Laravel Feature Flags:
```php
<?php
// Instalacja Laravel Feature Flags
composer require laravel/feature-flags

// Publikacja konfiguracji
php artisan vendor:publish --provider="Laravel\FeatureFlags\FeatureFlagsServiceProvider"
```

### ⚙️ Konfiguracja Feature Flags:
```php
<?php
// config/feature-flags.php
return [
    'default' => env('FEATURE_FLAGS_DEFAULT', false),
    
    'flags' => [
        'ai_description_generation' => true,
        'gpt4_generation' => [
            'enabled' => true,
            'percentage' => 25 // 25% użytkowników
        ],
        'multilingual_support' => [
            'enabled' => true,
            'percentage' => 50 // 50% użytkowników
        ],
        'style_packs' => false // Wyłączone
    ]
];
```

### 🎯 Użycie w MovieMind API:
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use Laravel\FeatureFlags\Facades\FeatureFlags;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        // Sprawdź czy funkcja jest włączona
        if (!FeatureFlags::enabled('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Sprawdź gradual rollout dla nowych modeli
        if (FeatureFlags::enabled('gpt4_generation')) {
            $model = 'gpt-4';
        } else {
            $model = 'gpt-3.5-turbo';
        }

        // Generuj opis z wybranym modelem
        GenerateDescriptionJob::dispatch($movie, $request->input('context'), $model);

        return response()->json(['message' => 'Description generation started']);
    }
}
```

### 🧪 Testy z Feature Flags:
```php
<?php
// tests/Feature/MovieControllerTest.php
use Laravel\FeatureFlags\Facades\FeatureFlags;

class MovieControllerTest extends TestCase
{
    public function test_can_generate_description_when_feature_enabled(): void
    {
        FeatureFlags::enable('ai_description_generation');
        
        $movie = Movie::factory()->create();
        
        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate");
        
        $response->assertStatus(202);
    }
    
    public function test_cannot_generate_description_when_feature_disabled(): void
    {
        FeatureFlags::disable('ai_description_generation');
        
        $movie = Movie::factory()->create();
        
        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate");
        
        $response->assertStatus(403);
    }
}
```

### 🎨 Blade Templates:
```blade
{{-- resources/views/movies/show.blade.php --}}
@if(FeatureFlags::enabled('ai_description_generation'))
    <button onclick="generateDescription()">Generate AI Description</button>
@endif

@if(FeatureFlags::enabled('style_packs'))
    <div class="style-packs">
        <!-- Style packs content -->
    </div>
@endif
```

---

## 🇬🇧 Git Trunk Flow

### 🎯 Code Management Strategy
We use **Git Trunk Flow** as the main code management strategy for MovieMind API.

### ✅ Trunk Flow Advantages:
- **Simpler workflow** - single main branch (main)
- **Faster integrations** - frequent merging to main
- **Fewer conflicts** - shorter feature branch lifetime
- **Better CI/CD** - every commit on main can be deployed
- **Feature flags** - feature control without branches
- **Rollback** - easy rollback through feature flags

### 🔄 Workflow:
1. **Feature branch** - `feature/ai-description-generation`
2. **Pull Request** - code review and tests
3. **Merge to main** - after approval
4. **Deploy** - automatic deploy with feature flags
5. **Feature flag** - feature enablement control

### 🛠️ Implementation:
- **Main branch** - always deployable
- **Feature branches** - short-term (1-3 days)
- **Feature flags** - runtime feature control
- **CI/CD** - automatic deploy on every merge

---

## 🇬🇧 Feature Flags

### 🎛️ Feature Control Strategy
We use **official Laravel Feature Flags integration** (`laravel/feature-flags`) instead of custom implementation.

### ✅ Official Laravel integration advantages:
- **Official support** - supported by Laravel team
- **Simplicity** - ready-made API and functions
- **Security** - tested by community
- **Integration** - perfect Laravel integration
- **Features** - more features out-of-the-box
- **Maintenance** - maintained by Laravel team

### 🎛️ Feature Flag Types:
1. **Boolean flags** - enable/disable features
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - for specific users
4. **Environment flags** - different settings per environment

### 🔧 Laravel Feature Flags Implementation:
```php
<?php
// Install Laravel Feature Flags
composer require laravel/feature-flags

// Publish configuration
php artisan vendor:publish --provider="Laravel\FeatureFlags\FeatureFlagsServiceProvider"
```

### ⚙️ Feature Flags Configuration:
```php
<?php
// config/feature-flags.php
return [
    'default' => env('FEATURE_FLAGS_DEFAULT', false),
    
    'flags' => [
        'ai_description_generation' => true,
        'gpt4_generation' => [
            'enabled' => true,
            'percentage' => 25 // 25% of users
        ],
        'multilingual_support' => [
            'enabled' => true,
            'percentage' => 50 // 50% of users
        ],
        'style_packs' => false // Disabled
    ]
];
```

### 🎯 Usage in MovieMind API:
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use Laravel\FeatureFlags\Facades\FeatureFlags;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        // Check if feature is enabled
        if (!FeatureFlags::enabled('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Check gradual rollout for new models
        if (FeatureFlags::enabled('gpt4_generation')) {
            $model = 'gpt-4';
        } else {
            $model = 'gpt-3.5-turbo';
        }

        // Generate description with selected model
        GenerateDescriptionJob::dispatch($movie, $request->input('context'), $model);

        return response()->json(['message' => 'Description generation started']);
    }
}
```

### 🧪 Testing with Feature Flags:
```php
<?php
// tests/Feature/MovieControllerTest.php
use Laravel\FeatureFlags\Facades\FeatureFlags;

class MovieControllerTest extends TestCase
{
    public function test_can_generate_description_when_feature_enabled(): void
    {
        FeatureFlags::enable('ai_description_generation');
        
        $movie = Movie::factory()->create();
        
        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate");
        
        $response->assertStatus(202);
    }
    
    public function test_cannot_generate_description_when_feature_disabled(): void
    {
        FeatureFlags::disable('ai_description_generation');
        
        $movie = Movie::factory()->create();
        
        $response = $this->postJson("/api/v1/movies/{$movie->id}/generate");
        
        $response->assertStatus(403);
    }
}
```

### 🎨 Blade Templates:
```blade
{{-- resources/views/movies/show.blade.php --}}
@if(FeatureFlags::enabled('ai_description_generation'))
    <button onclick="generateDescription()">Generate AI Description</button>
@endif

@if(FeatureFlags::enabled('style_packs'))
    <div class="style-packs">
        <!-- Style packs content -->
    </div>
@endif
```

---

## 🇵🇱 Deployment

### 🐳 Dockerfile
```dockerfile
# Dockerfile
FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
```

### 🚀 Docker Compose
```yaml
# docker-compose.yml
version: '3.9'
services:
  laravel:
    build: ./src-laravel
    ports:
      - "8001:80"
    environment:
      - APP_ENV=local
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on:
      - db
      - redis
    volumes:
      - ./src-laravel:/var/www
    networks:
      - moviemind

  db:
    image: postgres:15
    environment:
      POSTGRES_DB: moviemind
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - moviemind

  redis:
    image: redis:7
    networks:
      - moviemind

volumes:
  postgres_data:

networks:
  moviemind:
    driver: bridge
```

---

## 🇬🇧 Deployment

### 🐳 Dockerfile
```dockerfile
# Dockerfile
FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www
RUN chmod -R 755 /var/www/storage

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
```

### 🚀 Docker Compose
```yaml
# docker-compose.yml
version: '3.9'
services:
  laravel:
    build: ./src-laravel
    ports:
      - "8001:80"
    environment:
      - APP_ENV=local
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on:
      - db
      - redis
    volumes:
      - ./src-laravel:/var/www
    networks:
      - moviemind

  db:
    image: postgres:15
    environment:
      POSTGRES_DB: moviemind
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - moviemind

  redis:
    image: redis:7
    networks:
      - moviemind

volumes:
  postgres_data:

networks:
  moviemind:
    driver: bridge
```

---

## 🎯 Podsumowanie / Summary

### 🇵🇱
**Laravel Tutorial dla MovieMind API** zawiera wszystkie niezbędne informacje do rozpoczęcia pracy z Laravel 11 w kontekście projektu MovieMind API. Od podstawowej instalacji po zaawansowane funkcje jak Laravel Nova, Sanctum, Queue i Telescope.

### 🇬🇧
**Laravel Tutorial for MovieMind API** contains all necessary information to start working with Laravel 11 in the context of MovieMind API project. From basic installation to advanced features like Laravel Nova, Sanctum, Queue and Telescope.

---

*Dokument utworzony: 2025-01-27*  
*Document created: 2025-01-27*
