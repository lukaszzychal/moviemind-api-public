# 📋 Insomnia Collection - MovieMind API

**Format:** Insomnia v4 Export Format  
**Wersja:** 1.0.0  
**Data:** 2025-11-01

---

## 📦 Import kolekcji

1. Otwórz Insomnia
2. Kliknij `Create` → `Import/Export` → `Import Data`
3. Wybierz plik: `docs/insomnia/moviemind-api-insomnia.json`
4. Kliknij `Import`

---

## 🗂️ Struktura kolekcji

### **📁 Movies**
- `GET Movies - List` - Lista filmów z opcjonalnym wyszukiwaniem
- `GET Movies - Show (by slug)` - Szczegóły filmu po slug

### **📁 People**
- `GET People - Show (by slug)` - Szczegóły osoby po slug

### **📁 Generation**
- `POST Generate - MOVIE` - Kolejkuj generację dla filmu
- `POST Generate - PERSON` - Kolejkuj generację dla osoby

### **📁 Jobs**
- `GET Jobs - Show` - Status jobu generacji

### **📁 Admin**
- `GET Flags - List` - Lista wszystkich flag
- `POST Flags - Toggle ai_description_generation` - Włącz/wyłącz generację opisów
- `POST Flags - Toggle ai_bio_generation` - Włącz/wyłącz generację biografii
- `GET Flags - Usage` - Statystyki użycia flag

---

## 🔧 Zmienne środowiskowe (Environments)

### **Base Environment** (domyślny)
```json
{
  "baseUrl": "http://localhost:8000",
  "movieSlug": "the-matrix",
  "personSlug": "christopher-nolan",
  "jobId": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d"
}
```

### **Testing Environment** (dla testowania automatycznej generacji)
```json
{
  "baseUrl": "http://localhost:8000",
  "movieSlug": "annihilation",      // ← Slug który nie istnieje (zwróci 202)
  "personSlug": "john-doe",         // ← Slug który nie istnieje (zwróci 202)
  "jobId": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d"
}
```

**Jak używać:**
1. Wybierz environment w prawym górnym rogu Insomnia
2. **Base Environment** - dla filmów/osób które istnieją (200 OK)
3. **Testing Environment** - dla filmów/osób które nie istnieją (202 Accepted)

---

## 📝 Uwagi

### **Endpointy Movies/People Show - różne scenariusze:**

**Te same endpointy, różne zachowanie w zależności od:**
1. ✅ **Czy slug istnieje w bazie**
2. ✅ **Czy feature flag jest włączony**

**Przykłady:**

| Slug | Status w bazie | Feature flag | HTTP Status | Response |
|------|----------------|--------------|-------------|----------|
| `the-matrix` | ✅ Istnieje | - | `200 OK` | Pełne dane filmu |
| `annihilation` | ❌ Nie istnieje | ✅ Włączony | `202 Accepted` | `job_id`, `status` |
| `annihilation` | ❌ Nie istnieje | ❌ Wyłączony | `404 Not Found` | `error: "Movie not found"` |

**W Insomii:**
- Użyj **Base Environment** dla slugów które istnieją
- Użyj **Testing Environment** dla slugów które nie istnieją (i sprawdź czy feature flag jest włączony)

---

## 🎯 Różnice vs Postman Collection

### **Co zostało uproszczone:**

1. ✅ **Usunięto duplikaty** - endpointy 155/175 z Postman są zastąpione przez **Testing Environment**
2. ✅ **Organizacja w foldery** - łatwiejsza nawigacja
3. ✅ **Environment variables** - łatwa zmiana między scenariuszami

### **Co zostało zachowane:**

1. ✅ Wszystkie endpointy z Postman
2. ✅ Przykładowe request body
3. ✅ Opisy endpointów

---

## 🚀 Szybki start

### **1. Import kolekcji**
```
Insomnia → Import → Wybierz moviemind-api-insomnia.json
```

### **2. Ustaw environment**
```
Prawy górny róg → Wybierz "Base Environment" lub "Testing Environment"
```

### **3. Testuj endpointy**
```
Movies → Movies - List → Send
```

---

## ⚙️ Konfiguracja

### **Zmiana baseUrl:**

1. Kliknij ikonę środowiska (prawy górny róg)
2. Wybierz environment
3. Edytuj `baseUrl`:
   - Local: `http://localhost:8000`
   - Staging: `https://staging-api.moviemind.com`
   - Production: `https://api.moviemind.com`

### **Zmiana slugów:**

1. Wybierz environment
2. Edytuj `movieSlug` lub `personSlug`
3. Wszystkie requesty automatycznie użyją nowych wartości

---

## 📚 Dokumentacja API

Pełna dokumentacja API:
- 📄 OpenAPI: `docs/openapi.yaml`
- 📋 Postman Collection: `docs/postman/moviemind-api.postman_collection.json`
- 📖 Szczegóły: `docs/POSTMAN_ENDPOINTS_DIFFERENCES.md`

---

**Ostatnia aktualizacja:** 2025-11-01

