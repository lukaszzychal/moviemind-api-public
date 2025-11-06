# 🔍 Analiza Endpointu `/api/v1/jobs/{id}`

**Endpoint:** `GET /api/v1/jobs/{id}`  
**Status:** ✅ Działa (publiczny)  
**Data analizy:** 2025-01-27

---

## 📋 **Zastosowanie i Cel**

### **Główny cel:**
Endpoint służy do sprawdzania statusu asynchronicznej generacji AI (polling pattern).

### **Workflow:**

1. **Klient wywołuje generowanie:**
   ```bash
   POST /api/v1/generate
   {
     "entity_type": "MOVIE",
     "slug": "the-matrix-1999"
   }
   ```

2. **Otrzymuje odpowiedź z `job_id`:**
   ```json
   {
     "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
     "status": "PENDING",
     "message": "Generation queued for movie by slug",
     "slug": "the-matrix-1999"
   }
   ```

3. **Klient może sprawdzać status (polling):**
   ```bash
   GET /api/v1/jobs/7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d
   ```

4. **Odpowiedzi mogą być:**
   - `PENDING` - generowanie w trakcie
   - `DONE` - generowanie zakończone, entity utworzone
   - `FAILED` - generowanie nie powiodło się
   - `UNKNOWN` - job nie istnieje (404)

---

## 🔄 **Przypadki Użycia**

### **1. Polling Pattern (Asynchroniczna generacja)**
Klient uruchamia generowanie i okresowo sprawdza status:
```javascript
// 1. Wywołaj generowanie
const response = await fetch('/api/v1/generate', {
  method: 'POST',
  body: JSON.stringify({ entity_type: 'MOVIE', slug: 'the-matrix-1999' })
});
const { job_id } = await response.json();

// 2. Polling statusu
const checkStatus = async () => {
  const statusResponse = await fetch(`/api/v1/jobs/${job_id}`);
  const status = await statusResponse.json();
  
  if (status.status === 'DONE') {
    // Generowanie zakończone, pobierz entity
    return fetch(`/api/v1/movies/${status.slug}`);
  } else if (status.status === 'FAILED') {
    // Obsłuż błąd
    throw new Error(status.error || 'Generation failed');
  } else {
    // Spróbuj ponownie za chwilę
    setTimeout(checkStatus, 2000);
  }
};
```

### **2. Webhook Alternative (gdy webhooks nie są dostępne)**
Klient może używać polling jako alternatywę dla webhooks:
- Prostsze do implementacji
- Nie wymaga publicznego endpointu webhook
- Działa przez firewalle

### **3. Progress Tracking**
Klient może pokazywać użytkownikowi postęp generowania:
- "Generowanie w toku..." (PENDING)
- "Gotowe!" (DONE)
- "Błąd generowania" (FAILED)

---

## 🔒 **Bezpieczeństwo - Analiza**

### **Obecna implementacja:**
```php
// JobsController.php - BEZ AUTORYZACJI
public function show(string $id)
{
    $data = Cache::get($this->cacheKey($id));
    if (! $data) {
        return response()->json([
            'job_id' => $id,
            'status' => 'UNKNOWN',
        ], 404);
    }
    return response()->json($data);
}
```

### **Problemy bezpieczeństwa:**

#### ❌ **1. Publiczny dostęp bez autoryzacji**
- Każdy może sprawdzić status dowolnego `job_id`
- Jeśli `job_id` jest przewidywalny (UUID v4 jest bezpieczny, ale...)
- Możliwość wycieku informacji o tym, co kto generuje

#### ❌ **2. Brak weryfikacji własności job**
- Klient A może sprawdzić job klienta B
- Nie ma mechanizmu weryfikacji, że klient ma prawo do sprawdzania tego job

#### ❌ **3. Potencjalny wyciek informacji**
- Status może zawierać wrażliwe dane:
  - `slug` - co kto próbuje wygenerować
  - `status` - sukces/porażka
  - `confidence` - poziom pewności AI

#### ⚠️ **4. UUID v4 jest bezpieczny, ale...**
- Jeśli `job_id` wycieknie (logs, errors), każdy może go użyć
- Brak czasu wygaśnięcia dla cache (15 min jest OK, ale...)

---

## 🎯 **Rekomendacje**

### **Opcja 1: Publiczny (obecna implementacja) - ⚠️ NIEZALECANE**

**Zalety:**
- ✅ Prosty w użyciu
- ✅ Nie wymaga autoryzacji
- ✅ Działa dla publicznych API

**Wady:**
- ❌ Brak kontroli dostępu
- ❌ Możliwość wycieku informacji
- ❌ Nie nadaje się dla wrażliwych danych

**Kiedy używać:**
- Publiczne API bez autoryzacji
- Generowanie nie zawiera wrażliwych danych
- `job_id` jest jednorazowy i nieprzewidywalny

---

### **Opcja 2: Owner-Based (ZALECANE) - ✅**

**Implementacja:**
```php
// JobsController.php
public function show(Request $request, string $id)
{
    $data = Cache::get($this->cacheKey($id));
    
    if (! $data) {
        return response()->json([
            'job_id' => $id,
            'status' => 'UNKNOWN',
        ], 404);
    }
    
    // Weryfikacja własności (jeśli jest autoryzacja)
    if ($request->user()) {
        // Sprawdź czy job należy do użytkownika
        // Można dodać user_id do cache podczas tworzenia job
        if (isset($data['user_id']) && $data['user_id'] !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    }
    
    return response()->json($data);
}
```

**Zalety:**
- ✅ Bezpieczne - tylko właściciel może sprawdzić status
- ✅ Chroni przed wyciekiem informacji
- ✅ Działa z autoryzacją API keys/tokens

**Wady:**
- ⚠️ Wymaga systemu autoryzacji
- ⚠️ Trzeba dodać `user_id` do cache podczas tworzenia job

**Kiedy używać:**
- API z autoryzacją (API keys, OAuth, JWT)
- Wrażliwe dane w generowaniu
- Multi-tenant system

---

### **Opcja 3: Admin Only - ❌ NIEZALECANE dla tego przypadku**

**Implementacja:**
```php
// routes/api.php
Route::middleware(['auth:admin'])->group(function () {
    Route::get('jobs/{id}', [JobsController::class, 'show']);
});
```

**Zalety:**
- ✅ Pełna kontrola dostępu
- ✅ Tylko admin może sprawdzić status

**Wady:**
- ❌ Niepraktyczne - klienci nie mogą sprawdzać swoich własnych jobów
- ❌ Niszczy użyteczność API dla klientów

**Kiedy używać:**
- Tylko do debugowania/monitoringu przez adminów
- Nie jako endpoint dla klientów

---

### **Opcja 4: Publiczny z Token-Based (KOMPROMIS) - ✅ DOBRA ALTERNATYWA**

**Implementacja:**
```php
// GenerateController - zwraca job_id + secret_token
return response()->json([
    'job_id' => $jobId,
    'secret_token' => $secretToken, // HMAC(job_id + timestamp)
    'status' => 'PENDING',
]);

// JobsController - wymaga token
public function show(Request $request, string $id)
{
    $token = $request->query('token');
    
    if (! $this->validateToken($id, $token)) {
        return response()->json(['error' => 'Invalid token'], 403);
    }
    
    // ... reszta logiki
}
```

**Zalety:**
- ✅ Bez autoryzacji użytkownika
- ✅ Bezpieczne - tylko kto ma token może sprawdzić
- ✅ Działa dla publicznych API

**Wady:**
- ⚠️ Wymaga zarządzania tokenami
- ⚠️ Trzeba przekazywać token w każdym request

**Kiedy używać:**
- Publiczne API bez autoryzacji
- Potrzebna kontrola dostępu do jobów
- Kompromis między bezpieczeństwem a prostotą

---

## 📊 **Porównanie Opcji**

| Opcja | Bezpieczeństwo | Użyteczność | Złożoność | Rekomendacja |
|-------|---------------|-------------|-----------|--------------|
| **Publiczny** | ⚠️ Niski | ✅ Wysoka | ✅ Niska | ❌ Niezalecane |
| **Owner-Based** | ✅ Wysoki | ✅ Wysoka | ⚠️ Średnia | ✅ **Zalecane** |
| **Admin Only** | ✅ Wysoki | ❌ Niska | ✅ Niska | ❌ Niezalecane |
| **Token-Based** | ✅ Średni | ✅ Wysoka | ⚠️ Średnia | ✅ **Dobra alternatywa** |

---

## 🎯 **Rekomendacja Finalna**

### **Dla obecnego projektu (publiczne API):**

**Krótkoterminowo (MVP):**
- ✅ **Zostaw publiczny** - ale dodaj dokumentację o ograniczeniach
- ✅ Dodaj rate limiting na endpoint
- ✅ Dodaj TTL dla cache (już jest 15 min)

**Długoterminowo (Production):**
- ✅ **Zaimplementuj Owner-Based** gdy dodasz autoryzację
- ✅ Lub **Token-Based** jeśli chcesz pozostać bez autoryzacji użytkowników

### **Implementacja Owner-Based (gdy będzie autoryzacja):**

1. **Dodaj `user_id` do cache podczas tworzenia job:**
   ```php
   // GenerateController, MovieController, PersonController
   Cache::put("ai_job:{$jobId}", [
       'job_id' => $jobId,
       'user_id' => $request->user()?->id, // Opcjonalne jeśli jest autoryzacja
       'status' => 'PENDING',
       // ...
   ], now()->addMinutes(15));
   ```

2. **Weryfikuj w JobsController:**
   ```php
   if ($request->user() && isset($data['user_id'])) {
       if ($data['user_id'] !== $request->user()->id) {
           return response()->json(['error' => 'Unauthorized'], 403);
       }
   }
   ```

3. **Dla publicznych klientów (bez autoryzacji):**
   - Pozostaw dostęp publiczny (backward compatibility)
   - Lub wymagaj token w query param

---

## 📝 **Podsumowanie**

### **Zastosowanie:**
- ✅ Polling pattern dla asynchronicznej generacji
- ✅ Sprawdzanie statusu generowania AI
- ✅ Progress tracking dla użytkowników

### **Bezpieczeństwo:**
- ⚠️ Obecnie publiczny - brak autoryzacji
- ⚠️ Możliwość wycieku informacji
- ✅ UUID v4 jest bezpieczny (nieprzewidywalny)

### **Rekomendacja:**
- **Krótkoterminowo:** Zostaw publiczny, dodaj rate limiting
- **Długoterminowo:** Implementuj Owner-Based gdy będzie autoryzacja
- **Alternatywa:** Token-Based dla publicznych API bez autoryzacji użytkowników

---

**Ostatnia aktualizacja:** 2025-01-27

