# AI Service Configuration – Mock vs Real

## 🎯 Overview
The application supports switching between **MockAiService** (for development/testing) and **RealAiService** (for staging/production) via configuration.

---

## 📊 Architecture
### MockAiService
- Uses the legacy `Bus::dispatch(closure)` flow.
- Simulates AI generation (sleep + mock payloads).
- Recommended for local development and automated tests.
- No external AI API required.

### RealAiService
- Uses the new **Events + Jobs** architecture.
- Dispatches `MovieGenerationRequested` / `PersonGenerationRequested` events.
- Listeners queue `GenerateMovieJob` / `GeneratePersonJob`.
- Designed for real integrations (replace job handlers with actual AI provider calls).

---

## ⚙️ Configuration
### 1. Environment variable
Add to `.env`:

```env
# AI Service configuration
# Options: 'mock' or 'real'
AI_SERVICE=mock
```

**Values:**
- `mock` – use MockAiService (default for dev/test).
- `real` – use RealAiService (production/staging).

### 2. Service provider binding
`app/Providers/AppServiceProvider.php`:

```php
use App\Services\AiServiceSelector;
use App\Services\MockAiService;
use App\Services\RealAiService;

public function register(): void
{
    $this->app->singleton(MockAiService::class, fn () => new MockAiService());
    $this->app->singleton(RealAiService::class, fn () => new RealAiService());

    $this->app->bind(AiServiceSelector::class, function () {
        return new AiServiceSelector(
            mock: app(MockAiService::class),
            real: app(RealAiService::class),
            driver: config('services.ai.service')
        );
    });
}
```

### 3. Service selector helper
`app/Services/AiServiceSelector.php`:

```php
class AiServiceSelector
{
    public function __construct(
        private readonly MockAiService $mock,
        private readonly RealAiService $real,
        private readonly string $driver = 'mock',
    ) {}

    public function get(): MockAiService|RealAiService
    {
        return match ($this->driver) {
            'real' => $this->real,
            default => $this->mock,
        };
    }
}
```

### 4. Usage in controllers/actions
```php
$aiService = app(AiServiceSelector::class)->get();
$aiService->queueMovieGeneration($slug, $jobId);
```

---

## 🔄 Runtime switching
- Update `AI_SERVICE` in `.env` and clear config cache (`php artisan config:clear`).
- For Docker deployments ensure the environment variable is passed into the container.

---

## ✅ Testing tips
- Keep `AI_SERVICE=mock` for local/dev pipelines.
- Smoke test production/staging with `AI_SERVICE=real` and mocked API credentials until real integration is ready.
- Unit-test both services separately: Mock service with deterministic payloads, Real service with fake HTTP client / recorded fixtures.

---

**Polish source:** [`../pl/AI_SERVICE_CONFIGURATION.md`](../pl/AI_SERVICE_CONFIGURATION.md)
