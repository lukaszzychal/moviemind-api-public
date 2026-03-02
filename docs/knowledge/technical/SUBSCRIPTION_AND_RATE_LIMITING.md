# Subscription and Rate Limiting System

> **Last Updated:** 2025-01-27  
> **Related:** TASK-RAPI-002, TASK-RAPI-003

---

## 📍 Gdzie są Sprawdzane Subskrypcje i Limity?

### 1. **PlanBasedRateLimit Middleware** 
**Lokalizacja:** `api/app/Http/Middleware/PlanBasedRateLimit.php`

**Co sprawdza:**
- ✅ **Miesięczny limit** - czy użytkownik przekroczył limit zapytań w miesiącu
- ✅ **Rate limit per-minute** - czy użytkownik przekroczył limit zapytań na minutę
- ✅ **Tracking użycia** - zapisuje każde zapytanie do bazy danych

**Jak działa:**
```php
// Sprawdza miesięczny limit
if ($this->usageTracker->hasExceededMonthlyLimit($apiKey, $plan)) {
    return 429 Too Many Requests;
}

// Sprawdza rate limit per-minute
if ($this->usageTracker->hasExceededRateLimit($apiKey, $plan)) {
    return 429 Too Many Requests;
}
```

**Gdzie jest używany:**
- Zarejestrowany jako alias: `plan.rate.limit`
- Może być dodany do routes lub globalnie

---

### 2. **PlanService**
**Lokalizacja:** `api/app/Services/PlanService.php`

**Co sprawdza:**
- ✅ **Funkcje planu** - czy plan ma dostęp do konkretnej funkcji (`canUseFeature()`)
- ✅ **Limity planu** - pobiera miesięczny limit i rate limit

**Metody:**
```php
// Sprawdza czy plan ma funkcję
$planService->canUseFeature($plan, 'generate'); // true/false

// Pobiera limit miesięczny
$planService->getMonthlyLimit($plan); // 100, 10000, 0 (unlimited)

// Pobiera rate limit per-minute
$planService->getRateLimit($plan); // 10, 100, 1000
```

---

### 3. **UsageTracker Service**
**Lokalizacja:** `api/app/Services/UsageTracker.php`

**Co sprawdza:**
- ✅ **Czy przekroczono miesięczny limit** (`hasExceededMonthlyLimit()`)
- ✅ **Czy przekroczono rate limit** (`hasExceededRateLimit()`)
- ✅ **Ile zostało zapytań** (`getRemainingQuota()`)

**Metody:**
```php
// Sprawdza miesięczny limit
$usageTracker->hasExceededMonthlyLimit($apiKey, $plan);

// Sprawdza rate limit per-minute
$usageTracker->hasExceededRateLimit($apiKey, $plan);

// Pobiera pozostałe zapytania
$usageTracker->getRemainingQuota($apiKey, $plan);
```

---

### 4. **SubscriptionPlan Model**
**Lokalizacja:** `api/app/Models/SubscriptionPlan.php`

**Co sprawdza:**
- ✅ **Czy plan ma funkcję** (`hasFeature()`)
- ✅ **Czy plan jest unlimited** (`isUnlimited()`)

**Metody:**
```php
// Sprawdza funkcję
$plan->hasFeature('generate'); // true/false
$plan->hasFeature('webhooks'); // true/false
$plan->hasFeature('analytics'); // true/false

// Sprawdza czy unlimited
$plan->isUnlimited(); // true jeśli monthly_limit === 0
```

---

## 🔧 Jak Wyłączyć Sprawdzanie Subskrypcji (Pełny Dostęp)

### Opcja 1: Usunąć Middleware z Routes

**Jeśli middleware jest dodany do konkretnych routes:**

```php
// PRZED (z limitami)
Route::get('movies/{slug}', [MovieController::class, 'show'])
    ->middleware(['api.key.auth', 'plan.rate.limit']);

// PO (bez limitów)
Route::get('movies/{slug}', [MovieController::class, 'show'])
    ->middleware(['api.key.auth']); // tylko autoryzacja
```

---

### Opcja 2: Wyłączyć Middleware Globalnie

**Jeśli middleware jest dodany globalnie w `bootstrap/app.php`:**

```php
// PRZED
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\App\Http\Middleware\PlanBasedRateLimit::class);
});

// PO - usunąć lub zakomentować
->withMiddleware(function (Middleware $middleware): void {
    // $middleware->append(\App\Http\Middleware\PlanBasedRateLimit::class);
});
```

---

### Opcja 3: Feature Flag do Wyłączenia Limitów

**Stworzyć feature flag `disable_rate_limiting`:**

**1. Utworzyć feature flag:**
```php
// api/app/Features/disable_rate_limiting.php
<?php
declare(strict_types=1);
namespace App\Features;
class disable_rate_limiting extends BaseFeature {}
```

**2. Dodać do config:**
```php
// api/config/pennant.php
'disable_rate_limiting' => [
    'class' => disable_rate_limiting::class,
    'description' => 'Disable all rate limiting and subscription checks (full access).',
    'category' => 'operations',
    'default' => false,
    'togglable' => true,
],
```

**3. Zmodyfikować middleware:**
```php
// api/app/Http/Middleware/PlanBasedRateLimit.php
public function handle(Request $request, Closure $next): Response
{
    // Wyłącz limity jeśli feature flag jest aktywny
    if (Feature::active('disable_rate_limiting')) {
        return $next($request);
    }

    // ... reszta logiki sprawdzania limitów
}
```

**4. Włączyć przez API:**
```bash
POST /api/v1/admin/flags/disable_rate_limiting
{
  "state": "on"
}
```

---

### Opcja 4: Environment Variable

**Dodać zmienną środowiskową:**

**1. W `.env`:**
```env
DISABLE_RATE_LIMITING=true
```

**2. W middleware:**
```php
// api/app/Http/Middleware/PlanBasedRateLimit.php
public function handle(Request $request, Closure $next): Response
{
    // Wyłącz limity jeśli zmienna środowiskowa jest ustawiona
    if (config('app.disable_rate_limiting', false)) {
        return $next($request);
    }

    // ... reszta logiki
}
```

**3. W `config/app.php`:**
```php
'disable_rate_limiting' => env('DISABLE_RATE_LIMITING', false),
```

---

## 🎯 Którą Opcję Wybrać?

| Opcja | Zalety | Wady | Kiedy użyć |
|-------|--------|------|------------|
| **1. Usunąć z routes** | Proste, precyzyjne | Trzeba zmienić każdy route | Tylko dla konkretnych endpointów |
| **2. Wyłączyć globalnie** | Najprostsze | Wyłącza wszędzie | Development/testing |
| **3. Feature Flag** | Elastyczne, można włączyć/wyłączyć bez deploy | Wymaga implementacji | Production - czasowe wyłączenie |
| **4. Environment Variable** | Proste, szybkie | Trzeba restartować serwer | Development/testing |

**Rekomendacja:**
- **Development/Testing:** Opcja 2 lub 4 (najprostsze)
- **Production:** Opcja 3 (feature flag - elastyczne)

---

## 📝 Przykład: Wyłączenie przez Feature Flag

### Krok 1: Utworzyć Feature Flag

```php
// api/app/Features/disable_rate_limiting.php
<?php
declare(strict_types=1);
namespace App\Features;
class disable_rate_limiting extends BaseFeature {}
```

### Krok 2: Dodać do Config

```php
// api/config/pennant.php
'disable_rate_limiting' => [
    'class' => disable_rate_limiting::class,
    'description' => 'Disable all rate limiting and subscription checks (full access).',
    'category' => 'operations',
    'default' => false,
    'togglable' => true,
],
```

### Krok 3: Zmodyfikować Middleware

```php
// api/app/Http/Middleware/PlanBasedRateLimit.php
use Laravel\Pennant\Feature;

public function handle(Request $request, Closure $next): Response
{
    // Wyłącz limity jeśli feature flag jest aktywny
    if (Feature::active('disable_rate_limiting')) {
        // Nadal trackuj użycie (dla analytics), ale nie blokuj
        $apiKey = $request->attributes->get('api_key');
        if ($apiKey instanceof ApiKey) {
            $plan = $apiKey->plan;
            $response = $next($request);
            $this->trackUsage($apiKey, $plan, $request, $response);
            return $response;
        }
        return $next($request);
    }

    // ... reszta logiki sprawdzania limitów
}
```

### Krok 4: Włączyć przez Admin API

```bash
# Włączyć pełny dostęp
curl -X POST http://localhost:8000/api/v1/admin/flags/disable_rate_limiting \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{"state": "on"}'

# Wyłączyć (przywrócić limity)
curl -X POST http://localhost:8000/api/v1/admin/flags/disable_rate_limiting \
  -u admin:password \
  -H "Content-Type: application/json" \
  -d '{"state": "off"}'
```

---

## ⚠️ Uwagi Bezpieczeństwa

1. **Nigdy nie wyłączaj w produkcji bez powodu** - może prowadzić do nadużyć
2. **Używaj feature flag** - łatwiej włączyć/wyłączyć bez deploy
3. **Monitoruj użycie** - nawet przy wyłączonych limitach, trackuj użycie dla analytics
4. **Tylko dla adminów** - feature flag powinien być dostępny tylko dla adminów

---

## 📊 Co się Dzieje Gdy Wyłączysz Limity?

### Z Wyłączonymi Limitami:
- ✅ **Wszystkie zapytania przechodzą** - brak blokowania 429
- ✅ **Użycie nadal jest trackowane** - dla analytics
- ✅ **Pełny dostęp do wszystkich funkcji** - niezależnie od planu
- ⚠️ **Brak ochrony przed nadużyciami** - użytkownicy mogą wysyłać nieograniczoną liczbę zapytań

### Z Włączonymi Limitami (domyślnie):
- ✅ **Ochrona przed nadużyciami** - limity miesięczne i per-minute
- ✅ **Różne limity dla różnych planów** - Free: 100/mies, Pro: 10k/mies, Enterprise: unlimited
- ✅ **Rate limiting** - ochrona przed spamem
- ⚠️ **Możliwe blokowanie** - 429 gdy limit przekroczony

---

## 🔗 Powiązane Dokumenty

- [Rate Limiting Documentation](../reference/RATE_LIMITING.md)
- [Subscription Plans Documentation](../reference/SUBSCRIPTION_PLANS.md)
- [Feature Flags Guide](../reference/FEATURE_FLAGS.md)

---

**Last Updated:** 2025-01-27

