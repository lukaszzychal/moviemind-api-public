# 🔧 Konfiguracja Insomnia dla MovieMind API

## ❌ Problem: Dostajesz HTML zamiast JSON

Jeśli Insomnia zwraca stronę HTML (Laravel welcome page) zamiast JSON, to prawdopodobnie:

1. **Brakuje nagłówka `Accept: application/json`**
2. **Błędny URL** (brak `/api/v1/`)

---

## ✅ Rozwiązanie

### **1. Poprawny URL**

```
POST http://localhost:8000/api/v1/generate
```

**Ważne:** Musi zawierać `/api/v1/`!

---

### **2. Wymagane nagłówki**

W Insomnii dodaj te nagłówki:

| Header | Value |
|--------|-------|
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |

**Dlaczego `Accept: application/json`?**
- Laravel sprawdza ten nagłówek aby zwrócić JSON zamiast HTML
- Bez tego nagłówka Laravel zwraca domyślną stronę welcome

---

### **3. Body Request**

```json
{
  "entity_type": "MOVIE",
  "entity_id": "test-movie-slug"
}
```

---

## 📋 Konfiguracja w Insomnii

### **Krok 1: Utwórz request**

1. Kliknij **"New Request"**
2. Nazwa: `POST Generate - MOVIE`
3. Method: `POST`
4. URL: `http://localhost:8000/api/v1/generate`

### **Krok 2: Dodaj nagłówki**

Kliknij zakładkę **"Headers"** i dodaj:

```
Content-Type: application/json
Accept: application/json
```

### **Krok 3: Dodaj Body**

Kliknij zakładkę **"Body"**:
1. Wybierz **"JSON"**
2. Wklej:
```json
{
  "entity_type": "MOVIE",
  "entity_id": "test-movie-slug"
}
```

### **Krok 4: Wyślij request**

Kliknij **"Send"**

---

## ✅ Oczekiwana odpowiedź

**Status:** `202 Accepted`

**Body:**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "test-movie-slug"
}
```

---

## 🔍 Troubleshooting

### **Problem: Dostaję HTML zamiast JSON**

**Rozwiązanie:**
1. ✅ Sprawdź czy masz nagłówek `Accept: application/json`
2. ✅ Sprawdź czy URL zawiera `/api/v1/`
3. ✅ Sprawdź czy metoda to `POST`

---

### **Problem: 404 Not Found**

**Rozwiązanie:**
1. ✅ URL: `http://localhost:8000/api/v1/generate` (z `/api/v1/`)
2. ✅ Sprawdź czy Docker działa: `docker-compose ps`
3. ✅ Sprawdź czy route istnieje: `php artisan route:list`

---

### **Problem: 403 Forbidden**

**Rozwiązanie:**
1. ✅ Włącz flagę: `POST /api/v1/admin/flags/ai_description_generation` z `{"state": "on"}`
2. ✅ Lub przez Tinker: `Feature::activate('ai_description_generation')`

---

## 📝 Przykładowe requesty

### **1. Generowanie filmu:**
```
POST http://localhost:8000/api/v1/generate
Headers:
  Content-Type: application/json
  Accept: application/json
Body:
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"
}
```

### **2. Generowanie osoby:**
```
POST http://localhost:8000/api/v1/generate
Headers:
  Content-Type: application/json
  Accept: application/json
Body:
{
  "entity_type": "PERSON",
  "entity_id": "keanu-reeves"
}
```

### **3. Sprawdzenie statusu joba:**
```
GET http://localhost:8000/api/v1/jobs/{job_id}
Headers:
  Accept: application/json
```

---

## 🎯 Szybki test w Terminalu

Porównaj z Insomnią:

```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"entity_type": "MOVIE", "entity_id": "test"}'
```

**Oczekiwana odpowiedź:**
```json
{
  "job_id": "uuid-here",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "test"
}
```

---

**Ostatnia aktualizacja:** 2025-11-01

