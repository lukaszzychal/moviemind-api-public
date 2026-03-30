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
Laravel to framework PHP, który ułatwia tworzenie aplikacji webowych. Dla MovieMind API używamy Laravel 11 - najnowszej wersji.

### 🧩 Dlaczego Laravel dla MovieMind API?
- **Laravel Nova** - gotowy admin panel
- **Eloquent ORM** - łatwe zarządzanie bazą danych
- **Laravel Sanctum** - autoryzacja API
- **Laravel Queue** - asynchroniczne zadania AI
- **Laravel Telescope** - debugging i monitoring

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

### 📦 Wymagania
- PHP 8.3+
- Composer
- PostgreSQL
- Redis

### 🚀 Instalacja Laravel
```bash
# Instalacja Laravel 11
composer create-project laravel/laravel:^11.0 src-laravel

# Przejście do katalogu
cd src-laravel

# Instalacja zależności
composer install
```

### ⚙️ Konfiguracja .env
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

### 🎬 Model Movie
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

### 🗄️ Migracje
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
use App\Http\Controllers\PersonController;

Route::prefix('v1')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/{movie}', [MovieController::class, 'show']);
    Route::post('/movies/{movie}/generate', [MovieController::class, 'generateDescription']);
    
    Route::get('/people/{person:slug}', [PersonController::class, 'show']);
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
use App\Http\Controllers\PersonController;

Route::prefix('v1')->group(function () {
    Route::get('/movies', [MovieController::class, 'index']);
    Route::get('/movies/{movie}', [MovieController::class, 'show']);
    Route::post('/movies/{movie}/generate', [MovieController::class, 'generateDescription']);
    
Route::get('/people/{person:slug}', [PersonController::class, 'show']);
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
Używamy **własnej implementacji Feature Flags** zamiast gotowych rozwiązań.

### ✅ Zalety własnej implementacji:
- **Kontrola** - pełna kontrola nad logiką
- **Koszt** - brak kosztów zewnętrznych serwisów
- **Prostota** - dostosowana do potrzeb projektu
- **Integracja** - łatwa integracja z Laravel
- **Bezpieczeństwo** - dane nie opuszczają naszej infrastruktury

### 🎛️ Typy Feature Flags:
1. **Boolean flags** - włącz/wyłącz funkcje
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - dla konkretnych użytkowników
4. **Environment flags** - różne ustawienia per środowisko

### 🔧 Implementacja Laravel:
```php
<?php
// app/Services/FeatureFlagService.php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    public function isEnabled(string $flag, ?User $user = null): bool
    {
        $config = $this->getFlagConfig($flag);
        
        if ($config['enabled'] === false) {
            return false;
        }
        
        if ($config['percentage'] < 100) {
            return $this->shouldEnableForPercentage($flag, $user);
        }
        
        return true;
    }

    private function getFlagConfig(string $flag): array
    {
        return Cache::remember("feature_flag_{$flag}", 300, function () use ($flag) {
            return config("feature-flags.{$flag}", [
                'enabled' => false,
                'percentage' => 0
            ]);
        });
    }

    private function shouldEnableForPercentage(string $flag, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $hash = hash('sha256', $flag . $user->id);
        $hashValue = hexdec(substr($hash, 0, 8));
        $percentage = $this->getFlagConfig($flag)['percentage'];
        
        return ($hashValue % 100) < $percentage;
    }
}
```

### 🎯 Użycie w MovieMind API:
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(
        private FeatureFlagService $featureFlags
    ) {}

    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        // Sprawdź czy funkcja jest włączona
        if (!$this->featureFlags->isEnabled('ai_description_generation', $request->user())) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Sprawdź gradual rollout dla nowych modeli
        if ($this->featureFlags->isEnabled('gpt4_generation', $request->user())) {
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

### ⚙️ Konfiguracja Feature Flags:
```php
<?php
// config/feature-flags.php
return [
    'ai_description_generation' => [
        'enabled' => true,
        'percentage' => 100
    ],
    
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25 // 25% użytkowników
    ],
    
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50 // 50% użytkowników
    ],
    
    'style_packs' => [
        'enabled' => false, // Wyłączone
        'percentage' => 0
    ]
];
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
We use **custom Feature Flags implementation** instead of ready-made solutions.

### ✅ Custom implementation advantages:
- **Control** - full control over logic
- **Cost** - no external service costs
- **Simplicity** - tailored to project needs
- **Integration** - easy Laravel integration
- **Security** - data doesn't leave our infrastructure

### 🎛️ Feature Flag Types:
1. **Boolean flags** - enable/disable features
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - for specific users
4. **Environment flags** - different settings per environment

### 🔧 Laravel Implementation:
```php
<?php
// app/Services/FeatureFlagService.php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    public function isEnabled(string $flag, ?User $user = null): bool
    {
        $config = $this->getFlagConfig($flag);
        
        if ($config['enabled'] === false) {
            return false;
        }
        
        if ($config['percentage'] < 100) {
            return $this->shouldEnableForPercentage($flag, $user);
        }
        
        return true;
    }

    private function getFlagConfig(string $flag): array
    {
        return Cache::remember("feature_flag_{$flag}", 300, function () use ($flag) {
            return config("feature-flags.{$flag}", [
                'enabled' => false,
                'percentage' => 0
            ]);
        });
    }

    private function shouldEnableForPercentage(string $flag, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $hash = hash('sha256', $flag . $user->id);
        $hashValue = hexdec(substr($hash, 0, 8));
        $percentage = $this->getFlagConfig($flag)['percentage'];
        
        return ($hashValue % 100) < $percentage;
    }
}
```

### 🎯 Usage in MovieMind API:
```php
<?php
// app/Http/Controllers/MovieController.php
namespace App\Http\Controllers;

use App\Services\FeatureFlagService;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(
        private FeatureFlagService $featureFlags
    ) {}

    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        // Check if feature is enabled
        if (!$this->featureFlags->isEnabled('ai_description_generation', $request->user())) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Check gradual rollout for new models
        if ($this->featureFlags->isEnabled('gpt4_generation', $request->user())) {
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

### ⚙️ Feature Flags Configuration:
```php
<?php
// config/feature-flags.php
return [
    'ai_description_generation' => [
        'enabled' => true,
        'percentage' => 100
    ],
    
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25 // 25% of users
    ],
    
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50 // 50% of users
    ],
    
    'style_packs' => [
        'enabled' => false, // Disabled
        'percentage' => 0
    ]
];
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
# compose.yml
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
# compose.yml
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
