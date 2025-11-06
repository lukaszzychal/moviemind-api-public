# 🚦 Rate Limiting dla Jobs Endpoint

**Endpoint:** `GET /api/v1/jobs/{id}`  
**Status:** ✅ Zaimplementowane  
**Data:** 2025-11-04

---

## 📋 **Przegląd**

Rate limiting został zaimplementowany dla endpointu `/api/v1/jobs/{id}` aby zapobiec nadużyciom w publicznym API.

---

## ⚙️ **Konfiguracja**

### **Limit:**
- **60 requestów na minutę** per IP address
- Limit resetuje się po 1 minucie

### **Implementacja:**
```php
// api/routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});
```

**Format:** `throttle:max_attempts,decay_minutes`
- `60` - maksymalna liczba requestów
- `1` - przedział czasowy w minutach

---

## 🔒 **Zachowanie**

### **Normal Request (< 60/min):**
```bash
GET /api/v1/jobs/7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d
```

**Response:** `200 OK`
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "DONE",
  "entity": "MOVIE",
  "slug": "the-matrix-1999",
  "id": 1
}
```

---

### **Rate Limited (≥ 60/min):**
```bash
GET /api/v1/jobs/7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d
```

**Response:** `429 Too Many Requests`
```json
{
  "message": "Too many requests. Please try again later."
}
```

**Headers:**
```
Retry-After: 60
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
```

---

## 📊 **Limity per IP**

### **Jak działa:**
- Laravel automatycznie identyfikuje IP klienta
- Każdy IP ma osobny licznik
- Limit jest resetowany po 1 minucie

### **Przykład:**
```
IP 1.2.3.4: 60 requests/min ✅
IP 5.6.7.8: 60 requests/min ✅ (osobny licznik)
```

---

## 🧪 **Testy**

### **Testy Automatyczne:**
```bash
php artisan test --filter=JobsApiTest
```

**Testy obejmują:**
- ✅ Normal request zwraca 200
- ✅ 60 requests/min - wszystkie przechodzą
- ✅ 61st request - zwraca 429
- ✅ Rate limiting per IP
- ✅ 404 dla unknown job (nie wpływa na rate limit)

---

## 📝 **Dokumentacja API**

### **OpenAPI Spec:**
Dokumentacja została zaktualizowana w `docs/openapi.yaml`:
- ✅ Dodano opis rate limiting
- ✅ Dodano response 429
- ✅ Dodano headers (Retry-After)

---

## 🔄 **Best Practices dla Klientów**

### **1. Implementuj Retry Logic:**
```javascript
async function checkJobStatus(jobId, maxRetries = 5) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(`/api/v1/jobs/${jobId}`);
      
      if (response.status === 429) {
        // Rate limited - wait before retry
        const retryAfter = parseInt(response.headers.get('Retry-After') || '60');
        await sleep(retryAfter * 1000);
        continue;
      }
      
      return await response.json();
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      await sleep(2000);
    }
  }
}
```

### **2. Użyj Exponential Backoff:**
```javascript
async function checkJobStatusWithBackoff(jobId) {
  let delay = 2000; // Start with 2 seconds
  
  while (true) {
    const response = await fetch(`/api/v1/jobs/${jobId}`);
    
    if (response.status === 429) {
      delay = Math.min(delay * 2, 60000); // Max 60 seconds
      await sleep(delay);
      continue;
    }
    
    const data = await response.json();
    if (data.status === 'DONE' || data.status === 'FAILED') {
      return data;
    }
    
    await sleep(2000); // Poll every 2 seconds
  }
}
```

### **3. Cache Response:**
```javascript
// Cache job status locally to reduce API calls
const jobCache = new Map();

async function getJobStatus(jobId) {
  if (jobCache.has(jobId)) {
    const cached = jobCache.get(jobId);
    if (Date.now() - cached.timestamp < 5000) { // 5 second cache
      return cached.data;
    }
  }
  
  const data = await checkJobStatus(jobId);
  jobCache.set(jobId, { data, timestamp: Date.now() });
  
  return data;
}
```

---

## ⚙️ **Konfiguracja (Zaawansowana)**

### **Zmiana Limitu:**
Edytuj `api/routes/api.php`:
```php
// Więcej requestów
Route::middleware('throttle:120,1')->group(function () {
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});

// Dłuższy przedział czasowy
Route::middleware('throttle:60,5')->group(function () {
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});
```

### **Custom Rate Limiter:**
Możesz utworzyć custom rate limiter w `bootstrap/app.php`:
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('jobs', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});
```

Następnie użyj:
```php
Route::middleware('throttle:jobs')->group(function () {
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});
```

---

## 🎯 **Rekomendacje**

### **Dla Klientów:**
1. ✅ Implementuj retry logic z exponential backoff
2. ✅ Cache job status lokalnie (5-10 sekund)
3. ✅ Nie poll częściej niż co 2 sekundy
4. ✅ Obsługuj response 429 gracefully

### **Dla Deweloperów:**
1. ✅ Monitoruj rate limit hits (429 responses)
2. ✅ Rozważ zwiększenie limitu jeśli potrzeba
3. ✅ Rozważ różne limity dla różnych planów (Free/Pro)

---

## 📊 **Monitoring**

### **Sprawdzenie Rate Limit Hits:**
```bash
# Logs
tail -f storage/logs/laravel.log | grep "429"

# Horizon dashboard
# Rate limit hits mogą być widoczne w metrykach
```

### **Metryki do Monitorowania:**
- Liczba 429 responses per IP
- Średni czas między requestami
- Peak usage times

---

## 🔐 **Bezpieczeństwo**

### **Ochrona przed:**
- ✅ **Brute force polling** - ogranicza liczbę requestów
- ✅ **DoS attacks** - chroni serwer przed overload
- ✅ **Resource abuse** - zapobiega nadmiernemu użyciu Redis cache

### **Limitations:**
- ⚠️ Rate limiting działa per IP - łatwo obejść przez proxy/VPN
- ⚠️ Dla production warto rozważyć owner-based authorization (TASK-012)

---

## 📚 **Dodatkowe Zasoby**

- [Laravel Rate Limiting Documentation](https://laravel.com/docs/11.x/routing#rate-limiting)
- [JOBS_ENDPOINT_ANALYSIS.md](./JOBS_ENDPOINT_ANALYSIS.md) - Analiza bezpieczeństwa
- [OpenAPI Spec](./openapi.yaml) - Dokumentacja API

---

**Ostatnia aktualizacja:** 2025-11-04  
**Status:** ✅ Zaimplementowane i przetestowane

