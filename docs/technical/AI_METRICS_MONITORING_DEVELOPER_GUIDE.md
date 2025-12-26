# AI Metrics Monitoring - Developer Guide

> **Dla:** Programiści, DevOps, Architekci  
> **Cel:** Dokumentacja techniczna systemu monitoringu metryk AI

## 🏗️ Architektura

### Automatyczne zbieranie danych

Dane są zbierane automatycznie w `OpenAiClient::makeApiCall()`:

```php
// Automatycznie przy każdym wywołaniu AI
$this->trackAiMetrics(
    entityType: $entityType,
    slug: $slug,
    dataFormat: $dataFormat, // JSON, TOON, CSV
    usage: $usage,          // Tokeny z OpenAI API
    parsingResult: $parsingResult, // Walidacja parsowania
    responseTime: $responseTime
);
```

### Struktura danych

#### Tabela `ai_generation_metrics`
- Surowa tabela z wszystkimi metrykami
- Indeksy: `entity_type + data_format`, `created_at`, `parsing_successful`

#### Model `AiGenerationMetric`
- UUID primary key
- Wszystkie pola zgodnie z migracją
- Casts: `validation_errors` → array, `parsing_successful` → boolean

## 🔧 Implementacja

### 1. Tracking w OpenAiClient

```php
// api/app/Services/OpenAiClient.php

private function makeApiCall(
    string $entityType,
    string $slug,
    string $systemPrompt,
    string $userPrompt,
    callable $successMapper,
    array $jsonSchema,
    string $dataFormat = 'JSON' // Dodaj parametr formatu
): array {
    $startTime = microtime(true);
    
    try {
        $response = $this->sendRequest($systemPrompt, $userPrompt, $jsonSchema);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        
        if (!$response->successful()) {
            $this->trackAiMetricsError($entityType, $slug, $dataFormat, new \Exception(...));
            return $this->errorResponse(...);
        }
        
        $content = $this->extractContent($response);
        $usage = $this->extractTokenUsage($response);
        $parsingResult = $this->validateParsing($content, $jsonSchema);
        
        // Track metrics
        $this->trackAiMetrics(...);
        
        return $successMapper($content);
    } catch (\Throwable $e) {
        $this->trackAiMetricsError(...);
        return $this->errorResponse(...);
    }
}
```

### 2. Service do analizy

```php
// api/app/Services/AiMetricsService.php

public function getTokenUsageByFormat(?string $entityType = null): Collection
{
    $query = AiGenerationMetric::query();
    if ($entityType) {
        $query->where('entity_type', $entityType);
    }
    
    return $query->selectRaw('
            data_format,
            COUNT(*) as total_requests,
            AVG(total_tokens) as avg_tokens,
            SUM(total_tokens) as total_tokens
        ')
        ->groupBy('data_format')
        ->get();
}
```

### 3. Controller

```php
// api/app/Http/Controllers/Admin/AiMetricsController.php

public function tokenUsage(Request $request): JsonResponse
{
    $entityType = $request->query('entity_type');
    $stats = $this->metricsService->getTokenUsageByFormat($entityType);
    
    return response()->json([
        'data' => $stats,
        'summary' => [...],
    ]);
}
```

### 4. Scheduled Jobs

```php
// routes/console.php

// Daily report (runs every day at 02:00)
Schedule::job(new GenerateAiMetricsReportJob('daily'))->dailyAt('02:00');

// Weekly report (runs every Monday at 03:00)
Schedule::job(new GenerateAiMetricsReportJob('weekly'))->weeklyOn(1, '03:00');

// Monthly report (runs on the 1st day of each month at 04:00)
Schedule::job(new GenerateAiMetricsReportJob('monthly'))->monthlyOn(1, '04:00');
```

## 📊 Endpointy API

### Base URL
```
/api/v1/admin/ai-metrics
```

### Endpointy

| Method | Endpoint | Opis |
|--------|----------|------|
| GET | `/token-usage` | Statystyki zużycia tokenów |
| GET | `/token-usage?entity_type=MOVIE` | Statystyki dla konkretnego typu encji |
| GET | `/parsing-accuracy` | Dokładność parsowania |
| GET | `/errors` | Statystyki błędów |
| GET | `/comparison` | Porównanie TOON vs JSON |

### Autoryzacja

Wszystkie endpointy wymagają Basic Auth (middleware `admin.basic`).

## 🔄 Workflow

### 1. Zbieranie danych (automatyczne)
```
OpenAiClient::makeApiCall()
  → extractTokenUsage()
  → validateParsing()
  → trackAiMetrics()
  → AiGenerationMetric::create()
```

### 2. Analiza (on-demand)
```
GET /api/v1/admin/ai-metrics/token-usage
  → AiMetricsController::tokenUsage()
  → AiMetricsService::getTokenUsageByFormat()
  → Query ai_generation_metrics
  → Return statistics
```

### 3. Generowanie raportów (scheduled)
```
Schedule::job(GenerateAiMetricsReportJob)
  → Aggregate data from ai_generation_metrics
  → Generate JSON report
  → Save to storage/app/reports/ai-metrics/
```

## 🧪 Testowanie

### Unit Tests
- `AiGenerationMetricTest` - testy modelu
- `OpenAiClientMetricsTrackingTest` - testy trackingu
- `AiMetricsServiceTest` - testy analizy
- `GenerateAiMetricsReportJobTest` - testy job

### Feature Tests
- `AiMetricsControllerTest` - testy endpointów

### Uruchamianie testów
```bash
php artisan test --filter=AiMetrics
```

## 🐛 Debugging

### Sprawdzanie metryk w bazie
```php
use App\Models\AiGenerationMetric;

// Wszystkie metryki
$metrics = AiGenerationMetric::all();

// Metryki dla konkretnego formatu
$toonMetrics = AiGenerationMetric::where('data_format', 'TOON')->get();

// Błędy parsowania
$errors = AiGenerationMetric::where('parsing_successful', false)->get();
```

### Logi
```bash
# Sprawdź logi trackingu
tail -f storage/logs/laravel.log | grep "AI generation metrics"
```

## 📈 Optymalizacja

### Agregacje (przyszłość)

Dla lepszej wydajności przy dużych ilościach danych, można dodać tabelę agregatów:

```php
// Migration: create_ai_metrics_aggregates_table.php
Schema::create('ai_metrics_aggregates', function (Blueprint $table) {
    $table->id();
    $table->date('date')->index();
    $table->string('entity_type', 50)->nullable()->index();
    $table->string('data_format', 10)->index();
    $table->integer('total_requests')->default(0);
    $table->integer('total_tokens')->default(0);
    $table->decimal('accuracy_percent', 5, 2)->default(0);
    // ...
});
```

### Cache

Można dodać cache dla często używanych zapytań:

```php
$stats = Cache::remember('ai_metrics:token_usage', 3600, function () {
    return $this->metricsService->getTokenUsageByFormat();
});
```

## 🔐 Bezpieczeństwo

- Wszystkie endpointy wymagają Basic Auth (`admin.basic` middleware)
- Raporty są zapisywane w `storage/app/reports/ai-metrics/` (nie publiczne)
- Tracking nie powinien wpływać na główny flow (try-catch w `trackAiMetrics`)

## 📚 Powiązane dokumenty

- [AI Metrics Monitoring Decision](../knowledge/technical/AI_METRICS_MONITORING_DECISION.md)
- [User Guide](../business/AI_METRICS_MONITORING_USER_GUIDE.md)
- [QA Guide](../qa/AI_METRICS_MONITORING_QA_GUIDE.md)

---

**Ostatnia aktualizacja:** 2025-01-27

