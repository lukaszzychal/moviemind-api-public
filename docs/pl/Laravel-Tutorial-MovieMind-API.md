# 🚀 Laravel Tutorial dla MovieMind API
## 🇵🇱 Przewodnik Laravel

---

## 📋 Spis Treści

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

---

## 🇵🇱 Wprowadzenie do Laravel

### 🎯 Co to jest Laravel?
Laravel to nowoczesny framework PHP, który znacząco ułatwia tworzenie aplikacji webowych. Dla MovieMind API używamy Laravel 11.

**Kluczowe cechy Laravel:**
- Elegant Syntax
- Artisan CLI
- Blade Templating
- Service Container
- Eloquent ORM

### 🧩 Dlaczego Laravel dla MovieMind API?

#### Laravel Nova — Gotowy Admin Panel
- Automatyczne CRUD, filtrowanie, relacje, custom actions

#### Eloquent ORM — Zarządzanie Bazą Danych
```php
// Zamiast surowego SQL:
// SELECT * FROM movies WHERE release_year > 2020

// Używamy Eloquent:
Movie::where('release_year', '>', 2020)->get();
```

#### Laravel Sanctum — Autoryzacja API
- API Tokens, SPA Auth, Session Auth

#### Laravel Queue — Asynchroniczne Zadania AI
- Generacja AI, webhooki, maile, przetwarzanie plików

#### Laravel Telescope — Debugging i Monitoring
- Requests, query logging, jobs, exceptions

---

## 📚 Słownik Terminów Laravel

### 🔤 Podstawowe Terminy
| Termin | Opis | Przykład |
| --- | --- | --- |
| Artisan | CLI narzędzie | `php artisan make:controller MovieController` |
| Blade | System szablonów | `@if($movie) {{ $movie->title }} @endif` |
| Eloquent | ORM Laravel | `Movie::find(1)` |
| Middleware | Warstwa pośrednia | Autoryzacja, logowanie |
| Migration | Zmiany w schemacie | Tworzenie/usuwanie tabel |
| Model | Reprezentacja tabeli | `Movie` dla `movies` |
| Route | Mapowanie URL → akcja | `Route::get('/movies', ...)` |
| Service Container | DI w Laravel | Wstrzykiwanie zależności |
| Seeder | Dane przykładowe | `MovieSeeder` |
| Validation | Walidacja danych | `'title' => 'required|string|max:255'` |

### 🏗️ Architektura — Terminy
| Termin | Opis | Użycie w MovieMind API |
|-------|------|------------------------|
| Controller | Logika HTTP | `MovieController` |
| Service | Logika biznesowa | `AIService` |
| Job | Zadania w tle | `GenerateDescriptionJob` |
| Event | Zdarzenie | `DescriptionGenerated` |
| Listener | Reakcja na event | `SendWebhookNotification` |
| Observer | Monitor zmian | `MovieObserver` |
| Policy | Uprawnienia | `MoviePolicy` |
| Resource | Transformacja do API | `MovieResource` |

### 🔧 Narzędzia Deweloperskie
| Narzędzie | Opis | Komenda |
|----------|------|---------|
| Telescope | Debugging | `php artisan telescope:install` |
| Horizon | Kolejki Redis | `php artisan horizon:install` |
| Nova | Admin panel | `composer require laravel/nova` |
| Sanctum | Autoryzacja API | `composer require laravel/sanctum` |
| Scout | Wyszukiwanie | `composer require laravel/scout` |
| Cashier | Płatności Stripe | `composer require laravel/cashier` |

---

## 🇵🇱 Instalacja i Konfiguracja

### 📦 Wymagania Systemowe
#### PHP 8.3+
```bash
php --version
```
#### Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```
#### PostgreSQL
```bash
brew install postgresql
psql --version
```
#### Redis
```bash
brew install redis
redis-cli --version
```

### 🚀 Instalacja Laravel 11
```bash
composer create-project laravel/laravel:^11.0 src-laravel
cd src-laravel
composer install
php artisan key:generate
php artisan about
```

### ⚙️ Konfiguracja .env (fragmenty)
```bash
APP_NAME="MovieMind API"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Europe/Warsaw
APP_URL=http://localhost:8001

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moviemind
DB_USERNAME=moviemind
DB_PASSWORD=moviemind

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_MAX_TOKENS=1000
OPENAI_TEMPERATURE=0.7
```

### 🔧 Pierwsze Kroki po Instalacji
```bash
php artisan migrate
php artisan db:seed
php artisan serve --port=8001
php artisan route:list
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

### 🎯 Kluczowe katalogi dla MovieMind API
- `app/Models/` — modele Eloquent (Movie, Actor, Description)
- `app/Http/Controllers/` — kontrolery API
- `app/Services/` — logika biznesowa (AIService, CacheService)
- `app/Jobs/` — asynchroniczne zadania AI
- `database/migrations/` — struktura bazy danych

---

## 🇵🇱 Modele i Migracje

### 🎬 Model Movie — Szczegółowa Analiza
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movie extends Model
{
    use HasFactory;
    protected $table = 'movies';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    protected $fillable = [
        'title','release_year','director','genres','default_description_id','source_of_truth_locale'
    ];
    protected $guarded = ['id','created_at','updated_at'];
    protected $casts = [
        'genres' => 'array','release_year' => 'integer','created_at' => 'datetime','updated_at' => 'datetime','is_featured' => 'boolean','rating' => 'decimal:2'
    ];
    public function descriptions(): HasMany { return $this->hasMany(MovieDescription::class); }
    public function defaultDescription(): BelongsTo { return $this->belongsTo(MovieDescription::class, 'default_description_id'); }
    public function scopeRecent($query) { return $query->where('release_year', '>=', 2020); }
    public function scopeByYear($query, $year) { return $query->where('release_year', $year); }
    public function getFormattedTitleAttribute(): string { return ucwords(strtolower($this->title)); }
    public function setTitleAttribute($value): void { $this->attributes['title'] = trim($value); }
    public function hasDescription(): bool { return $this->descriptions()->exists(); }
    public function getDescriptionInLocale(string $locale): ?MovieDescription
    { return $this->descriptions()->where('locale', $locale)->first(); }
    protected static function booted(): void
    {
        static::creating(function ($movie) {
            if (empty($movie->source_of_truth_locale)) { $movie->source_of_truth_locale = 'en-US'; }
        });
        static::saved(function ($movie) { \Log::info("Movie saved: {$movie->title}"); });
    }
}
```

### 🎭 Model Actor
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actor extends Model
{
    protected $fillable = ['name','birth_date','birthplace','default_bio_id','source_of_truth_locale'];
    protected $casts = ['birth_date' => 'date'];
    public function bios(): HasMany { return $this->hasMany(ActorBio::class); }
    public function defaultBio() { return $this->belongsTo(ActorBio::class, 'default_bio_id'); }
}
```

### 📝 Model MovieDescription
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieDescription extends Model
{
    protected $fillable = ['movie_id','locale','text','context_tag','origin','ai_model','quality_score','plagiarism_score','selected_default'];
    protected $casts = ['quality_score' => 'float','plagiarism_score' => 'float','selected_default' => 'boolean'];
    public function movie(): BelongsTo { return $this->belongsTo(Movie::class); }
}
```

### 🗄️ Migracje — Przykład
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->smallInteger('release_year')->index();
            $table->string('director')->index();
            $table->json('genres')->nullable();
            $table->foreignId('default_description_id')->nullable()->constrained('movie_descriptions')->onDelete('set null');
            $table->string('source_of_truth_locale', 10)->default('en-US')->index();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['draft','published','archived'])->default('draft')->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('movies'); }
};
```

---

## 🇵🇱 Kontrolery i Routing

### 🎬 MovieController
```php
<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Movie::query();
        if ($request->has('q')) { $query->where('title', 'like', '%'.$request->q.'%'); }
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
        return response()->json(['data' => $movie]);
    }

    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        $context = $request->input('context', 'modern');
        $locale = $request->input('locale', 'en-US');
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

### 📦 Instalacja
```bash
composer require laravel/nova
php artisan nova:install
```

### 🎬 Nova Resource — Movie
```php
<?php
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
    public static array $search = ['title','director'];
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Title')->sortable()->rules('required','max:255'),
            Number::make('Release Year')->sortable()->rules('required','integer','min:1900','max:2030'),
            Text::make('Director')->sortable()->rules('required','max:255'),
            Textarea::make('Genres')->help('Enter genres separated by commas'),
            BelongsTo::make('Default Description', 'defaultDescription', MovieDescription::class)->nullable(),
            HasMany::make('Descriptions', 'descriptions', MovieDescription::class),
        ];
    }
}
```

---

## 🇵🇱 Laravel Sanctum - API Authentication

### 📦 Instalacja
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 🔐 Konfiguracja (fragment)
```php
<?php
return [
  'guard' => ['web'],
  'expiration' => null,
];
```

### 🎫 API Token Controller
```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiTokenController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:255']);
        $token = $request->user()->createToken($request->name);
        return response()->json(['token' => $token->plainTextToken,'name' => $request->name]);
    }

    public function revokeToken(Request $request): JsonResponse
    { $request->user()->currentAccessToken()->delete(); return response()->json(['message' => 'Token revoked']); }
}
```

---

## 🇵🇱 Laravel Queue - Asynchroniczne Zadania

### 🎬 GenerateDescriptionJob
```php
<?php
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
    public function __construct(public Movie $movie, public string $context, public string $locale) {}
    public function handle(AIService $aiService): void
    {
        $description = $aiService->generateDescription($this->movie->title,$this->context,$this->locale);
        $movieDescription = $this->movie->descriptions()->create([
            'locale'=>$this->locale,'text'=>$description,'context_tag'=>$this->context,'origin'=>'GENERATED','ai_model'=>'gpt-4','quality_score'=>0.8,'plagiarism_score'=>0.1,'selected_default'=>false
        ]);
        if ($this->movie->descriptions()->count() === 1) {
            $this->movie->update(['default_description_id'=>$movieDescription->id]);
            $movieDescription->update(['selected_default'=>true]);
        }
    }
}
```

### ⚙️ Konfiguracja Queue (fragment)
```php
<?php
return [
  'default' => env('QUEUE_CONNECTION','redis'),
  'connections' => [
    'redis' => ['driver'=>'redis','connection'=>'default','queue'=>env('REDIS_QUEUE','default'),'retry_after'=>90],
  ],
];
```

---

## 🇵🇱 Laravel Telescope - Debugging

### 📦 Instalacja
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### 🔍 Konfiguracja (fragment)
```php
<?php
return [ 'enabled' => env('TELESCOPE_ENABLED', true) ];
```

---

## 🇵🇱 Testy

### 🧪 Test MovieController
```php
<?php
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
        $response->assertStatus(200)->assertJsonStructure([
            'data'=>[['id','title','release_year','director','genres']],
            'total','per_page','current_page'
        ]);
    }
}
```

---

## 🇵🇱 Git Trunk Flow

### 🎯 Strategia Zarządzania Kodem
Używamy Git Trunk Flow jako głównej strategii zarządzania kodem.

### ✅ Zalety
- Prostszy workflow, szybsze integracje, mniej konfliktów, lepsze CI/CD

---

## 🇵🇱 Feature Flags

### 🎛️ Strategia i konfiguracja
```php
<?php
return [
  'flags' => [
    'ai_description_generation' => true,
    'gpt4_generation' => ['enabled'=>true,'percentage'=>25],
    'multilingual_support' => ['enabled'=>true,'percentage'=>50],
    'style_packs' => false,
  ]
];
```

### Użycie w kontrolerze (fragment)
```php
if (!FeatureFlags::enabled('ai_description_generation')) {
  return response()->json(['error'=>'Feature not available'],403);
}
```

---

## 🇵🇱 Deployment

### 🐳 Dockerfile (fragment)
```dockerfile
FROM php:8.3-fpm
RUN apt-get update && apt-get install -y git curl zip unzip postgresql-client \
    libpng-dev libonig-dev libxml2-dev
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd
WORKDIR /var/www
COPY . .
RUN composer install --no-dev --optimize-autoloader
CMD ["php-fpm"]
```

### 🚀 docker-compose.yml (fragment)
```yaml
version: '3.9'
services:
  laravel:
    build: ./src-laravel
    ports: ["8001:80"]
    environment:
      - APP_ENV=local
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on: [db, redis]
    volumes: ["./src-laravel:/var/www"]
  db:
    image: postgres:15
    environment:
      POSTGRES_DB: moviemind
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
  redis:
    image: redis:7
```

---

## 🎯 Podsumowanie

**Laravel Tutorial dla MovieMind API** zawiera potrzebne informacje do pracy z Laravel 11: instalacja, konfiguracja, modele, migracje, kontrolery, kolejki, debugowanie, feature flags oraz deployment.

---

*Dokument utworzony: 2025-01-27*


