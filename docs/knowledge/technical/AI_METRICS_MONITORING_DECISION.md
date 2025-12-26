# Decyzja: Monitoring Metryk AI (Token Usage, Parsing Accuracy, Errors)

> **Data utworzenia:** 2025-01-27  
> **Status:** ✅ Zaimplementowane  
> **Kategoria:** technical  
> **Zadanie:** TASK-040 (Faza 3: Monitoring)

## 🎯 Kontekst

W ramach implementacji eksperymentu TOON vs JSON (TASK-040) potrzebujemy systemu monitoringu, który pozwoli:
- Śledzić zużycie tokenów dla różnych formatów danych (JSON, TOON, CSV)
- Monitorować dokładność parsowania odpowiedzi AI
- Śledzić błędy i problemy z generowaniem

## 🔍 Analiza Opcji

### Opcja 1: Logowanie tylko (bez bazy danych)

**Opis:** Użycie Laravel Log do zapisywania metryk w plikach logów.

**Zalety:**
- Proste w implementacji
- Brak dodatkowych tabel w bazie

**Wady:**
- Trudna analiza (parsowanie logów)
- Brak możliwości agregacji
- Brak możliwości generowania raportów
- Wysokie zużycie miejsca na dysku

### Opcja 2: Tabela w bazie danych + analiza on-demand

**Opis:** Tabela `ai_generation_metrics` z surowymi danymi, analiza na żądanie.

**Zalety:**
- Pełna kontrola nad danymi
- Możliwość dokładnej analizy
- Łatwe zapytania SQL

**Wady:**
- Wolne zapytania przy dużych ilościach danych
- Wysokie obciążenie bazy przy analizie

### Opcja 3: Tabela + agregacje + scheduled reports (WYBRANA)

**Opis:** Tabela z surowymi danymi + tabela agregatów + scheduled job do generowania raportów.

**Zalety:**
- Szybka analiza (agregaty)
- Automatyczne raporty okresowe
- Możliwość analizy on-demand (surowa tabela)
- Optymalne wykorzystanie zasobów

**Wady:**
- Większa złożoność implementacji
- Wymaga scheduled jobs

## 🚀 Decyzja

**Wybrana opcja:** Opcja 3: Tabela + agregacje + scheduled reports

**Uzasadnienie:**
- System musi obsługiwać zarówno szybkie analizy (dashboard) jak i szczegółowe raporty
- Automatyczne raporty okresowe pozwalają na proaktywne monitorowanie
- Agregacje zapewniają wydajność przy dużych ilościach danych

## 📝 Szczegóły Implementacji

### 1. Automatyczne zbieranie danych

**Gdzie:** `OpenAiClient::makeApiCall()`

**Co jest zbierane:**
- Tokeny: `prompt_tokens`, `completion_tokens`, `total_tokens` (z odpowiedzi OpenAI)
- Format danych: `JSON`, `TOON`, `CSV`
- Dokładność parsowania: walidacja względem schema
- Błędy: wszystkie błędy parsowania i walidacji
- Czas odpowiedzi: `response_time_ms`
- Model: `gpt-4o-mini` (lub inny)

**Kiedy:** Przy każdym wywołaniu AI (automatycznie, zero konfiguracji)

### 2. Struktura danych

#### Tabela `ai_generation_metrics` (surowa tabela)
- `id` (UUID)
- `job_id` (nullable, link do jobs)
- `entity_type` (MOVIE, PERSON, TV_SERIES, TV_SHOW)
- `entity_slug`
- `data_format` (JSON, TOON, CSV)
- `prompt_tokens`, `completion_tokens`, `total_tokens`
- `token_savings_vs_json` (decimal, oszczędności vs JSON baseline)
- `parsing_successful` (boolean)
- `parsing_errors` (text)
- `validation_errors` (json)
- `response_time_ms`
- `model`
- `created_at`, `updated_at`

#### Tabela `ai_metrics_aggregates` (agregaty)
- `id`
- `date` (date, indeks)
- `entity_type` (nullable, indeks)
- `data_format` (indeks)
- `total_requests`
- `total_tokens`
- `avg_tokens`
- `successful_parsing`
- `failed_parsing`
- `accuracy_percent`
- `avg_token_savings`
- `error_count`
- `created_at`, `updated_at`

### 3. Analiza danych

#### Opcja A: Analiza on-demand (manualna)
- Endpointy API: `/api/v1/admin/ai-metrics/*`
- Obliczenia na żywo z surowej tabeli
- Wolniejsze, ale 100% aktualne

#### Opcja B: Analiza z agregatów (zoptymalizowana)
- Endpointy API używają tabeli agregatów
- Szybkie zapytania
- Aktualizowane przez scheduled job

#### Opcja C: Hybrydowa (REKOMENDOWANA)
- Dashboard używa agregatów (szybkie)
- Szczegółowa analiza używa surowych danych (dokładne)
- Parametr `?cache=true/false` w endpointach

### 4. Generowanie raportów okresowych

**Scheduled Job:** `GenerateAiMetricsReportJob`
- **Częstotliwość:** Codziennie o 2:00 (konfigurowalne)
- **Co robi:**
  1. Agreguje dane z poprzedniego dnia
  2. Zapisuje do tabeli `ai_metrics_aggregates`
  3. Generuje raport (JSON/PDF) i zapisuje do storage
  4. Wysyła notyfikację (opcjonalnie, email/webhook)

**Format raportu:**
- Porównanie formatów (TOON vs JSON)
- Statystyki tokenów
- Dokładność parsowania
- Wykryte problemy
- Rekomendacje

### 5. Endpointy API

```
GET /api/v1/admin/ai-metrics/token-usage
GET /api/v1/admin/ai-metrics/token-usage/{entityType}
GET /api/v1/admin/ai-metrics/parsing-accuracy
GET /api/v1/admin/ai-metrics/parsing-accuracy/{entityType}
GET /api/v1/admin/ai-metrics/errors
GET /api/v1/admin/ai-metrics/errors/{entityType}
GET /api/v1/admin/ai-metrics/comparison
GET /api/v1/admin/ai-metrics/comparison/{entityType}
GET /api/v1/admin/ai-metrics/reports
GET /api/v1/admin/ai-metrics/reports/{reportId}
```

## ⚠️ Konsekwencje

### Pozytywne
- Pełna widoczność zużycia tokenów
- Możliwość optymalizacji kosztów AI
- Proaktywne wykrywanie problemów
- Dane do podejmowania decyzji (TOON vs JSON)

### Negatywne
- Dodatkowe tabele w bazie danych
- Większe zużycie miejsca (surowa tabela)
- Wymaga scheduled jobs (infrastruktura)

## 🛣️ Plan Wdrożenia

1. ✅ Migracja `ai_generation_metrics`
2. ✅ Model `AiGenerationMetric`
3. ✅ Rozszerzenie `OpenAiClient` o tracking
4. ✅ Service `AiMetricsService`
5. ✅ Controller `AiMetricsController`
6. ✅ Migracja `ai_metrics_aggregates`
7. ✅ Model `AiMetricsAggregate`
8. ✅ Job `AggregateAiMetricsJob` (scheduled)
9. ✅ Job `GenerateAiMetricsReportJob` (scheduled)
10. ✅ Dokumentacja (biznesowa, techniczna, QA)

## 📚 Dokumentacja

- **Biznesowa:** `docs/business/AI_METRICS_MONITORING_USER_GUIDE.md`
- **Techniczna:** `docs/technical/AI_METRICS_MONITORING_DEVELOPER_GUIDE.md`
- **QA:** `docs/qa/AI_METRICS_MONITORING_QA_GUIDE.md`

---

**Ostatnia aktualizacja:** 2025-01-27

