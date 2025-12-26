# Struktura kolekcji Postman - Gdzie są informacje o endpointach i testach

## 📍 Gdzie znajdować informacje w pliku kolekcji

Plik `moviemind-api.postman_collection.json` to plik JSON, który zawiera wszystkie informacje o endpointach i testach.

---

## 🔍 Struktura żądania (Request)

### 1. Informacje o endpoincie (URL, metoda HTTP)

Znajdują się w sekcji `request`:

```json
{
  "name": "Get movie by slug",
  "request": {
    "method": "GET",                    // ← Metoda HTTP (GET, POST, PUT, DELETE)
    "url": {
      "raw": "{{baseUrl}}/api/v1/movies/{{movieSlug}}",  // ← Pełny URL
      "host": ["{{baseUrl}}"],
      "path": ["api", "v1", "movies", "{{movieSlug}}"],   // ← Ścieżka endpointa
      "query": [                                         // ← Parametry query (opcjonalne)
        {
          "key": "description_id",
          "value": "{{movieDescriptionId}}"
        }
      ]
    },
    "header": [                                          // ← Headery HTTP
      {
        "key": "Accept",
        "value": "application/json"
      }
    ],
    "body": {                                            // ← Body dla POST/PUT (opcjonalne)
      "mode": "raw",
      "raw": "{\n  \"entity_type\": \"MOVIE\",\n  \"entity_id\": \"the-matrix\"\n}"
    }
  }
}
```

**Gdzie szukać:**
- `request.method` → Metoda HTTP (GET, POST, PUT, DELETE, PATCH)
- `request.url.raw` → Pełny URL endpointa
- `request.url.path` → Ścieżka endpointa (części URL)
- `request.url.query` → Parametry query string
- `request.header` → Headery HTTP
- `request.body` → Body żądania (dla POST/PUT)

---

## 🧪 Informacje o testach

### 2. Testy (co ma być przetestowane)

Znajdują się w sekcji `event[].listen: "test"`:

```json
{
  "name": "Get movie by slug",
  "request": { ... },
  "event": [
    {
      "listen": "test",                    // ← Oznacza, że to są testy
      "script": {
        "type": "text/javascript",         // ← Kod JavaScript
        "exec": [                          // ← Lista linii kodu testów
          "pm.test(\"Status code is 200\", function () {",
          "  pm.response.to.have.status(200);",
          "});",
          "const json = pm.response.json();",
          "pm.test(\"Response contains movie id and slug\", function () {",
          "  pm.expect(json).to.have.property('id');",
          "  pm.expect(json).to.have.property('slug');",
          "});"
        ]
      }
    }
  ]
}
```

**Gdzie szukać:**
- `event[].listen: "test"` → Sekcja z testami
- `event[].script.exec` → Tablica linii kodu JavaScript z testami

---

## 📋 Przykład z Twojego projektu

### Przykład 1: "Get movie by slug"

```json
{
  "name": "Get movie by slug",                    // ← Nazwa żądania
  "request": {
    "method": "GET",                              // ← Metoda HTTP
    "url": {
      "raw": "{{baseUrl}}/api/v1/movies/{{movieSlug}}",  // ← Endpoint
      "path": ["api", "v1", "movies", "{{movieSlug}}"]
    }
  },
  "event": [
    {
      "listen": "test",
      "script": {
        "exec": [
          // Test 1: Status code
          "pm.test(\"Status code is 200\", function () {",
          "  pm.response.to.have.status(200);",
          "});",
          
          // Test 2: Struktura odpowiedzi
          "const json = pm.response.json();",
          "pm.test(\"Response contains movie id and slug\", function () {",
          "  pm.expect(json).to.have.property('id');",      // ← Testuje pole 'id'
          "  pm.expect(json).to.have.property('slug');",   // ← Testuje pole 'slug'
          "});",
          
          // Test 3: Pole descriptions_count
          "pm.test(\"Response exposes descriptions_count\", function () {",
          "  pm.expect(json).to.have.property('descriptions_count');",  // ← Testuje pole 'descriptions_count'
          "});"
        ]
      }
    }
  ]
}
```

**Co testuje:**
1. ✅ Status code = 200
2. ✅ Odpowiedź zawiera pole `id`
3. ✅ Odpowiedź zawiera pole `slug`
4. ✅ Odpowiedź zawiera pole `descriptions_count`

---

### Przykład 2: "Get person by slug"

```json
{
  "name": "Get person by slug",
  "request": {
    "method": "GET",
    "url": {
      "raw": "{{baseUrl}}/api/v1/people/{{personSlug}}",  // ← Endpoint: GET /api/v1/people/{slug}
      "path": ["api", "v1", "people", "{{personSlug}}"]
    }
  },
  "event": [
    {
      "listen": "test",
      "script": {
        "exec": [
          // Test 1: Status code (200 lub 202)
          "pm.test(\"Status code is 200 or 202\", function () {",
          "  pm.expect([200, 202]).to.include(pm.response.code);",
          "});",
          
          // Test 2: Struktura odpowiedzi (tylko gdy status 200)
          "const json = pm.response.json();",
          "if (pm.response.code === 200) {",
          "  pm.test(\"Response contains person id and slug\", function () {",
          "    pm.expect(json).to.have.property('id');",        // ← Testuje pole 'id'
          "    pm.expect(json).to.have.property('slug');",     // ← Testuje pole 'slug'
          "  });",
          "  pm.test(\"Response exposes bios_count\", function () {",
          "    pm.expect(json).to.have.property('bios_count');",  // ← Testuje pole 'bios_count'
          "  });",
          "}"
        ]
      }
    }
  ]
}
```

**Co testuje:**
1. ✅ Status code = 200 lub 202
2. ✅ Gdy status 200: odpowiedź zawiera pole `id`
3. ✅ Gdy status 200: odpowiedź zawiera pole `slug`
4. ✅ Gdy status 200: odpowiedź zawiera pole `bios_count`

---

### Przykład 3: "Generate movie (existing slug -> 202)"

```json
{
  "name": "Generate movie (existing slug -> 202)",
  "request": {
    "method": "POST",                                    // ← Metoda POST
    "url": {
      "raw": "{{baseUrl}}/api/v1/generate",              // ← Endpoint: POST /api/v1/generate
      "path": ["api", "v1", "generate"]
    },
    "header": [
      {
        "key": "Content-Type",
        "value": "application/json"
      }
    ],
    "body": {                                            // ← Body żądania
      "mode": "raw",
      "raw": "{\n  \"entity_type\": \"MOVIE\",\n  \"entity_id\": \"the-matrix-1999\"\n}"
    }
  },
  "event": [
    {
      "listen": "test",
      "script": {
        "exec": [
          // Test 1: Status code
          "pm.test(\"Status code is 202\", function () {",
          "  pm.response.to.have.status(202);",
          "});",
          
          // Test 2: Struktura odpowiedzi
          "const json = pm.response.json();",
          "pm.test(\"Generation is queued\", function () {",
          "  pm.expect(json.status).to.eql('PENDING');",        // ← Testuje pole 'status' = 'PENDING'
          "});",
          "pm.test(\"Existing ID present\", function () {",
          "  pm.expect(json).to.have.property('existing_id');", // ← Testuje pole 'existing_id'
          "});",
          "pm.test(\"Baseline description ID present\", function () {",
          "  pm.expect(json).to.have.property('description_id');", // ← Testuje pole 'description_id'
          "});"
        ]
      }
    }
  ]
}
```

**Co testuje:**
1. ✅ Status code = 202
2. ✅ Odpowiedź zawiera pole `status` = 'PENDING'
3. ✅ Odpowiedź zawiera pole `existing_id`
4. ✅ Odpowiedź zawiera pole `description_id`

---

## 🗺️ Mapa struktury pliku

```
moviemind-api.postman_collection.json
│
├── info                          # Metadane kolekcji
│   ├── name: "MovieMind API"
│   └── version: "1.2.0"
│
├── item[]                        # Lista folderów/żądań
│   │
│   ├── name: "Movies"           # Folder
│   │   └── item[]                # Żądania w folderze
│   │       │
│   │       ├── name: "Get movie by slug"  # ← Nazwa żądania
│   │       │   │
│   │       │   ├── request                # ← INFORMACJE O ENDPOINCIE
│   │       │   │   ├── method: "GET"      # ← Metoda HTTP
│   │       │   │   ├── url                # ← URL endpointa
│   │       │   │   │   ├── raw: "{{baseUrl}}/api/v1/movies/{{movieSlug}}"
│   │       │   │   │   └── path: ["api", "v1", "movies", "{{movieSlug}}"]
│   │       │   │   ├── header: [...]     # ← Headery
│   │       │   │   └── body: {...}       # ← Body (dla POST/PUT)
│   │       │   │
│   │       │   └── event[]                # ← TESTY
│   │       │       └── listen: "test"
│   │       │           └── script
│   │       │               └── exec: [    # ← Kod testów JavaScript
│   │       │                   "pm.test(...)",
│   │       │                   "pm.expect(...)"
│   │       │               ]
│   │       │
│   │       └── name: "List movies"
│   │           └── ... (podobna struktura)
│   │
│   └── name: "People"
│       └── item[]                # Żądania dla People
│
└── variable[]                    # Zmienne kolekcji
    ├── key: "movieSlug"
    └── value: "the-matrix-1999"
```

---

## 🔎 Jak znaleźć informacje o konkretnym endpoincie

### Metoda 1: Przez nazwę żądania

1. Otwórz plik `moviemind-api.postman_collection.json`
2. Wyszukaj nazwę żądania (np. "Get movie by slug")
3. Sprawdź sekcję `request` → informacje o endpoincie
4. Sprawdź sekcję `event[].listen: "test"` → informacje o testach

### Metoda 2: Przez URL

1. Wyszukaj fragment URL (np. "/api/v1/movies")
2. Znajdź sekcję `request.url.raw` lub `request.url.path`
3. Sprawdź sekcję `request` → pełne informacje o endpoincie
4. Sprawdź sekcję `event` → testy

### Metoda 3: W Postman GUI

1. Otwórz Postman
2. Zaimportuj kolekcję
3. Kliknij na żądanie
4. Zobaczysz:
   - **Params** → parametry URL
   - **Headers** → headery
   - **Body** → body żądania
   - **Tests** → kod testów
   - **Pre-request Script** → kod przed żądaniem

---

## 📝 Podsumowanie - Gdzie co jest

| Informacja | Gdzie w pliku JSON |
|------------|-------------------|
| **Metoda HTTP** | `request.method` |
| **URL endpointa** | `request.url.raw` lub `request.url.path` |
| **Parametry query** | `request.url.query[]` |
| **Headery** | `request.header[]` |
| **Body żądania** | `request.body` |
| **Kod testów** | `event[].listen: "test"` → `script.exec[]` |
| **Nazwa żądania** | `name` |

---

## 💡 Przykład praktyczny

Chcesz sprawdzić, co testuje żądanie "Get person by slug"?

1. **Otwórz plik:** `docs/postman/moviemind-api.postman_collection.json`
2. **Wyszukaj:** "Get person by slug" (linia ~253)
3. **Sprawdź endpoint:**
   ```json
   "request": {
     "method": "GET",
     "url": {
       "raw": "{{baseUrl}}/api/v1/people/{{personSlug}}"
     }
   }
   ```
   → Endpoint: `GET /api/v1/people/{slug}`

4. **Sprawdź testy:**
   ```json
   "event": [{
     "listen": "test",
     "script": {
       "exec": [
         "pm.test(\"Status code is 200 or 202\", ...)",
         "pm.test(\"Response contains person id and slug\", ...)",
         "pm.test(\"Response exposes bios_count\", ...)"
       ]
     }
   }]
   ```
   → Testuje: status code, pole `id`, pole `slug`, pole `bios_count`

---

## 🎯 Szybki przewodnik

**Pytanie: "Jaki endpoint?"**
→ Szukaj: `request.url.raw` lub `request.url.path`

**Pytanie: "Jaka metoda HTTP?"**
→ Szukaj: `request.method`

**Pytanie: "Jakie dane testuje?"**
→ Szukaj: `event[].listen: "test"` → `script.exec[]`

**Pytanie: "Jakie parametry?"**
→ Szukaj: `request.url.query[]` lub `request.body`

