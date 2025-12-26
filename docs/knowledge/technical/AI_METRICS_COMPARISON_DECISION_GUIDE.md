# Przewodnik: Jak porównać TOON vs JSON i podjąć decyzję

> **Dla:** Product Owners, Managerowie, Architekci  
> **Cel:** Jak interpretować wyniki porównania i zdecydować, który format jest lepszy

## 📊 Metryki do porównania

### 1. Zużycie tokenów (Token Usage)

**Co sprawdzać:**
- **Średnie tokeny** (`avg_tokens`) - ile tokenów zużywa każdy format
- **Oszczędności** (`token_savings`) - ile tokenów oszczędza TOON vs JSON

**Jak interpretować:**
```json
{
  "avg_tokens": {
    "json": 150,
    "toon": 100,
    "savings": 50
  },
  "token_savings": {
    "absolute": 500,
    "percent": 33.3
  }
}
```

**Wniosek:**
- ✅ TOON oszczędza **33.3% tokenów** (50 tokenów na request)
- ✅ Przy 1000 requestów = oszczędność **50,000 tokenów**
- ✅ **Niższe koszty** = mniej tokenów = mniej płacisz OpenAI

### 2. Dokładność parsowania (Parsing Accuracy)

**Co sprawdzać:**
- **Accuracy percent** - ile % requestów zostało poprawnie sparsowanych
- **Difference** - różnica w dokładności między formatami

**Jak interpretować:**
```json
{
  "accuracy": {
    "json": 98.0,
    "toon": 96.0,
    "difference": -2.0
  }
}
```

**Wniosek:**
- ⚠️ TOON ma **2% niższą dokładność** niż JSON
- ⚠️ 96% to nadal **bardzo dobra dokładność**
- ⚠️ **Kompromis:** Oszczędności tokenów vs dokładność

### 3. Błędy (Error Statistics)

**Co sprawdzać:**
- **Error count** - ile błędów dla każdego formatu
- **Error rate** - % błędnych requestów

**Jak interpretować:**
```json
{
  "data": [
    {
      "data_format": "JSON",
      "error_count": 2,
      "affected_entity_types": 1
    },
    {
      "data_format": "TOON",
      "error_count": 5,
      "affected_entity_types": 2
    }
  ]
}
```

**Wniosek:**
- ⚠️ TOON ma **więcej błędów** (5 vs 2)
- ⚠️ Może wymagać **poprawy promptów** lub **schematów**

## 🎯 Jak podjąć decyzję?

### Scenariusz 1: TOON oszczędza tokeny + wysoka dokładność

**Przykład:**
```json
{
  "token_savings": { "percent": 30.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 97.5,
    "difference": -0.5
  }
}
```

**Decyzja:** ✅ **Użyj TOON**
- Oszczędności tokenów są **znaczące** (>20%)
- Dokładność jest **porównywalna** (różnica <2%)
- **Korzyści przewyższają ryzyko**

### Scenariusz 2: TOON oszczędza tokeny, ale niska dokładność

**Przykład:**
```json
{
  "token_savings": { "percent": 25.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 90.0,
    "difference": -8.0
  }
}
```

**Decyzja:** ⚠️ **Opcjonalnie TOON z poprawkami**
- Oszczędności są **znaczące** (>20%)
- Dokładność jest **znacznie niższa** (różnica >5%)
- **Działania:**
  1. Sprawdź błędy parsowania TOON
  2. Popraw prompty/schematy dla TOON
  3. Przetestuj ponownie
  4. Jeśli dokładność się poprawi → użyj TOON

### Scenariusz 3: TOON nie oszczędza tokenów

**Przykład:**
```json
{
  "token_savings": { "percent": -5.0 },
  "accuracy": {
    "json": 98.0,
    "toon": 95.0,
    "difference": -3.0
  }
}
```

**Decyzja:** ❌ **Pozostań przy JSON**
- TOON **nie oszczędza** tokenów (może nawet zużywa więcej)
- Dokładność jest **niższa**
- **Brak korzyści** z przejścia na TOON

### Scenariusz 4: TOON oszczędza tokeny, ale dużo błędów

**Przykład:**
```json
{
  "token_savings": { "percent": 30.0 },
  "error_statistics": {
    "json": { "error_count": 2 },
    "toon": { "error_count": 20 }
  }
}
```

**Decyzja:** ⚠️ **Nie używaj TOON (na razie)**
- Oszczędności są **znaczące**, ale:
- **Zbyt dużo błędów** (10x więcej niż JSON)
- **Działania:**
  1. Zidentyfikuj przyczyny błędów TOON
  2. Popraw implementację TOON
  3. Przetestuj ponownie
  4. Jeśli błędy spadną → rozważ TOON

## 📈 Progi decyzyjne (rekomendowane)

### ✅ Użyj TOON, jeśli:
- **Oszczędności tokenów ≥ 20%** I
- **Dokładność ≥ 95%** I
- **Różnica w dokładności ≤ 3%** I
- **Error rate ≤ 5%**

### ⚠️ Rozważ TOON, jeśli:
- **Oszczędności tokenów ≥ 15%** I
- **Dokładność ≥ 92%** I
- **Różnica w dokładności ≤ 5%**
- **Wymaga poprawy błędów**

### ❌ Nie używaj TOON, jeśli:
- **Oszczędności tokenów < 10%** LUB
- **Dokładność < 90%** LUB
- **Różnica w dokładności > 5%** LUB
- **Error rate > 10%**

## 🔍 Jak sprawdzić wyniki?

### 1. Endpoint porównania

```bash
GET /api/v1/admin/ai-metrics/comparison
```

**Odpowiedź:**
```json
{
  "data": {
    "token_savings": {
      "absolute": 500,
      "percent": 33.3
    },
    "accuracy": {
      "json": 98.0,
      "toon": 96.0,
      "difference": -2.0
    },
    "avg_tokens": {
      "json": 150,
      "toon": 100,
      "savings": 50
    }
  }
}
```

### 2. Analiza wyników

**Krok 1: Sprawdź oszczędności tokenów**
- Jeśli `token_savings.percent ≥ 20%` → ✅ Dobra oszczędność
- Jeśli `token_savings.percent < 20%` → ⚠️ Niska oszczędność

**Krok 2: Sprawdź dokładność**
- Jeśli `accuracy.toon ≥ 95%` → ✅ Wysoka dokładność
- Jeśli `accuracy.toon < 95%` → ⚠️ Niska dokładność
- Jeśli `accuracy.difference > 3%` → ⚠️ Znaczna różnica

**Krok 3: Sprawdź błędy**
```bash
GET /api/v1/admin/ai-metrics/errors
```
- Jeśli error rate TOON > 2x JSON → ⚠️ Zbyt dużo błędów

**Krok 4: Podejmij decyzję**
- Użyj progi decyzyjne powyżej

## 💡 Przykład praktyczny

### Dane z produkcji (hipotetyczne):

```json
{
  "token_savings": {
    "percent": 28.5
  },
  "accuracy": {
    "json": 98.2,
    "toon": 96.8,
    "difference": -1.4
  },
  "avg_tokens": {
    "json": 145,
    "toon": 104,
    "savings": 41
  }
}
```

**Analiza:**
1. ✅ Oszczędności: **28.5%** (znaczące, >20%)
2. ✅ Dokładność TOON: **96.8%** (wysoka, >95%)
3. ✅ Różnica: **-1.4%** (mała, <3%)
4. ✅ Error rate: sprawdź `/errors` endpoint

**Decyzja:** ✅ **Użyj TOON**
- Wszystkie kryteria spełnione
- Oszczędności są znaczące
- Dokładność jest porównywalna

## 📊 Raporty okresowe

Raporty zawierają sekcję `comparison` z automatyczną analizą:

```json
{
  "comparison": {
    "token_savings": {...},
    "accuracy": {...},
    "recommendation": "USE_TOON" // lub "KEEP_JSON", "CONSIDER_TOON"
  }
}
```

## ⚠️ Ważne uwagi

1. **Minimalna próbka:** Potrzebujesz **co najmniej 50-100 requestów** dla każdego formatu, żeby wyniki były wiarygodne
2. **Różne typy encji:** Porównuj osobno dla MOVIE, PERSON, TV_SERIES, TV_SHOW
3. **Czas testów:** Testuj przez **co najmniej tydzień**, żeby zobaczyć różne scenariusze
4. **Monitorowanie:** Po wdrożeniu TOON, monitoruj metryki przez **miesiąc**, żeby upewnić się, że wszystko działa

---

**Ostatnia aktualizacja:** 2025-12-26

