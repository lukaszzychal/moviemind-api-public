# 🚀 Laravel Tutorial for MovieMind API
## 🇬🇧 Laravel Guide

---

## 📋 Table of Contents

1. Laravel Introduction
2. Installation and Configuration
3. Project Structure
4. Models and Migrations
5. Controllers and Routing
6. Laravel Nova - Admin Panel
7. Laravel Sanctum - API Authentication
8. Laravel Queue - Asynchronous Tasks
9. Laravel Telescope - Debugging
10. Testing
11. Git Trunk Flow
12. Feature Flags
13. Deployment

---

## Laravel Introduction

Laravel is a PHP framework that makes web application development easier. For MovieMind API we use Laravel 11.

Key features:
- Elegant syntax
- Artisan CLI
- Blade templating
- Service container (dependency injection)
- Eloquent ORM

Why Laravel for MovieMind API:
- Laravel Nova — ready admin panel
- Eloquent ORM — easy database management
- Laravel Sanctum — API authentication
- Laravel Queue — async AI tasks
- Laravel Telescope — debugging and monitoring

---

## Installation and Configuration

### Requirements
- PHP 8.3+
- Composer
- PostgreSQL
- Redis

### Install Laravel 11
```bash
composer create-project laravel/laravel:^11.0 src-laravel
cd src-laravel
composer install
php artisan key:generate
php artisan about
```

### .env Configuration (snippets)
```bash
APP_NAME="MovieMind API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8001

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=moviemind
DB_USERNAME=moviemind
DB_PASSWORD=moviemind

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

### First Steps
```bash
php artisan migrate
php artisan db:seed
php artisan serve --port=8001
php artisan route:list
```

---

## Project Structure

### Laravel Directory Structure
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

Key directories:
- app/Models — Eloquent models (Movie, Actor, Description)
- app/Http/Controllers — API controllers
- app/Services — business logic (AIService, CacheService)
- app/Jobs — asynchronous AI tasks
- database/migrations — database structure

---

## Models and Migrations

### Movie Model (excerpt)
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    protected $fillable = [
        'title', 'release_year', 'director', 'genres', 'default_description_id', 'source_of_truth_locale'
    ];

    protected $casts = [
        'genres' => 'array',
        'release_year' => 'integer'
    ];

    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }
}
```

### Migration (excerpt)
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
            $table->string('title');
            $table->smallInteger('release_year');
            $table->string('director');
            $table->json('genres');
            $table->foreignId('default_description_id')->nullable();
            $table->string('source_of_truth_locale', 10)->default('en-US');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('movies'); }
};
```

---

## Controllers and Routing

### MovieController (excerpt)
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
}
```

### Routes (excerpt)
```php
<?php
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

## Laravel Nova - Admin Panel

### Installation
```bash
composer require laravel/nova
php artisan nova:install
```

---

## Laravel Sanctum - API Authentication

### Installation
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

---

## Laravel Queue - Asynchronous Tasks

### GenerateDescriptionJob (excerpt)
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
        $this->movie->descriptions()->create([
            'locale'=>$this->locale,'text'=>$description,'context_tag'=>$this->context,'origin'=>'GENERATED','ai_model'=>'gpt-4'
        ]);
    }
}
```

---

## Laravel Telescope - Debugging

### Installation
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

---

## Testing (excerpt)
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

## Git Trunk Flow

Simpler workflow, faster integrations, fewer conflicts, better CI/CD, feature flags, rollback.

---

## Feature Flags

Use `laravel/feature-flags`. Example:
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

---

## Deployment

### Dockerfile (excerpt)
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

### docker-compose.yml (excerpt)
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

## Summary

Laravel Tutorial for MovieMind API provides practical guidance for Laravel 11 in the project context: installation, configuration, models, migrations, controllers, queues, debugging, feature flags, and deployment.

---

Document created: 2025-01-27


