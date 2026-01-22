# 🧪 Instrukcja Testów Manualnych - TV Series & TV Shows

**Cel:** Weryfikacja poprawności działania nowych endpointów dla Seriali i Programów TV.
**Wymagania:** Uruchomione środowisko Docker (`docker compose up -d`).

---

## 1. 📺 TV Series (Seriale)

### A. Lista i Wyszukiwanie
Sprawdź, czy możesz pobrać listę seriali i wyszukać konkretny tytuł.

```bash
# 1. Lista wszystkich seriali
curl -X GET "http://localhost:8000/api/v1/tv-series" \
  -H "Accept: application/json" | jq

# 2. Wyszukiwanie (np. "Breaking Bad")
curl -X GET "http://localhost:8000/api/v1/tv-series/search?q=Breaking+Bad" \
  -H "Accept: application/json" | jq
```

### B. Szczegóły i Relacje
Pobierz szczegóły serialu i sprawdź powiązane tytuły (np. spin-offy).

```bash
# 1. Szczegóły serialu (użyj sluga z wyszukiwania, np. breaking-bad-2008)
curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
  -H "Accept: application/json" | jq

# 2. Powiązane seriale (Related)
curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/related" \
  -H "Accept: application/json" | jq
```

### C. Porównywanie (Compare)
Porównaj dwa seriale, aby zobaczyć wspólne cechy (gatunki, obsada).

```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug1=breaking-bad-2008&slug2=better-call-saul-2015" \
  -H "Accept: application/json" | jq
```

### D. Odświeżanie danych (Refresh)
Wymuś pobranie najświeższych danych z TMDb.

```bash
curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/refresh" \
  -H "Accept: application/json" | jq
```

---

## 2. 🎤 TV Shows (Programy TV)

### A. Lista i Wyszukiwanie

```bash
# 1. Lista programów
curl -X GET "http://localhost:8000/api/v1/tv-shows" \
  -H "Accept: application/json" | jq

# 2. Wyszukiwanie (np. "Top Gear")
curl -X GET "http://localhost:8000/api/v1/tv-shows/search?q=Top+Gear" \
  -H "Accept: application/json" | jq
```

### B. Szczegóły

```bash
# Szczegóły programu
curl -X GET "http://localhost:8000/api/v1/tv-shows/top-gear-2002" \
  -H "Accept: application/json" | jq
```

---

## 3. 🤖 Generowanie Opisów (AI)

Zleć wygenerowanie opisu przez AI dla serialu.

```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "TV_SERIES",
    "slug": "breaking-bad-2008",
    "locale": "pl-PL",
    "context_tag": "modern"
  }' | jq
```

*Zapisz zwrócony `job_id` i sprawdź status:*

```bash
# Podmień {job_id} na otrzymany ID
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq
```

---

## 4. 🚩 Zgłaszanie Błędów (Reports)

Zgłoś błąd w opisie (np. niepoprawna data).

```bash
curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "factual_error",
    "message": "Niepoprawny rok premiery w opisie",
    "suggested_fix": "Zmień na 2008"
  }' | jq
```

---

## ✅ Oczekiwane Rezultaty

1. **Status 200 OK** dla zapytań GET (listy, szczegóły, search).
2. **Status 202 Accepted** dla zlecenia generowania AI.
3. **Status 201 Created** dla zgłoszenia raportu.
4. **Dane JSON** powinny zawierać poprawne pola (tytuł, data premiery, opis).
5. **Brak błędów 500** w logach (`docker compose logs -f php`).
