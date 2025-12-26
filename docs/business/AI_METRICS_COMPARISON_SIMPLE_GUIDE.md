# Prosty przewodnik: TOON vs JSON - co lepsze?

> **Dla:** Wszyscy (uproszczony przewodnik)  
> **Cel:** Szybka odpowiedź na pytanie "co lepsze?"

## 🎯 Krótka odpowiedź

**Sprawdź endpoint:**
```bash
GET /api/v1/admin/ai-metrics/comparison
```

**Odpowiedź zawiera:**
```json
{
  "data": {
    "token_savings": {
      "percent": 33.3
    },
    "accuracy": {
      "json": 98.0,
      "toon": 96.0,
      "difference": -2.0
    }
  },
  "recommendation": {
    "decision": "USE_TOON",
    "message": "TOON is recommended..."
  }
}
```

## 📊 Jak interpretować rekomendację?

### ✅ `USE_TOON` - Użyj TOON
**Znaczy:** TOON jest lepszy
- Oszczędza **≥20% tokenów**
- Dokładność **≥95%**
- Różnica w dokładności **≤3%**
- Mało błędów (**≤5%**)

**Działanie:** Przejdź na TOON

### ⚠️ `CONSIDER_TOON` - Rozważ TOON
**Znaczy:** TOON może być lepszy, ale wymaga poprawek
- Oszczędza **≥15% tokenów**
- Dokładność **≥92%**
- Różnica w dokładności **≤5%**
- Średnio błędów (**≤10%**)

**Działanie:** Popraw TOON (prompty, schematy) i przetestuj ponownie

### ❌ `KEEP_JSON` - Zostań przy JSON
**Znaczy:** JSON jest lepszy
- Oszczędności **<15%** LUB
- Dokładność **<92%** LUB
- Różnica w dokładności **>5%** LUB
- Dużo błędów (**>10%**)

**Działanie:** Zostań przy JSON

### 📊 `INSUFFICIENT_DATA` - Brak danych
**Znaczy:** Nie można porównać
- Brak danych TOON (wszystko jest JSON)
- Potrzebujesz danych dla **obu formatów**

**Działanie:** Zaimplementuj TOON i zbierz dane

## 🔢 Progi decyzyjne (uproszczone)

| Oszczędności tokenów | Dokładność TOON | Różnica dokładności | Decyzja |
|---------------------|-----------------|---------------------|---------|
| ≥20% | ≥95% | ≤3% | ✅ **USE_TOON** |
| ≥15% | ≥92% | ≤5% | ⚠️ **CONSIDER_TOON** |
| <15% LUB | <92% LUB | >5% | ❌ **KEEP_JSON** |

## 💡 Przykłady

### Przykład 1: TOON lepszy
```json
{
  "token_savings": { "percent": 30.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 97.0,
    "difference": -1.0
  },
  "recommendation": {
    "decision": "USE_TOON"
  }
}
```
**Wniosek:** ✅ Użyj TOON (oszczędza 30% tokenów, dokładność prawie taka sama)

### Przykład 2: JSON lepszy
```json
{
  "token_savings": { "percent": 5.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 90.0,
    "difference": -8.0
  },
  "recommendation": {
    "decision": "KEEP_JSON"
  }
}
```
**Wniosek:** ❌ Zostań przy JSON (małe oszczędności, znacznie niższa dokładność)

### Przykład 3: Trzeba poprawić TOON
```json
{
  "token_savings": { "percent": 25.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 93.0,
    "difference": -5.0
  },
  "recommendation": {
    "decision": "CONSIDER_TOON",
    "suggestions": [
      "Improve TOON prompts to increase parsing accuracy"
    ]
  }
}
```
**Wniosek:** ⚠️ Popraw TOON i przetestuj ponownie

## 🎯 Co sprawdzić?

### 1. Oszczędności tokenów
- **≥20%** = bardzo dobre
- **15-20%** = dobre
- **<15%** = słabe

### 2. Dokładność TOON
- **≥95%** = bardzo dobra
- **92-95%** = dobra
- **<92%** = słaba

### 3. Różnica w dokładności
- **≤3%** = minimalna różnica (OK)
- **3-5%** = średnia różnica (do poprawy)
- **>5%** = duża różnica (problem)

## 📞 Gdzie sprawdzić?

### Endpoint API
```bash
GET /api/v1/admin/ai-metrics/comparison
```

### Raporty okresowe
- Codziennie: `storage/app/reports/ai-metrics/ai-metrics-daily-*.json`
- Sekcja `recommendation` w raporcie

---

**Ostatnia aktualizacja:** 2025-12-26

