# 🔄 Backward Compatibility: `entity_id` → `slug`

**Data:** 2025-11-01  
**Status:** ✅ Zaimplementowane w 2 miejscach

---

## 📍 Gdzie jest zaimplementowane?

Backward compatibility działa w **2 miejscach** (podwójna ochrona):

### **1. `GenerateRequest.php` - `prepareForValidation()` (PREFEROWANE)**

**Lokalizacja:** `api/app/Http/Requests/GenerateRequest.php` (linie 72-84)

**Kod:**
```php
/**
 * Prepare the data for validation.
 * Support both 'slug' and deprecated 'entity_id' fields.
 */
protected function prepareForValidation(): void
{
    // If entity_id is provided but slug is not, use entity_id as slug (backward compatibility)
    if ($this->has('entity_id') && ! $this->has('slug')) {
        $this->merge([
            'slug' => $this->input('entity_id'),
        ]);
    }
}
```

**Kiedy się wykonuje:**
- ✅ **PRZED walidacją** - Laravel automatycznie wywołuje `prepareForValidation()` przed sprawdzeniem reguł
- ✅ **Automatycznie** - nie trzeba nic robić w controllerze
- ✅ **Przejrzyste** - wszystkie dalsze kroki widzą `slug`, nie `entity_id`

**Jak działa:**
1. Sprawdza czy request ma `entity_id` ale NIE MA `slug`
2. Jeśli tak → kopiuje `entity_id` → `slug` za pomocą `merge()`
3. Dalszy kod widzi już `slug` w `$request->validated()`

---

### **2. `GenerateController.php` - Fallback (BACKUP)**

**Lokalizacja:** `api/app/Http/Controllers/Api/GenerateController.php` (linia 24)

**Kod:**
```php
public function generate(GenerateRequest $request): JsonResponse
{
    $validated = $request->validated();
    $entityType = $validated['entity_type'];
    // Support both 'slug' (new) and 'entity_id' (deprecated, backward compatibility)
    $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
    $jobId = (string) Str::uuid();
    // ...
}
```

**Kiedy się wykonuje:**
- ✅ **PO walidacji** - jeśli `prepareForValidation()` nie zadziałało (edge case)
- ✅ **Backup** - dodatkowa warstwa bezpieczeństwa
- ✅ **Operator null coalescing** - `??` sprawdza po kolei: `slug`, potem `entity_id`

---

## 🔍 Jak to działa w praktyce?

### **Scenariusz 1: Stary format (`entity_id`)**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"
}
```

**Kroki:**

1. **Request trafia do Laravel** → `GenerateRequest`
2. **`prepareForValidation()` się wykonuje:**
   ```php
   // Sprawdza: has('entity_id') = true, has('slug') = false
   // Wykonuje: merge(['slug' => 'the-matrix-1999'])
   ```
3. **Walidacja:**
   ```php
   // Teraz request ma OBA pola:
   // entity_id: "the-matrix-1999"
   // slug: "the-matrix-1999"  ← dodane przez prepareForValidation()
   ```
4. **Controller:**
   ```php
   $validated = $request->validated();
   // $validated['slug'] = "the-matrix-1999"  ← już jest!
   $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
   // $slug = "the-matrix-1999"  ← z 'slug'
   ```

**Rezultat:** ✅ Działa jakby było `slug` od początku!

---

### **Scenariusz 2: Nowy format (`slug`)**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999"
}
```

**Kroki:**

1. **Request trafia do Laravel** → `GenerateRequest`
2. **`prepareForValidation()` się wykonuje:**
   ```php
   // Sprawdza: has('entity_id') = false
   // Nie wykonuje merge() - slug już istnieje
   ```
3. **Walidacja:** Zwykła walidacja
4. **Controller:**
   ```php
   $validated = $request->validated();
   // $validated['slug'] = "the-matrix-1999"
   $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
   // $slug = "the-matrix-1999"  ← z 'slug'
   ```

**Rezultat:** ✅ Działa normalnie!

---

### **Scenariusz 3: Oba pola (edge case)**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "entity_id": "inception-2010"
}
```

**Kroki:**

1. **`prepareForValidation()`:**
   ```php
   // Sprawdza: has('entity_id') = true, has('slug') = true
   // NIE wykonuje merge() - slug już istnieje
   ```
2. **Controller:**
   ```php
   $slug = (string) ($validated['slug'] ?? $validated['entity_id'] ?? '');
   // $slug = "the-matrix-1999"  ← 'slug' ma priorytet
   ```

**Rezultat:** ✅ `slug` ma priorytet nad `entity_id`!

---

## 🔄 Flow diagram

```
┌─────────────────────────────────────────────────────────────┐
│ Client Request                                               │
│ {entity_type: "MOVIE", entity_id: "the-matrix-1999"}        │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ GenerateRequest::prepareForValidation()                    │
│                                                             │
│ if (has('entity_id') && !has('slug')) {                    │
│     merge(['slug' => input('entity_id')])  ✅ WYKONANE      │
│ }                                                           │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ Validation Rules                                            │
│                                                             │
│ 'slug' => 'required_without:entity_id|string|max:255'       │
│ 'entity_id' => 'required_without:slug|string|max:255'      │
│                                                             │
│ ✅ Walidacja przechodzi (oba pola istnieją lub jedno)      │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ GenerateController::generate()                             │
│                                                             │
│ $validated = $request->validated();                         │
│ // $validated['slug'] = "the-matrix-1999" ✅                │
│                                                             │
│ $slug = $validated['slug'] ?? $validated['entity_id'];     │
│ // $slug = "the-matrix-1999" ✅                             │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────────────┐
│ SlugValidator & Event & Job                                 │
│                                                             │
│ Używa: $slug = "the-matrix-1999" ✅                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Reguły walidacji

**`required_without`** - oznacza "wymagane jeśli drugie nie istnieje":

```php
'slug' => 'required_without:entity_id|string|max:255',
'entity_id' => 'required_without:slug|string|max:255',
```

**Jak działa:**
- Jeśli jest `slug` → `entity_id` nie jest wymagane (ale może być)
- Jeśli jest `entity_id` → `slug` nie jest wymagane (ale może być)
- Jeśli są oba → oba są walidowane (ale `slug` ma priorytet)
- Jeśli nie ma żadnego → walidacja zwróci błąd

---

## 🧪 Testowanie

### **Test 1: Stary format**

```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "entity_id": "the-matrix-1999"}'
```

**Oczekiwany rezultat:**
```json
{
  "job_id": "uuid",
  "status": "PENDING",
  "slug": "the-matrix-1999",  ← slug w odpowiedzi
  "confidence": 0.9,
  "confidence_level": "high"
}
```

✅ **Działa!** `entity_id` został automatycznie skonwertowany na `slug`.

---

### **Test 2: Nowy format**

```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}'
```

**Oczekiwany rezultat:** Takie same jak Test 1.

✅ **Działa!**

---

### **Test 3: Oba pola (edge case)**

```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999", "entity_id": "inception-2010"}'
```

**Oczekiwany rezultat:**
```json
{
  "slug": "the-matrix-1999"  ← slug ma priorytet
}
```

✅ **Działa!** `slug` ma priorytet nad `entity_id`.

---

## ⚠️ Ważne uwagi

### **Dlaczego 2 miejsca?**

1. **`prepareForValidation()`** - Główna konwersja, przed walidacją
2. **Fallback w controllerze** - Dodatkowa warstwa bezpieczeństwa

**Kiedy fallback może być potrzebny?**
- Jeśli ktoś modyfikuje request ręcznie po walidacji
- Edge cases w Laravel's request lifecycle
- Lepsze bezpieczeństwo = mniej błędów

---

### **Dlaczego `prepareForValidation()` jest lepsze?**

✅ **Automatyczne** - Laravel wywołuje przed walidacją  
✅ **Przejrzyste** - reszta kodu widzi tylko `slug`  
✅ **Spójne** - wszystkie kroki używają `slug`  
✅ **Laravel Best Practice** - zalecana metoda modyfikacji danych przed walidacją

---

### **Dlaczego fallback też jest potrzebny?**

✅ **Bezpieczeństwo** - podwójna ochrona  
✅ **Defensive programming** - lepiej mieć backup  
✅ **Edge cases** - nie wszystkie przypadki są przewidywalne  

---

## 📊 Porównanie podejść

| Podejście | Gdzie | Kiedy | Zalety |
|-----------|-------|-------|--------|
| **`prepareForValidation()`** | `GenerateRequest.php` | PRZED walidacją | ✅ Automatyczne, przejrzyste, Laravel Best Practice |
| **Fallback w controllerze** | `GenerateController.php` | PO walidacji | ✅ Backup, defensive programming |

**Rekomendacja:** Oba! `prepareForValidation()` jako główne, fallback jako backup.

---

## 🔗 Powiązane pliki

1. **`api/app/Http/Requests/GenerateRequest.php`**
   - `prepareForValidation()` - konwersja `entity_id` → `slug`
   - `rules()` - reguły walidacji z `required_without`

2. **`api/app/Http/Controllers/Api/GenerateController.php`**
   - `generate()` - fallback `$validated['slug'] ?? $validated['entity_id']`

3. **`docs/SLUG_VALIDATION_AND_SECURITY.md`**
   - Dokumentacja walidacji slug

---

**Ostatnia aktualizacja:** 2025-11-01  
**Status:** ✅ Działa w obu miejscach

