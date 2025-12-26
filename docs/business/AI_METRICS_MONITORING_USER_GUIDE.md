# AI Metrics Monitoring - User Guide

> **Dla:** Użytkownicy biznesowi, Product Owners, Managerowie  
> **Cel:** Zrozumienie systemu monitoringu metryk AI i korzystania z raportów

## 📊 Przegląd

System monitoringu metryk AI automatycznie zbiera dane o:
- **Zużyciu tokenów** - ile tokenów zużywa każdy format danych (JSON, TOON, CSV)
- **Dokładności parsowania** - czy AI poprawnie parsuje odpowiedzi
- **Błędach** - jakie problemy występują podczas generowania

## 🎯 Dlaczego to ważne?

### Oszczędności kosztów
- Porównanie formatów pozwala wybrać najbardziej efektywny (mniej tokenów = niższe koszty)
- Monitoring zużycia tokenów pomaga optymalizować koszty AI

### Jakość danych
- Dokładność parsowania pokazuje, czy AI poprawnie rozumie formaty danych
- Wykrywanie błędów pozwala szybko reagować na problemy

### Podejmowanie decyzji
- Dane do decyzji: czy używać TOON zamiast JSON?
- Analiza porównawcza formatów

## 📈 Endpointy API

### 1. Statystyki zużycia tokenów

```bash
GET /api/v1/admin/ai-metrics/token-usage
GET /api/v1/admin/ai-metrics/token-usage?entity_type=MOVIE
```

**Odpowiedź:**
```json
{
  "data": [
    {
      "data_format": "JSON",
      "total_requests": 100,
      "avg_tokens": 150,
      "total_tokens": 15000
    },
    {
      "data_format": "TOON",
      "total_requests": 50,
      "avg_tokens": 120,
      "total_tokens": 6000,
      "avg_savings_percent": 20.0
    }
  ],
  "summary": {
    "total_requests": 150,
    "total_tokens": 21000
  }
}
```

### 2. Dokładność parsowania

```bash
GET /api/v1/admin/ai-metrics/parsing-accuracy
```

**Odpowiedź:**
```json
{
  "data": [
    {
      "data_format": "JSON",
      "total_requests": 100,
      "successful": 98,
      "failed": 2,
      "accuracy_percent": 98.0
    },
    {
      "data_format": "TOON",
      "total_requests": 50,
      "successful": 48,
      "failed": 2,
      "accuracy_percent": 96.0
    }
  ]
}
```

### 3. Statystyki błędów

```bash
GET /api/v1/admin/ai-metrics/errors
```

**Odpowiedź:**
```json
{
  "data": [
    {
      "data_format": "JSON",
      "error_count": 2,
      "affected_entity_types": 1,
      "avg_response_time_ms": 2000
    }
  ]
}
```

### 4. Porównanie formatów (TOON vs JSON)

```bash
GET /api/v1/admin/ai-metrics/comparison
```

**Odpowiedź:**
```json
{
  "data": {
    "token_savings": {
      "absolute": 30,
      "percent": 20.0
    },
    "accuracy": {
      "json": 98.0,
      "toon": 96.0,
      "difference": -2.0
    },
    "avg_tokens": {
      "json": 150,
      "toon": 120,
      "savings": 30
    }
  }
}
```

## 📄 Raporty okresowe

System automatycznie generuje raporty:
- **Codziennie** o 02:00 - raport dzienny
- **Co tydzień** (poniedziałek) o 03:00 - raport tygodniowy
- **Co miesiąc** (1. dnia) o 04:00 - raport miesięczny

Raporty są zapisywane w `storage/app/reports/ai-metrics/` jako pliki JSON.

**Format nazwy pliku:**
```
ai-metrics-daily-2025-01-27_02-00-00.json
ai-metrics-weekly-2025-01-27_03-00-00.json
ai-metrics-monthly-2025-01-27_04-00-00.json
```

**Struktura raportu:**
```json
{
  "period": "daily",
  "start_date": "2025-01-26",
  "end_date": "2025-01-27",
  "generated_at": "2025-01-27T02:00:00+00:00",
  "token_usage": [...],
  "parsing_accuracy": [...],
  "error_statistics": [...],
  "comparison": {...},
  "summary": {
    "total_requests": 150,
    "total_tokens": 21000,
    "avg_accuracy": 97.0
  }
}
```

## 🔍 Interpretacja wyników

### Oszczędności tokenów
- **Pozytywne %** = TOON oszczędza tokeny vs JSON
- **Negatywne %** = TOON zużywa więcej tokenów (nie powinno się zdarzyć)

### Dokładność parsowania
- **> 95%** = bardzo dobra dokładność
- **90-95%** = dobra dokładność
- **< 90%** = wymaga uwagi (sprawdź błędy)

### Porównanie formatów
- Jeśli TOON ma **wyższą dokładność** i **oszczędza tokeny** → rozważ przejście na TOON
- Jeśli TOON ma **niższą dokładność** → pozostań przy JSON

## ⚠️ Kiedy reagować?

### Wysokie zużycie tokenów
- Sprawdź, czy format jest optymalny
- Rozważ przejście na bardziej efektywny format

### Niska dokładność parsowania (< 90%)
- Sprawdź statystyki błędów
- Zidentyfikuj problematyczne formaty
- Rozważ poprawę promptów lub schematów

### Wzrost błędów
- Sprawdź, czy problem dotyczy konkretnego formatu
- Zidentyfikuj przyczyny błędów
- Rozważ rollback do stabilnego formatu

## 📞 Wsparcie

W razie pytań lub problemów, skontaktuj się z zespołem technicznym.

---

**Ostatnia aktualizacja:** 2025-01-27

