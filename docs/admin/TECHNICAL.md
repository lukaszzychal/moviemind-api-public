# Admin Panel - Dokumentacja Techniczna

## 🏗️ Architektura

### Stack Technologiczny
```
Laravel 11.x
├── Filament 3.2 (Admin Panel Framework)
│   ├── Livewire 3.x (Reactive Components)
│   ├── Alpine.js 3.x (Frontend Interactivity)
│   └── Tailwind CSS 3.x (Styling)
├── Laravel Pennant (Feature Flags)
├── Laravel Horizon (Queue Monitoring)
└── PHPUnit (Testing)
```

### Struktura Katalogów
```
api/
├── app/
│   ├── Filament/
│   │   ├── Pages/
│   │   │   └── FeatureFlags.php          # Custom page: Feature flags
│   │   ├── Resources/
│   │   │   ├── MovieResource.php         # CRUD: Movies
│   │   │   │   └── Pages/
│   │   │   │       ├── CreateMovie.php
│   │   │   │       ├── EditMovie.php
│   │   │   │       ├── ListMovies.php
│   │   │   │       └── ViewMovie.php
│   │   │   └── PersonResource.php        # CRUD: People
│   │   │       └── Pages/
│   │   │           ├── CreatePerson.php
│   │   │           ├── EditPerson.php
│   │   │           ├── ListPeople.php
│   │   │           └── ViewPerson.php
│   │   └── Widgets/
│   │       └── StatsOverview.php         # Dashboard widget
│   ├── Providers/
│   │   └── Filament/
│   │       └── AdminPanelProvider.php    # Panel configuration
│   └── Models/
│       └── User.php                      # FilamentUser implementation
├── resources/
│   └── views/
│       └── filament/
│           └── pages/
│               └── feature-flags.blade.php  # Feature flags view
├── public/
│   ├── css/filament/                     # Compiled CSS
│   └── js/filament/                      # Compiled JS
└── config/
    └── pennant.php                       # Feature flags config
```

---

## 🔧 Konfiguracja

### AdminPanelProvider
**Lokalizacja:** `api/app/Providers/Filament/AdminPanelProvider.php`

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->login()
        ->colors([
            'primary' => Color::Blue,
        ])
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
        ->widgets([
            Widgets\StatsOverview::class,
        ])
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ])
        ->authMiddleware([
            Authenticate::class,
        ])
        ->brandName('MovieMind Admin')
        ->darkMode(true);
}
```

**Kluczowe Parametry:**
- `id('admin')` - Identyfikator panelu
- `path('admin')` - URL path: `/admin`
- `login()` - Włącza stronę logowania
- `colors(['primary' => Color::Blue])` - Kolor główny: niebieski
- `darkMode(true)` - Włącza dark mode

---

## 📦 Resources (CRUD)

### MovieResource
**Lokalizacja:** `api/app/Filament/Resources/MovieResource.php`

#### Form Schema
```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('title')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(fn ($state, callable $set) => 
                $set('slug', Str::slug($state))
            ),
        
        Forms\Components\TextInput::make('slug')
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true),
        
        Forms\Components\TextInput::make('release_year')
            ->numeric()
            ->minValue(1800)
            ->maxValue(2100),
        
        Forms\Components\TextInput::make('director')
            ->maxLength(255),
        
        Forms\Components\TagsInput::make('genres')
            ->separator(','),
        
        Forms\Components\TextInput::make('tmdb_id')
            ->numeric()
            ->label('TMDb ID'),
    ]);
}
```

**Funkcje:**
- **Auto-slug:** `live(onBlur: true)` + `afterStateUpdated`
- **Walidacja:** `required()`, `maxLength()`, `unique()`
- **Numeric constraints:** `minValue()`, `maxValue()`
- **TagsInput:** Separator: `,`

#### Table Schema
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('title')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('slug')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('release_year')
                ->sortable(),
            Tables\Columns\TextColumn::make('director')
                ->searchable(),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\Filter::make('release_year')
                ->form([
                    Forms\Components\TextInput::make('from')
                        ->numeric(),
                    Forms\Components\TextInput::make('to')
                        ->numeric(),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'], fn ($q, $year) => 
                            $q->where('release_year', '>=', $year)
                        )
                        ->when($data['to'], fn ($q, $year) => 
                            $q->where('release_year', '<=', $year)
                        );
                }),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
}
```

**Funkcje:**
- **Search:** `title`, `director`, `slug`
- **Sort:** `title`, `release_year`, `created_at`
- **Filters:** Release year range (from-to)
- **Toggleable columns:** `slug`, `created_at`
- **Actions:** View, Edit, Delete

---

### PersonResource
**Lokalizacja:** `api/app/Filament/Resources/PersonResource.php`

#### Form Schema
```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(fn ($state, callable $set) => 
                $set('slug', Str::slug($state))
            ),
        
        Forms\Components\TextInput::make('slug')
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true),
        
        Forms\Components\DatePicker::make('birth_date')
            ->maxDate(now()),
        
        Forms\Components\TextInput::make('birthplace')
            ->maxLength(255),
        
        Forms\Components\TextInput::make('tmdb_id')
            ->numeric()
            ->label('TMDb ID'),
    ]);
}
```

**Funkcje:**
- **Auto-slug:** Analogicznie do MovieResource
- **DatePicker:** `maxDate(now())` - nie można wybrać przyszłej daty
- **Walidacja:** `required()`, `maxLength()`, `unique()`

#### Table Schema
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable(),
            Tables\Columns\TextColumn::make('slug')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('birth_date')
                ->date()
                ->sortable(),
            Tables\Columns\TextColumn::make('birthplace')
                ->searchable(),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Tables\Filters\Filter::make('birth_year')
                ->form([
                    Forms\Components\TextInput::make('from')
                        ->numeric(),
                    Forms\Components\TextInput::make('to')
                        ->numeric(),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'], fn ($q, $year) => 
                            $q->whereYear('birth_date', '>=', $year)
                        )
                        ->when($data['to'], fn ($q, $year) => 
                            $q->whereYear('birth_date', '<=', $year)
                        );
                }),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
}
```

**Funkcje:**
- **Search:** `name`, `birthplace`, `slug`
- **Sort:** `name`, `birth_date`, `created_at`
- **Filters:** Birth year range (from-to)
- **Toggleable columns:** `slug`, `created_at`

---

## 📄 Custom Pages

### FeatureFlags Page
**Lokalizacja:** `api/app/Filament/Pages/FeatureFlags.php`

```php
class FeatureFlags extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static string $view = 'filament.pages.feature-flags';
    protected static ?string $navigationLabel = 'Feature Flags';
    protected static ?string $title = 'Feature Flags Management';

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        $config = config('pennant.default');
        $flags = [];

        foreach ($config as $category => $categoryFlags) {
            $flags[] = Forms\Components\Section::make(
                Str::title(str_replace('_', ' ', $category))
            )->schema(
                collect($categoryFlags)->map(function ($value, $key) {
                    return Forms\Components\Toggle::make($key)
                        ->label(Str::title(str_replace('_', ' ', $key)))
                        ->default($value)
                        ->disabled(!is_bool($value));
                })->toArray()
            );
        }

        return $flags;
    }

    public function save(): void
    {
        Notification::make()
            ->title('Feature flags updated')
            ->success()
            ->body('Note: Changes are in-memory only. To persist, update config/pennant.php')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save Changes')
                ->action('save')
                ->color('primary'),
        ];
    }
}
```

**Funkcje:**
- **Dynamic form:** Generuje formularz z `config/pennant.php`
- **Categorization:** Grupuje flagi po kategoriach
- **Toggle controls:** Tylko dla flag boolean
- **In-memory only:** Zmiany nie są persystowane

**Blade View:** `resources/views/filament/pages/feature-flags.blade.php`
```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-6">
            {{ $this->getFormActions() }}
        </div>
    </form>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Important Note
        </x-slot>
        
        <div class="text-sm text-gray-600 dark:text-gray-400">
            <p>Changes made here are <strong>in-memory only</strong> and will not persist.</p>
            <p class="mt-2">To permanently enable/disable a feature flag, update the configuration in:</p>
            <code class="block mt-2 p-2 bg-gray-100 dark:bg-gray-800 rounded">
                config/pennant.php
            </code>
        </div>
    </x-filament::section>
</x-filament-panels::page>
```

---

## 📊 Widgets

### StatsOverview Widget
**Lokalizacja:** `api/app/Filament/Widgets/StatsOverview.php`

```php
class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Movies', Movie::count())
                ->description('Movies in database')
                ->descriptionIcon('heroicon-m-film')
                ->color('success'),

            Stat::make('Total People', Person::count())
                ->description('People in database')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('Pending Jobs', DB::table('jobs')->count())
                ->description('Jobs in queue')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Failed Jobs', DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count())
                ->description('Last 24 hours')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
```

**Funkcje:**
- **Polling:** Auto-refresh co 30s
- **Real-time stats:** Liczniki z bazy danych
- **Icons:** Heroicons
- **Colors:** success, info, warning, danger

---

## 🔐 Autentykacja

### User Model
**Lokalizacja:** `api/app/Models/User.php`

```php
class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // TODO: Add role-based access control
    }
}
```

**Implementacja:**
- `FilamentUser` interface
- `canAccessPanel()` - kontrola dostępu do panelu
- **TODO:** RBAC (Role-Based Access Control)

### Basic Auth
**Lokalizacja:** `api/routes/web.php` (TASK-050)

```php
Route::middleware(['auth.basic'])->group(function () {
    Route::get('/admin', function () {
        return redirect('/admin/login');
    });
});
```

**Warstwa ochrony:**
1. **Basic Auth** (serwer) → credentials z `.env`
2. **Filament Login** (aplikacja) → credentials z `users` table

---

## 🗄️ Baza Danych

### Tabele Wykorzystywane

#### `movies`
```sql
CREATE TABLE movies (
    id BIGINT UNSIGNED PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    release_year INT,
    director VARCHAR(255),
    genres TEXT,
    tmdb_id INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `people`
```sql
CREATE TABLE people (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    birth_date DATE,
    birthplace VARCHAR(255),
    tmdb_id INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `users`
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `jobs` (Laravel Queue)
```sql
CREATE TABLE jobs (
    id BIGINT UNSIGNED PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL
);
```

#### `failed_jobs` (Laravel Queue)
```sql
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🎨 Frontend Assets

### CSS
**Lokalizacja:** `api/public/css/filament/`

```
filament/
├── app.css           # Main Filament styles
├── forms/forms.css   # Form components
└── support/support.css  # Support utilities
```

**Build Command:**
```bash
php artisan filament:assets
```

### JavaScript
**Lokalizacja:** `api/public/js/filament/`

```
filament/
├── app.js                    # Main Filament JS
├── echo.js                   # Laravel Echo (WebSockets)
├── forms/components/
│   ├── color-picker.js
│   ├── date-time-picker.js
│   ├── file-upload.js
│   ├── key-value.js
│   ├── markdown-editor.js
│   ├── rich-editor.js
│   ├── select.js
│   ├── tags-input.js
│   └── textarea.js
├── notifications/notifications.js
├── support/support.js
├── tables/components/table.js
└── widgets/components/
    ├── chart.js
    └── stats-overview/stat/chart.js
```

---

## 🧪 Testing

### Test Coverage
```
Feature Tests: 501 passed
Unit Tests: 432 passed
Total: 933 tests, 3827 assertions
```

**Status:** ✅ All tests passing

### Brak Dedykowanych Testów dla Admin Panel
**Powód:** Filament jest frameworkiem z wbudowanymi testami

**Rekomendacja:** Dodać testy E2E (Dusk) w przyszłości:
```php
// tests/Browser/AdminPanelTest.php
public function test_admin_can_create_movie()
{
    $this->browse(function (Browser $browser) {
        $browser->loginAs(User::find(1))
                ->visit('/admin/movies/create')
                ->type('title', 'Test Movie')
                ->press('Save')
                ->assertPathIs('/admin/movies');
    });
}
```

---

## 🚀 Deployment

### Build Assets
```bash
cd api
php artisan filament:assets
```

### Clear Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Environment Variables
```env
# .env
APP_URL=https://moviemind.local
ADMIN_BASIC_AUTH_USER=admin
ADMIN_BASIC_AUTH_PASSWORD=secret
```

---

## 🔍 Debugging

### Enable Debug Mode
```env
APP_DEBUG=true
FILAMENT_DEBUG=true
```

### Logs
```bash
tail -f storage/logs/laravel.log
```

### Horizon (Queue Monitoring)
```bash
php artisan horizon
# Access: http://localhost/horizon
```

### Telescope (Debugging)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
# Access: http://localhost/telescope
```

---

## 📚 API Reference

### Filament Resources
- [Filament Docs](https://filamentphp.com/docs)
- [Livewire Docs](https://livewire.laravel.com/docs)
- [Alpine.js Docs](https://alpinejs.dev/start-here)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)

### Laravel
- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Laravel Pennant](https://laravel.com/docs/11.x/pennant)
- [Laravel Horizon](https://laravel.com/docs/11.x/horizon)

---

## 🐛 Known Issues

### 1. Feature Flags Not Persisting
**Issue:** Changes in Feature Flags page are not saved  
**Reason:** By design - in-memory only  
**Solution:** Update `config/pennant.php` manually

### 2. Slug Duplication
**Issue:** Slug może się zduplikować przy równoczesnej edycji  
**Solution:** Unique constraint w bazie + walidacja w formularzu

### 3. Performance z Dużą Ilością Rekordów
**Issue:** Tabele mogą być wolne przy >10k rekordów  
**Solution:** 
- Dodać pagination (default: 25/page)
- Dodać indexy w bazie danych
- Użyć `simplePaginate()` zamiast `paginate()`

---

## 🔄 Maintenance

### Regular Tasks
- **Daily:** Sprawdzenie failed jobs
- **Weekly:** Przegląd activity log (TODO)
- **Monthly:** Backup bazy danych

### Monitoring
- **Uptime:** Pingdom, UptimeRobot
- **Errors:** Sentry, Bugsnag
- **Performance:** New Relic, Blackfire

---

**Utworzono:** 2025-01-08  
**Wersja:** 1.0  
**Autor:** AI-Assisted Development  
**Następna Aktualizacja:** Q2 2025
