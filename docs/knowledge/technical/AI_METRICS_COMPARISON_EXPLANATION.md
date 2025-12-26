# Jak działa porównywanie tokenów JSON vs TOON

> **Dla:** Wszyscy  
> **Cel:** Wyjaśnienie, jak system porównuje zużycie tokenów między formatami

## 🎯 Jak to działa?

### 1. Automatyczne zbieranie danych

**Przy każdym wywołaniu AI:**
- System zapisuje metrykę z informacją o formacie danych (`data_format`: JSON, TOON, CSV)
- Zapisuje zużycie tokenów: `prompt_tokens`, `completion_tokens`, `total_tokens`
- Zapisuje dokładność parsowania: `parsing_successful`

**Przykład:**
```
Wywołanie 1: JSON, 150 tokenów
Wywołanie 2: JSON, 160 tokenów
Wywołanie 3: TOON, 120 tokenów
Wywołanie 4: TOON, 110 tokenów
```

### 2. Agregacja danych

**System grupuje metryki według formatu:**

```sql
SELECT 
    data_format,
    COUNT(*) as total_requests,
    AVG(total_tokens) as avg_tokens,
    SUM(total_tokens) as total_tokens
FROM ai_generation_metrics
GROUP BY data_format
```

**Wynik:**
```
JSON: 2 requests, avg 155 tokens, total 310 tokens
TOON: 2 requests, avg 115 tokens, total 230 tokens
```

### 3. Porównywanie

**Endpoint `/api/v1/admin/ai-metrics/comparison`:**

1. Pobiera statystyki dla JSON i TOON
2. Oblicza oszczędności:
   - **Absolute:** `JSON_total_tokens - TOON_total_tokens` = 310 - 230 = 80 tokenów
   - **Percent:** `(JSON_avg_tokens - TOON_avg_tokens) / JSON_avg_tokens * 100` = (155 - 115) / 155 * 100 = 25.8%
3. Porównuje dokładność parsowania
4. Zwraca wyniki

**Przykładowa odpowiedź:**
```json
{
  "data": {
    "token_savings": {
      "absolute": 80,
      "percent": 25.8
    },
    "accuracy": {
      "json": 98.0,
      "toon": 96.0,
      "difference": -2.0
    },
    "avg_tokens": {
      "json": 155,
      "toon": 115,
      "savings": 40
    }
  }
}
```

## ❓ Dlaczego teraz nie działa?

### Obecna sytuacja:
- ✅ Wszystkie metryki są w formacie **JSON** (TOON nie jest jeszcze zaimplementowany)
- ❌ Brak danych TOON → nie można porównać
- ❌ Endpoint `/comparison` zwraca: `{"error": "Insufficient data for comparison"}`

### Co się stanie, gdy TOON będzie zaimplementowany:

1. **Automatyczne zbieranie:**
   - Gdy `OpenAiClient` użyje formatu TOON, metryka będzie zapisana z `data_format: 'TOON'`
   - System automatycznie zacznie zbierać dane dla obu formatów

2. **Automatyczne porównywanie:**
   - Endpoint `/comparison` zacznie zwracać porównanie
   - Raporty okresowe będą zawierać porównanie JSON vs TOON

3. **Obliczanie oszczędności:**
   - System porówna średnie tokeny: `(JSON_avg - TOON_avg) / JSON_avg * 100`
   - Zapisze w `token_savings_vs_json` w metrykach TOON

## 📊 Przykład działania (gdy będzie TOON)

### Scenariusz:
- 10 wywołań w JSON: średnio 150 tokenów
- 10 wywołań w TOON: średnio 100 tokenów

### Wynik porównania:
```json
{
  "token_savings": {
    "absolute": 500,  // (150-100) * 10
    "percent": 33.3  // (150-100)/150 * 100
  },
  "avg_tokens": {
    "json": 150,
    "toon": 100,
    "savings": 50
  }
}
```

**Wniosek:** TOON oszczędza 33.3% tokenów vs JSON!

## 🔍 Gdzie sprawdzić dane?

### 1. Baza danych
```sql
SELECT data_format, COUNT(*), AVG(total_tokens)
FROM ai_generation_metrics
GROUP BY data_format;
```

### 2. Endpointy API
```bash
# Statystyki per format
GET /api/v1/admin/ai-metrics/token-usage

# Porównanie (wymaga danych dla obu formatów)
GET /api/v1/admin/ai-metrics/comparison
```

### 3. Raporty
- Raporty okresowe zawierają sekcję `comparison`
- Zapisane w `storage/app/reports/ai-metrics/`

## ⚠️ Ważne

- **Porównywanie działa tylko, gdy są dane dla obu formatów**
- **Obecnie tylko JSON** → brak porównania
- **Gdy TOON będzie zaimplementowany** → automatyczne porównywanie

---

**Ostatnia aktualizacja:** 2025-12-26

