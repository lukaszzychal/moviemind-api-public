# TASK-018: OpenAI API Budget Check Endpoint

**Data:** 2025-11-04  
**Status:** ✅ Implementacja zakończona

---

## 📋 Opis

Dodano endpoint do sprawdzania dostępności budżetu OpenAI API. Endpoint analizuje rate limit headers i sprawdza organizację, aby określić czy budżet jest dostępny.

**Uwaga:** OpenAI nie udostępnia bezpośredniego endpointu do sprawdzania budżetu. Endpoint sprawdza:
- Rate limit headers z odpowiedzi API
- Informacje o organizacji (jeśli dostępne)
- Status połączenia z API

---

## 🔗 Endpoint

### `GET /api/v1/admin/budget`

**Opis:** Sprawdza dostępność budżetu OpenAI API poprzez analizę rate limit headers

**Odpowiedź (sukces - budżet dostępny):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": true,
    "status_code": 200,
    "has_budget": true,
    "rate_limits": {
      "limit_requests": 5000,
      "requests_remaining": 4850,
      "requests_reset": "2025-11-04T21:00:00Z",
      "limit_tokens": 1000000,
      "tokens_remaining": 950000,
      "tokens_reset": "2025-11-04T21:00:00Z"
    },
    "organization": {
      "id": "org-xxx",
      "name": "My Organization",
      "is_default": true
    },
    "message": "Budget available - API requests are possible"
  }
}
```

**Odpowiedź (sukces - budżet może być wyczerpany):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": true,
    "status_code": 200,
    "has_budget": false,
    "rate_limits": {
      "limit_requests": 5000,
      "requests_remaining": 0,
      "requests_reset": "2025-11-04T21:00:00Z",
      "limit_tokens": 1000000,
      "tokens_remaining": 0,
      "tokens_reset": "2025-11-04T21:00:00Z"
    },
    "organization": null,
    "message": "Budget may be exhausted - check rate limits"
  }
}
```

**Odpowiedź (błąd):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": false,
    "status_code": 401,
    "has_budget": false,
    "error": "Incorrect API key provided"
  }
}
```

**Status Codes:**
- `200` - Budget available (has_budget: true)
- `503` - Budget may be exhausted (has_budget: false) lub błąd API

---

## 🧪 Testowanie

### Przykład użycia:

```bash
# Test budget check
curl http://localhost:8000/api/v1/admin/budget

# Z formatowaniem JSON
curl http://localhost:8000/api/v1/admin/budget | python3 -m json.tool
```

### Przykładowe odpowiedzi:

**1. Budżet dostępny (rate limit headers dostępne):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": true,
    "status_code": 200,
    "has_budget": true,
    "rate_limits": {
      "limit_requests": 5000,
      "requests_remaining": 4850,
      "requests_reset": "2025-11-04T21:00:00Z",
      "limit_tokens": 1000000,
      "tokens_remaining": 950000,
      "tokens_reset": "2025-11-04T21:00:00Z"
    },
    "organization": {
      "id": "org-xxx",
      "name": "My Organization",
      "is_default": true
    },
    "message": "Budget available - API requests are possible"
  }
}
```

**2. Budżet dostępny (rate limit headers niedostępne, ale API działa):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": true,
    "status_code": 200,
    "has_budget": true,
    "rate_limits": {
      "limit_requests": null,
      "requests_remaining": null,
      "requests_reset": null,
      "limit_tokens": null,
      "tokens_remaining": null,
      "tokens_reset": null
    },
    "organization": null,
    "message": "Budget available - API requests are possible"
  }
}
```

**3. Budżet wyczerpany (rate limit = 0):**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": true,
    "status_code": 200,
    "has_budget": false,
    "rate_limits": {
      "limit_requests": 5000,
      "requests_remaining": 0,
      "requests_reset": "2025-11-04T21:00:00Z",
      "limit_tokens": 1000000,
      "tokens_remaining": 0,
      "tokens_reset": "2025-11-04T21:00:00Z"
    },
    "message": "Budget may be exhausted - check rate limits"
  }
}
```

**4. Brak API key:**
```json
{
  "timestamp": "2025-11-04T20:37:24+00:00",
  "openai": {
    "success": false,
    "status_code": null,
    "has_budget": false,
    "error": "OpenAI API key not configured. Set OPENAI_API_KEY in .env"
  }
}
```

---

## 🔧 Implementacja

### 1. Metoda `checkBudget()` w `OpenAiClient`

**Lokalizacja:** `api/app/Services/OpenAiClient.php`

**Funkcjonalność:**
- Wykonuje GET request do `/v1/models` (lightweight, nie konsumuje tokenów)
- Analizuje rate limit headers z odpowiedzi
- Sprawdza organizację (jeśli API key ma dostęp)
- Określa dostępność budżetu na podstawie:
  - Status code 200 = API działa
  - Rate limit headers (jeśli dostępne) = dokładne limity
  - Jeśli headers nie są dostępne, ale status 200 = zakładamy że budżet jest dostępny

**Rate Limit Headers:**
- `x-ratelimit-limit-requests` - Limit requestów
- `x-ratelimit-remaining-requests` - Pozostałe requesty
- `x-ratelimit-reset-requests` - Reset requestów
- `x-ratelimit-limit-tokens` - Limit tokenów
- `x-ratelimit-remaining-tokens` - Pozostałe tokeny
- `x-ratelimit-reset-tokens` - Reset tokenów

**Uwaga:** Nie wszystkie wersje OpenAI API zwracają te headers. Jeśli nie są dostępne, endpoint zakłada że budżet jest dostępny jeśli status jest 200.

### 2. Metoda `getOrganizationInfo()` w `OpenAiClient`

**Funkcjonalność:**
- Próbuje pobrać informacje o organizacji przez `/v1/organizations`
- Zwraca ID, nazwę i czy jest domyślną organizacją
- Może zwrócić `null` jeśli endpoint nie jest dostępny lub API key nie ma dostępu

### 3. Controller `HealthController::budget()`

**Lokalizacja:** `api/app/Http/Controllers/Admin/HealthController.php`

**Funkcjonalność:**
- Wywołuje `checkBudget()` z `OpenAiClient`
- Zwraca status budżetu
- HTTP status code zależy od `has_budget`:
  - `200` jeśli `has_budget: true`
  - `503` jeśli `has_budget: false` lub błąd

### 4. Route

**Lokalizacja:** `api/routes/api.php`

```php
Route::prefix('v1/admin')->group(function () {
    Route::get('budget', [HealthController::class, 'budget']);
});
```

---

## 📊 Pola odpowiedzi

| Pole | Typ | Opis |
|------|-----|------|
| `timestamp` | string | ISO 8601 timestamp |
| `openai.success` | boolean | Czy request się udał |
| `openai.status_code` | int\|null | HTTP status code z OpenAI API |
| `openai.has_budget` | boolean | Czy budżet jest dostępny |
| `openai.rate_limits.limit_requests` | int\|null | Limit requestów |
| `openai.rate_limits.requests_remaining` | int\|null | Pozostałe requesty |
| `openai.rate_limits.requests_reset` | string\|null | Czas resetu requestów |
| `openai.rate_limits.limit_tokens` | int\|null | Limit tokenów |
| `openai.rate_limits.tokens_remaining` | int\|null | Pozostałe tokeny |
| `openai.rate_limits.tokens_reset` | string\|null | Czas resetu tokenów |
| `openai.organization` | object\|null | Informacje o organizacji |
| `openai.organization.id` | string\|null | ID organizacji |
| `openai.organization.name` | string\|null | Nazwa organizacji |
| `openai.organization.is_default` | boolean\|null | Czy domyślna organizacja |
| `openai.message` | string | Komunikat o statusie budżetu |
| `openai.error` | string | Komunikat błędu (jeśli wystąpił) |

---

## 🔍 Logika określania budżetu

### 1. Status Code 200 + Rate Limit Headers dostępne
- Jeśli `requests_remaining > 0` lub `tokens_remaining > 0` → `has_budget: true`
- Jeśli `requests_remaining = 0` i `tokens_remaining = 0` → `has_budget: false`

### 2. Status Code 200 + Rate Limit Headers niedostępne
- Zakładamy że budżet jest dostępny → `has_budget: true`
- (API działa, więc prawdopodobnie ma budżet)

### 3. Status Code != 200
- `has_budget: false`
- Błąd w `error` field

---

## ⚠️ Uwagi i ograniczenia

1. **OpenAI nie ma bezpośredniego endpointu budżetu**
   - Endpoint analizuje rate limit headers
   - Jeśli headers nie są dostępne, zakłada że budżet jest dostępny (jeśli API działa)

2. **Rate Limit Headers nie zawsze są dostępne**
   - Niektóre wersje API mogą nie zwracać tych headers
   - W takim przypadku endpoint zakłada że budżet jest dostępny jeśli status jest 200

3. **Organizacja może być niedostępna**
   - Endpoint `/v1/organizations` może nie być dostępny dla wszystkich API keys
   - W takim przypadku `organization` będzie `null`

4. **Nie konsumuje tokenów**
   - Używa endpoint `/v1/models` (lightweight check)
   - Nie wykonuje żadnych generacji

5. **Rate limits są per-minute**
   - Headers pokazują limity per minute
   - Reset następuje co minutę

---

## 🚀 Przykłady użycia

### Monitoring

```bash
# Sprawdź czy budżet jest dostępny
curl -s http://localhost:8000/api/v1/admin/budget | jq '.openai.has_budget'
# Output: true lub false
```

### Alerting

```bash
# Sprawdź czy budżet jest wyczerpany
if curl -s http://localhost:8000/api/v1/admin/budget | jq -e '.openai.has_budget == false' > /dev/null; then
  echo "⚠️  OpenAI budget may be exhausted!"
else
  echo "✅ OpenAI budget is available"
fi
```

### Debugging

```bash
# Pełna odpowiedź z szczegółami
curl http://localhost:8000/api/v1/admin/budget | python3 -m json.tool
```

### Sprawdzenie rate limits

```bash
# Sprawdź pozostałe requesty
curl -s http://localhost:8000/api/v1/admin/budget | jq '.openai.rate_limits.requests_remaining'
```

---

## 🔗 Powiązane dokumenty

- [TASK_018_OPENAI_HEALTH_CHECK.md](./TASK_018_OPENAI_HEALTH_CHECK.md) - Health check endpoint
- [TASK_018_ENDPOINT_TEST_RESULTS.md](./TASK_018_ENDPOINT_TEST_RESULTS.md) - Testy endpointów
- [OpenAI API Rate Limits](https://platform.openai.com/docs/guides/rate-limits)

---

## 📝 Alternatywne rozwiązania

Jeśli potrzebujesz dokładniejszego sprawdzania budżetu:

1. **OpenAI Dashboard API** (jeśli dostępne)
   - Wymaga dodatkowej autoryzacji
   - Może wymagać webhook integration

2. **Własne śledzenie kosztów**
   - Loguj każdy request i jego koszt
   - Przechowuj w bazie danych
   - Obliczaj pozostały budżet

3. **OpenAI Billing API** (jeśli dostępne)
   - Sprawdź czy jest dostępny dla Twojego planu
   - Może wymagać specjalnych uprawnień

---

**Uwaga:** Obecna implementacja jest najlepszym możliwym rozwiązaniem przy użyciu publicznego OpenAI API, które nie udostępnia bezpośredniego endpointu budżetu.

