# TASK-018: OpenAI API Health Check Endpoint

**Data:** 2025-11-04  
**Status:** ✅ Implementacja zakończona

---

## 📋 Opis

Dodano endpoint do sprawdzania połączenia z OpenAI API. Endpoint testuje konfigurację i dostępność OpenAI API bez konsumowania tokenów.

---

## 🔗 Endpoint

### `GET /api/v1/admin/health`

**Opis:** Sprawdza status połączenia z OpenAI API

**Odpowiedź (sukces):**
```json
{
  "status": "healthy",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": true,
      "status_code": 200,
      "message": "OpenAI API connection successful",
      "model_count": 50,
      "configured_model": "gpt-4o-mini",
      "model_available": true,
      "available_models": [
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4-turbo",
        "..."
      ]
    }
  }
}
```

**Odpowiedź (błąd):**
```json
{
  "status": "degraded",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": false,
      "status_code": 401,
      "error": "Incorrect API key provided",
      "error_type": "invalid_request_error"
    }
  }
}
```

**Status Codes:**
- `200` - Healthy (OpenAI API działa)
- `503` - Degraded (OpenAI API nie działa)

---

## 🧪 Testowanie

### Przykład użycia:

```bash
# Test health check
curl http://localhost:8000/api/v1/admin/health

# Z formatowaniem JSON
curl http://localhost:8000/api/v1/admin/health | python3 -m json.tool
```

### Przykładowe odpowiedzi:

**1. Sukces (API działa):**
```json
{
  "status": "healthy",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": true,
      "status_code": 200,
      "message": "OpenAI API connection successful",
      "model_count": 50,
      "configured_model": "gpt-4o-mini",
      "model_available": true,
      "available_models": [
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4-turbo",
        "gpt-4",
        "gpt-3.5-turbo"
      ]
    }
  }
}
```

**2. Błąd (brak API key):**
```json
{
  "status": "degraded",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": false,
      "status_code": null,
      "error": "OpenAI API key not configured. Set OPENAI_API_KEY in .env"
    }
  }
}
```

**3. Błąd (nieprawidłowy API key):**
```json
{
  "status": "degraded",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": false,
      "status_code": 401,
      "error": "Incorrect API key provided",
      "error_type": "invalid_request_error"
    }
  }
}
```

**4. Błąd (rate limit):**
```json
{
  "status": "degraded",
  "timestamp": "2025-11-04T20:00:00+00:00",
  "services": {
    "openai": {
      "success": false,
      "status_code": 429,
      "error": "Rate limit exceeded",
      "error_type": "rate_limit_error"
    }
  }
}
```

---

## 🔧 Implementacja

### 1. Metoda `testConnection()` w `OpenAiClient`

**Lokalizacja:** `api/app/Services/OpenAiClient.php`

**Funkcjonalność:**
- Sprawdza czy API key jest skonfigurowany
- Wykonuje GET request do `/v1/models` (nie konsumuje tokenów)
- Zwraca szczegółowe informacje o statusie połączenia
- Sprawdza czy skonfigurowany model jest dostępny

**Endpoint OpenAI:** `GET https://api.openai.com/v1/models`

**Dlaczego `/v1/models`?**
- ✅ Nie konsumuje tokenów
- ✅ Szybkie sprawdzenie (lightweight)
- ✅ Sprawdza autentyczność API key
- ✅ Pokazuje dostępne modele

### 2. Controller `HealthController`

**Lokalizacja:** `api/app/Http/Controllers/Admin/HealthController.php`

**Funkcjonalność:**
- Wywołuje `testConnection()` z `OpenAiClient`
- Zwraca status zdrowia aplikacji
- HTTP status code zależy od statusu OpenAI API

### 3. Route

**Lokalizacja:** `api/routes/api.php`

```php
Route::prefix('v1/admin')->group(function () {
    Route::get('health', [HealthController::class, 'check']);
});
```

---

## 📊 Pola odpowiedzi

| Pole | Typ | Opis |
|------|-----|------|
| `status` | string | `"healthy"` lub `"degraded"` |
| `timestamp` | string | ISO 8601 timestamp |
| `services.openai.success` | boolean | Czy połączenie się udało |
| `services.openai.status_code` | int\|null | HTTP status code z OpenAI API |
| `services.openai.message` | string | Komunikat sukcesu |
| `services.openai.model_count` | int | Liczba dostępnych modeli |
| `services.openai.configured_model` | string | Model skonfigurowany w `.env` |
| `services.openai.model_available` | boolean | Czy skonfigurowany model jest dostępny |
| `services.openai.available_models` | array | Pierwsze 10 dostępnych modeli |
| `services.openai.error` | string | Komunikat błędu (jeśli wystąpił) |
| `services.openai.error_type` | string\|null | Typ błędu z OpenAI API |

---

## 🔍 Uwagi

1. **Nie konsumuje tokenów** - używa endpoint `/v1/models` który jest darmowy
2. **Szybkie sprawdzenie** - timeout 10 sekund
3. **Logowanie błędów** - wszystkie błędy są logowane w `storage/logs/laravel.log`
4. **Czytelne odpowiedzi** - JSON z jasnymi komunikatami
5. **Rozszerzalne** - można dodać więcej serwisów (database, redis, etc.)

---

## 🚀 Przykłady użycia

### Monitoring

```bash
# Sprawdź status w cron job
curl -s http://localhost:8000/api/v1/admin/health | jq '.status'
# Output: "healthy" lub "degraded"
```

### Alerting

```bash
# Sprawdź czy OpenAI działa
if curl -s http://localhost:8000/api/v1/admin/health | jq -e '.services.openai.success == true' > /dev/null; then
  echo "✅ OpenAI API is working"
else
  echo "❌ OpenAI API is not working"
fi
```

### Debugging

```bash
# Pełna odpowiedź z szczegółami
curl http://localhost:8000/api/v1/admin/health | python3 -m json.tool
```

---

## 🔗 Powiązane dokumenty

- [TASK_018_ENDPOINT_TEST_RESULTS.md](./TASK_018_ENDPOINT_TEST_RESULTS.md) - Testy endpointów
- [TASK_018_REAL_AI_TEST_RESULTS.md](./TASK_018_REAL_AI_TEST_RESULTS.md) - Konfiguracja
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference/models/list)

