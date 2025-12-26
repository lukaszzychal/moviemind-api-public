# Prosty przykład - Jak czytać plik kolekcji Postman

## 🎯 Prosty przykład: "Get movie by slug"

Otwórz plik `moviemind-api.postman_collection.json` i znajdź linię **~80** (żądanie "Get movie by slug").

---

## 📍 KROK 1: Znajdź nazwę żądania

```json
{
  "name": "Get movie by slug",    // ← TO JEST NAZWA ŻĄDANIA
```

**To jest:** Nazwa żądania, które widzisz w Postmanie.

---

## 📍 KROK 2: Znajdź informacje o ENDPOINCIE

Szukaj sekcji `"request"`:

```json
"request": {                      // ← TUTAJ SĄ INFORMACJE O ENDPOINCIE
  "method": "GET",                 // ← Metoda HTTP: GET
  "url": {
    "raw": "{{baseUrl}}/api/v1/movies/{{movieSlug}}",  // ← TO JEST ENDPOINT!
    "path": ["api", "v1", "movies", "{{movieSlug}}"]
  },
  "header": [
    {
      "key": "Accept",
      "value": "application/json"
    }
  ]
}
```

**Co to znaczy:**
- **Metoda:** `GET`
- **Endpoint:** `/api/v1/movies/{slug}`
- **Pełny URL:** `http://localhost:8000/api/v1/movies/the-matrix-1999` (gdy `baseUrl = http://localhost:8000` i `movieSlug = the-matrix-1999`)

---

## 📍 KROK 3: Znajdź informacje o TESTACH

Szukaj sekcji `"event"` z `"listen": "test"`:

```json
"event": [                         // ← TUTAJ SĄ TESTY
  {
    "listen": "test",              // ← "test" = to są testy
    "script": {
      "type": "text/javascript",
      "exec": [                    // ← TUTAJ JEST KOD TESTÓW
        "pm.test(\"Status code is 200\", function () {",
        "  pm.response.to.have.status(200);",
        "});",
        "const json = pm.response.json();",
        "pm.test(\"Response contains movie id and slug\", function () {",
        "  pm.expect(json).to.have.property('id');",
        "  pm.expect(json).to.have.property('slug');",
        "});",
        "pm.test(\"Response exposes descriptions_count\", function () {",
        "  pm.expect(json).to.have.property('descriptions_count');",
        "});"
      ]
    }
  }
]
```

**Co to znaczy:**

### Test 1: Status code
```javascript
pm.test("Status code is 200", function () {
  pm.response.to.have.status(200);
});
```
**Sprawdza:** Czy odpowiedź ma status code 200

### Test 2: Pole 'id' i 'slug'
```javascript
pm.test("Response contains movie id and slug", function () {
  pm.expect(json).to.have.property('id');
  pm.expect(json).to.have.property('slug');
});
```
**Sprawdza:** Czy odpowiedź zawiera pola `id` i `slug`

### Test 3: Pole 'descriptions_count'
```javascript
pm.test("Response exposes descriptions_count", function () {
  pm.expect(json).to.have.property('descriptions_count');
});
```
**Sprawdza:** Czy odpowiedź zawiera pole `descriptions_count`

---

## 📋 PEŁNY PRZYKŁAD - Wszystko razem

```json
{
  "name": "Get movie by slug",                    // ← NAZWA
  
  "request": {                                    // ← ENDPOINT
    "method": "GET",
    "url": {
      "raw": "{{baseUrl}}/api/v1/movies/{{movieSlug}}"
    }
  },
  
  "event": [                                      // ← TESTY
    {
      "listen": "test",
      "script": {
        "exec": [
          "pm.test(\"Status code is 200\", ...)",           // Test 1
          "pm.test(\"Response contains id and slug\", ...)", // Test 2
          "pm.test(\"Response exposes descriptions_count\", ...)" // Test 3
        ]
      }
    }
  ]
}
```

---

## 🎯 PODSUMOWANIE - Gdzie co jest

```
┌─────────────────────────────────────────┐
│  "name": "Get movie by slug"            │  ← Nazwa żądania
├─────────────────────────────────────────┤
│  "request": {                            │
│    "method": "GET"                       │  ← Metoda HTTP
│    "url": {                              │
│      "raw": ".../api/v1/movies/..."     │  ← ENDPOINT (URL)
│    }                                     │
│  }                                       │
├─────────────────────────────────────────┤
│  "event": [                              │
│    {                                     │
│      "listen": "test"                    │  ← To są testy
│      "script": {                         │
│        "exec": [                         │
│          "pm.test(...)"                  │  ← TEST 1: Status code
│          "pm.test(...)"                  │  ← TEST 2: Pole 'id' i 'slug'
│          "pm.test(...)"                  │  ← TEST 3: Pole 'descriptions_count'
│        ]                                 │
│      }                                   │
│    }                                     │
│  ]                                       │
└─────────────────────────────────────────┘
```

---

## 🔍 Jak to znaleźć w pliku?

1. **Otwórz:** `docs/postman/moviemind-api.postman_collection.json`
2. **Wyszukaj:** `"Get movie by slug"` (Ctrl+F / Cmd+F)
3. **Znajdź:**
   - `"request"` → endpoint
   - `"event"` → testy

---

## 💡 Proste tłumaczenie

**Endpoint = Gdzie wysłać żądanie**
- `GET /api/v1/movies/the-matrix-1999`

**Testy = Co sprawdzić w odpowiedzi**
- ✅ Status code = 200?
- ✅ Jest pole `id`?
- ✅ Jest pole `slug`?
- ✅ Jest pole `descriptions_count`?

---

## 🎬 Przykład w działaniu

1. **Postman wysyła żądanie:**
   ```
   GET http://localhost:8000/api/v1/movies/the-matrix-1999
   ```

2. **API zwraca odpowiedź:**
   ```json
   {
     "id": 1,
     "slug": "the-matrix-1999",
     "title": "The Matrix",
     "descriptions_count": 2
   }
   ```

3. **Postman uruchamia testy:**
   - ✅ Status code = 200? → TAK
   - ✅ Jest pole `id`? → TAK (wartość: 1)
   - ✅ Jest pole `slug`? → TAK (wartość: "the-matrix-1999")
   - ✅ Jest pole `descriptions_count`? → TAK (wartość: 2)

4. **Wszystkie testy przeszły!** ✅

---

## ❓ Pytania?

**P: Gdzie jest endpoint?**
O: W sekcji `"request"` → `"url"` → `"raw"`

**P: Gdzie są testy?**
O: W sekcji `"event"` → `"listen": "test"` → `"script"` → `"exec"`

**P: Co testuje?**
O: Sprawdź kod w `"exec"` - każda linia `pm.test(...)` to jeden test

