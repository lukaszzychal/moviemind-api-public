# Testowanie Pełnego Flow z AI_SERVICE=real

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Manualne testowanie pełnego flow generowania z AI_SERVICE=real i weryfikacja nowych mechanizmów walidacji  
> **Kategoria:** reference

## 🎯 Cel

Przetestowanie pełnego flow generowania filmów i osób z `AI_SERVICE=real`, weryfikacja:
- Działania nowych promptów z weryfikacją istnienia
- Obsługi błędów "not found" z AI
- Walidacji danych przez `AiDataValidator` (gdy `hallucination_guard` jest aktywny)
- Poprawnego działania schematów JSON z `oneOf` i `required` fields

## 📋 Scenariusze Testowe

### Test 1: Istniejący Film (The Matrix 1999)

**Cel:** Sprawdzenie czy istniejący film jest poprawnie generowany

```bash
# 1. Wywołaj endpoint
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "Accept: application/json" | jq

# Oczekiwany wynik:
# - 202 Accepted (jeśli nie ma w bazie) lub 200 OK (jeśli już jest)
# - job_id zwrócony
# - status: PENDING

# 2. Sprawdź status joba
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq

# Oczekiwany wynik:
# - status: DONE (po przetworzeniu)
# - Dane filmu: title, release_year, director, description, genres
# - Wszystkie wymagane pola obecne (title, release_year)
```

**Weryfikacja:**
- ✅ AI zwraca kompletne dane (nie tylko `{"error": "Movie not found"}`)
- ✅ Wszystkie wymagane pola są obecne (`title`, `release_year`)
- ✅ Opcjonalne pola mogą być obecne (`director`, `description`, `genres`)
- ✅ Dane są zgodne z slugiem (walidacja przez `hallucination_guard`)

---

### Test 2: Nieistniejący Film

**Cel:** Sprawdzenie czy nieistniejący film zwraca błąd "not found"

```bash
# 1. Wywołaj endpoint z nieistniejącym filmem
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-xyz-9999" \
  -H "Accept: application/json" | jq

# Oczekiwany wynik:
# - 202 Accepted (job jest kolejkowany)
# - job_id zwrócony

# 2. Sprawdź status joba (poczekaj na przetworzenie)
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq

# Oczekiwany wynik:
# - status: FAILED
# - error zawiera "Movie not found" lub podobny komunikat
```

**Weryfikacja:**
- ✅ AI zwraca `{"error": "Movie not found"}` zgodnie z nowymi promptami
- ✅ Job kończy się z błędem (nie zapisuje fałszywych danych)
- ✅ W logach pojawia się komunikat "Movie not found by AI"

---

### Test 3: Istniejąca Osoba (Keanu Reeves)

**Cel:** Sprawdzenie czy istniejąca osoba jest poprawnie generowana

```bash
# 1. Wywołaj endpoint
curl -X GET "http://localhost:8000/api/v1/people/keanu-reeves" \
  -H "Accept: application/json" | jq

# 2. Sprawdź status joba
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq
```

**Weryfikacja:**
- ✅ AI zwraca kompletne dane osoby
- ✅ Wszystkie wymagane pola są obecne (`name`, `birth_date`)
- ✅ Opcjonalne pola mogą być obecne (`birthplace`, `biography`)

---

### Test 4: Nieistniejąca Osoba

**Cel:** Sprawdzenie czy nieistniejąca osoba zwraca błąd "not found"

```bash
# 1. Wywołaj endpoint z nieistniejącą osobą
curl -X GET "http://localhost:8000/api/v1/people/non-existent-person-xyz-9999" \
  -H "Accept: application/json" | jq

# 2. Sprawdź status joba
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq
```

**Weryfikacja:**
- ✅ AI zwraca `{"error": "Person not found"}`
- ✅ Job kończy się z błędem
- ✅ W logach pojawia się komunikat "Person not found by AI"

---

### Test 5: Weryfikacja Walidacji Danych (hallucination_guard)

**Cel:** Sprawdzenie czy `AiDataValidator` działa poprawnie

**Uwaga:** Ten test wymaga aktywnego feature flag `hallucination_guard`

```bash
# 1. Sprawdź czy feature flag jest aktywny
curl -X GET "http://localhost:8000/api/v1/admin/debug/config" \
  -H "Accept: application/json" | jq '.features.hallucination_guard'

# 2. Wygeneruj film z slugiem, który może zwrócić niezgodne dane
# (np. slug z rokiem, ale AI zwraca inny rok)
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "test-movie-1999",
    "locale": "en-US"
  }' | jq

# 3. Sprawdź logi aplikacji
docker compose logs horizon | grep -i "validation\|hallucination" | tail -20
```

**Weryfikacja:**
- ✅ Jeśli AI zwróci dane niezgodne z slugiem (np. inny rok), walidacja je odrzuca
- ✅ W logach pojawia się "AI data validation failed"
- ✅ Job kończy się z błędem walidacji

---

## 🔍 Sprawdzanie Logów

### Logi Horizon (przetwarzanie jobów)

```bash
# Podgląd logów na żywo
docker compose logs -f horizon

# Ostatnie 50 linii
docker compose logs horizon | tail -50

# Filtrowanie po błędach
docker compose logs horizon | grep -i "error\|failed\|not found" | tail -20
```

### Logi Aplikacji (Laravel)

```bash
# Ostatnie 100 linii
tail -100 api/storage/logs/laravel.log

# Filtrowanie po AI responses
tail -100 api/storage/logs/laravel.log | grep -i "ai returned\|validation\|hallucination"

# Filtrowanie po "not found"
tail -100 api/storage/logs/laravel.log | grep -i "not found"
```

---

## ✅ Checklist Testowania

### Przed Testowaniem

- [ ] Docker uruchomiony (`docker compose up -d`)
- [ ] Horizon działa (`docker ps | grep horizon`)
- [ ] `AI_SERVICE=real` w konfiguracji
- [ ] `OPENAI_API_KEY` ustawiony
- [ ] Feature flags aktywne:
  - [ ] `ai_description_generation`
  - [ ] `ai_bio_generation`
  - [ ] `hallucination_guard` (dla testów walidacji)

### Testy Filmów

- [ ] Test 1: Istniejący film - dane są generowane poprawnie
- [ ] Test 2: Nieistniejący film - zwraca błąd "not found"
- [ ] Weryfikacja: Wszystkie wymagane pola są obecne
- [ ] Weryfikacja: Opcjonalne pola mogą być puste/null

### Testy Osób

- [ ] Test 3: Istniejąca osoba - dane są generowane poprawnie
- [ ] Test 4: Nieistniejąca osoba - zwraca błąd "not found"
- [ ] Weryfikacja: Wszystkie wymagane pola są obecne

### Testy Walidacji

- [ ] Test 5: Walidacja danych działa (gdy `hallucination_guard` aktywny)
- [ ] Weryfikacja: Niezgodne dane są odrzucane
- [ ] Weryfikacja: Logi zawierają informacje o walidacji

### Weryfikacja Logów

- [ ] Logi zawierają komunikaty "AI returned error response"
- [ ] Logi zawierają komunikaty "Movie/Person not found by AI"
- [ ] Logi zawierają komunikaty "AI data validation failed" (gdy walidacja nie przechodzi)
- [ ] Brak błędów związanych z JSON Schema validation

---

## 🐛 Troubleshooting

### Problem: Job nie jest przetwarzany

**Rozwiązanie:**
```bash
# Sprawdź czy Horizon działa
docker compose logs horizon | tail -20

# Restart Horizon
docker compose restart horizon
```

### Problem: Błąd "OpenAI API key not configured"

**Rozwiązanie:**
```bash
# Sprawdź konfigurację
docker compose exec php php artisan tinker --execute="echo config('services.openai.api_key') ? 'SET' : 'NOT SET';"

# Ustaw w .env
echo "OPENAI_API_KEY=sk-..." >> api/.env
docker compose restart php horizon
```

### Problem: Błąd JSON Schema validation

**Objawy:**
- OpenAI API zwraca błąd 400
- Komunikat o nieprawidłowym schemacie JSON

**Rozwiązanie:**
- Sprawdź czy schemat JSON jest poprawny (używa `oneOf`, `required` fields)
- Sprawdź logi aplikacji dla szczegółów błędu

---

## 📊 Oczekiwane Wyniki

### Sukces (istniejący film/osoba)

```json
{
  "status": "DONE",
  "movie": {
    "title": "The Matrix",
    "release_year": 1999,
    "director": "Lana Wachowski, Lilly Wachowski",
    "description": "...",
    "genres": ["Action", "Sci-Fi"]
  }
}
```

### Błąd (nieistniejący film/osoba)

```json
{
  "status": "FAILED",
  "error": "Movie not found: non-existent-movie-xyz-9999"
}
```

### Błąd Walidacji (niezgodne dane)

```json
{
  "status": "FAILED",
  "error": "AI data validation failed: Title 'Inception' does not match slug 'the-matrix-1999' (similarity: 0.15)"
}
```

---

**Ostatnia aktualizacja:** 2025-12-01

