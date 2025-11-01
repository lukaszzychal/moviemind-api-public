# 📋 Insomnia vs Postman Collection - Porównanie

**Data:** 2025-11-01

---

## ❓ Pytanie użytkownika

**"Tworzę kolekcję dla Insomii. Wczytałem kolekcję dla Postman, ale może czegoś nie widzę. Postman czy Insomnia nie ma wpływu na flagę `ai_description_generation`, więc chyba oba endpointy będą zachowywać się tak samo przy użyciu. Ewentualnie przykladem w dokumentacji mogą się różnić."**

---

## ✅ Odpowiedź

**Masz rację!** Postman i Insomnia to tylko narzędzia - **nie wpływają na zachowanie API**. Różnica między endpointami (linie 30/50 vs 155/175) to tylko **różne slugi**, nie różne endpointy.

---

## 🔍 Analiza

### **Co jest prawdą:**

1. ✅ **Te same endpointy** - `GET /api/v1/movies/{slug}` i `GET /api/v1/people/{slug}`
2. ✅ **Te same zachowanie** - API sprawdza czy slug istnieje + feature flag
3. ✅ **Różnica to tylko slug**:
   - `the-matrix` → istnieje → `200 OK`
   - `annihilation` → nie istnieje → `202 Accepted` (jeśli flag włączony)

### **Co zostało uproszczone w Insomii:**

W kolekcji Insomii **usunięto duplikaty** (linie 155/175 z Postman) i zastąpiono je **Environment Variables**:

**Postman:**
```json
{
  "name": "Movies - Show (by slug)",
  "url": "{{baseUrl}}/api/v1/movies/the-matrix"  // ← Hardcoded slug
}
{
  "name": "Movies - Show (missing slug => 202 when generation on)",
  "url": "{{baseUrl}}/api/v1/movies/annihilation"  // ← Inny hardcoded slug
}
```

**Insomnia:**
```json
{
  "name": "Movies - Show (by slug)",
  "url": "{{ _.baseUrl }}/api/v1/movies/{{ _.movieSlug }}",  // ← Zmienna
  "description": "Returns 200 OK if exists, 202 Accepted if missing + flag enabled"
}
```

**Plus Environments:**
- **Base Environment**: `movieSlug = "the-matrix"` (istnieje)
- **Testing Environment**: `movieSlug = "annihilation"` (nie istnieje)

---

## 📊 Porównanie struktur

| Aspekt | Postman | Insomnia |
|--------|---------|----------|
| **Endpointy Movies Show** | 2 osobne requesty (30 + 155) | 1 request + 2 environments |
| **Endpointy People Show** | 2 osobne requesty (50 + 175) | 1 request + 2 environments |
| **Organizacja** | Płaska lista | Foldery (Movies, People, etc.) |
| **Zmienne** | `{{baseUrl}}`, `{{jobId}}` | `{{ _.baseUrl }}`, `{{ _.movieSlug }}`, etc. |
| **Duplikaty** | ✅ Tak (dla różnych scenariuszy) | ❌ Nie (używa environments) |
| **Czytelność** | ⚠️ Więcej requestów | ✅ Mniej requestów, lepsze organizacja |

---

## 🎯 Dlaczego uproszczono w Insomii?

### **Problem z Postman:**
- Duplikacja endpointów (ten sam endpoint, różne slugi)
- Trudne utrzymanie (zmiana w jednym = zmiana w dwóch miejscach)
- Myli użytkowników (wygląda jakby to były różne endpointy)

### **Rozwiązanie w Insomii:**
- ✅ Jeden endpoint na typ zasobu
- ✅ Environments dla różnych scenariuszy
- ✅ Opisy wyjaśniające różne odpowiedzi
- ✅ Łatwiejsze utrzymanie

---

## 📝 Różnice w przykładach

### **Postman Collection:**

**Request 1 (linia 30):**
```json
{
  "name": "Movies - Show (by slug)",
  "url": "{{baseUrl}}/api/v1/movies/the-matrix",
  "response": [{
    "code": 200,
    "body": "{...pełne dane filmu...}"
  }]
}
```

**Request 2 (linia 155):**
```json
{
  "name": "Movies - Show (missing slug => 202 when generation on)",
  "url": "{{baseUrl}}/api/v1/movies/annihilation",
  "response": [{
    "code": 202,
    "body": "{\"job_id\": \"...\", \"status\": \"PENDING\"}"
  }]
}
```

---

### **Insomnia Collection:**

**Jeden request:**
```json
{
  "name": "Movies - Show (by slug)",
  "url": "{{ _.baseUrl }}/api/v1/movies/{{ _.movieSlug }}",
  "description": "Get movie details by slug.\n\nReturns:\n- 200 OK if movie exists\n- 202 Accepted if missing and feature flag enabled\n- 404 Not Found if missing and feature flag disabled"
}
```

**Plus 2 environments:**

**Base Environment:**
```json
{
  "movieSlug": "the-matrix"  // ← Istnieje → 200 OK
}
```

**Testing Environment:**
```json
{
  "movieSlug": "annihilation"  // ← Nie istnieje → 202 Accepted (jeśli flag włączony)
}
```

---

## ✅ Podsumowanie

### **Co jest takie samo:**

1. ✅ **Te same endpointy API**
2. ✅ **Te same zachowania** (200 OK, 202 Accepted, 404 Not Found)
3. ✅ **Te same feature flags** (`ai_description_generation`, `ai_bio_generation`)

### **Co jest inne:**

1. ✅ **Organizacja** - Insomnia używa folderów
2. ✅ **Duplikaty** - Postman ma 2 requesty, Insomnia ma 1 + environments
3. ✅ **Zmienne** - Insomnia używa bardziej elastycznego systemu

### **Co jest lepsze w Insomii:**

1. ✅ **Mniej duplikacji** - łatwiejsze utrzymanie
2. ✅ **Lepsza organizacja** - foldery zamiast płaskiej listy
3. ✅ **Elastyczność** - łatwa zmiana slugów przez environments

### **Co jest lepsze w Postman:**

1. ✅ **Więcej przykładów** - 2 requesty pokazują różne scenariusze
2. ✅ **Czytelniejsze** - widać od razu jakie slugi są używane

---

## 🎯 Rekomendacja

**Oba podejścia są poprawne**, ale:

- ✅ **Postman** - lepsze dla **dokumentacji** (więcej przykładów)
- ✅ **Insomnia** - lepsze dla **pracy** (mniej duplikacji, lepsza organizacja)

**Można mieć obie!** Każda ma swoje zastosowanie.

---

**Ostatnia aktualizacja:** 2025-11-01

