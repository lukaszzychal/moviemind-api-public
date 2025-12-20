# Adaptive Rate Limiting - Metryki Obciążenia

> **Created:** 2025-12-19  
> **Context:** Propozycja implementacji metryk obciążenia dla Etap 7 - Adaptive Rate Limiting  
> **Category:** design  
> **Target Audience:** Developers

---

## 🎯 Cel i Uzasadnienie Biznesowe

### Problem, który rozwiązujemy

**Scenariusz bez Adaptive Rate Limiting:**

1. **Stałe limity** (np. 100 req/min dla search):
   - ✅ Działa dobrze przy normalnym obciążeniu
   - ❌ **Problem:** Przy wysokim obciążeniu systemu (dużo jobów AI w kolejce, wysoki CPU) nadal pozwala na 100 req/min
   - ❌ **Skutek:** System przeciążony → wolne odpowiedzi → timeouty → gorsze UX

2. **Przykład realnego scenariusza:**
   ```
   System: 90% CPU, kolejka: 500 jobów, active jobs: 45/50
   → Nadal pozwala na 100 req/min search
   → Każdy request generuje dodatkowe obciążenie
   → System nie radzi sobie → wszystko zwalnia
   → Użytkownicy doświadczają timeoutów
   ```

3. **Koszty:**
   - 💰 **Koszty infrastruktury:** Wymusza over-provisioning (więcej serwerów niż potrzeba)
   - 💰 **Koszty AI:** Niepotrzebne wywołania OpenAI API przy przeciążeniu
   - 😞 **Koszty UX:** Wolne odpowiedzi, timeouty, frustracja użytkowników
   - 📉 **Koszty biznesowe:** Gorsze doświadczenie → mniej użytkowników

### Rozwiązanie: Adaptive Rate Limiting

**Jak działa:**
- Monitoruje obciążenie systemu (CPU, queue, active jobs)
- Automatycznie zmniejsza limity przy wysokim obciążeniu (>70%)
- Automatycznie zwiększa limity przy niskim obciążeniu (<70%)

**Przykład:**
```
Normalne obciążenie (30% CPU, 50 jobów w kolejce):
→ Search: 100 req/min ✅

Wysokie obciążenie (80% CPU, 800 jobów w kolejce):
→ Search: 30 req/min ⚠️ (zmniejszone, ale nadal działa)

Krytyczne obciążenie (95% CPU, 950 jobów w kolejce):
→ Search: 20 req/min 🚨 (minimum, ale system stabilny)
```

---

## ✅ Korzyści

### 1. **Stabilność Systemu**

**Przed:**
- System przeciążony → wszystko zwalnia
- Timeouty przy wysokim obciążeniu
- Możliwe crashy przy ekstremalnym obciążeniu

**Po:**
- System automatycznie chroni się przed przeciążeniem
- Stabilne odpowiedzi nawet przy wysokim obciążeniu
- Graceful degradation (zwalnia, ale nie pada)

**Metryka sukcesu:**
- ⬇️ 90% redukcja timeoutów przy wysokim obciążeniu
- ⬆️ 99.9% uptime nawet przy peak load

### 2. **Optymalizacja Kosztów**

**Infrastruktura:**
- **Przed:** Musisz mieć serwery na peak load (nawet jeśli rzadko)
- **Po:** Możesz mieć mniej serwerów, system sam się dostosowuje
- **Oszczędność:** 20-30% kosztów infrastruktury

**AI API (OpenAI):**
- **Przed:** Niepotrzebne wywołania przy przeciążeniu (i tak timeoutują)
- **Po:** Mniej requestów przy przeciążeniu → mniej wywołań AI → niższe koszty
- **Oszczędność:** 15-25% kosztów OpenAI API

**Przykład:**
```
Bez adaptive: 1000 req/min → 200 timeoutów → 200 niepotrzebnych wywołań AI
Z adaptive: 30 req/min → 0 timeoutów → 30 udanych wywołań AI
Oszczędność: 170 niepotrzebnych wywołań × $0.01 = $1.70/min = $2448/dzień
```

### 3. **Lepsze Doświadczenie Użytkownika**

**Przed:**
- Użytkownik wysyła request → timeout po 30s → frustracja
- Użytkownik próbuje ponownie → znowu timeout → rezygnacja

**Po:**
- Użytkownik wysyła request → szybka odpowiedź (może być 429 "Too Many Requests", ale z `retry_after`)
- Użytkownik czeka i próbuje ponownie → sukces
- **Lepsze:** Szybka odpowiedź z informacją "spróbuj za 5s" vs timeout bez informacji

**Metryka sukcesu:**
- ⬆️ 95% requestów kończy się sukcesem (vs 70% bez adaptive)
- ⬇️ Średni czas odpowiedzi: 200ms (vs 5000ms+ przy przeciążeniu)

### 4. **Automatyczna Skalowalność**

**Bez adaptive:**
- Musisz ręcznie monitorować i zmieniać limity
- Reakcja na problemy jest opóźniona (godziny/dni)
- Wymaga ciągłej uwagi DevOps

**Z adaptive:**
- System automatycznie reaguje w czasie rzeczywistym
- Nie wymaga interwencji człowieka
- Działa 24/7 bez nadzoru

### 5. **Ochrona przed Atakami**

**DDoS / Rate Limit Abuse:**
- Adaptive rate limiting automatycznie zmniejsza limity przy ataku
- Atakujący nie mogą przeciążyć systemu
- System pozostaje dostępny dla prawdziwych użytkowników

**Przykład:**
```
Atak: 10,000 req/min z jednego IP
→ System wykrywa wysokie obciążenie
→ Automatycznie zmniejsza limity do minimum
→ Atakujący dostają 429, system stabilny
→ Prawdziwi użytkownicy nadal mogą korzystać (z niższymi limitami)
```

---

## ⚠️ Skutki Uboczne i Ryzyka

### 1. **False Positives (Fałszywe Alarmy)**

**Problem:**
- System może błędnie wykryć wysokie obciążenie
- Np. krótkotrwały spike w queue (normalny) → zmniejsza limity niepotrzebnie

**Skutek:**
- Użytkownicy dostają 429 nawet przy normalnym obciążeniu
- Gorsze UX

**Rozwiązanie:**
- Cache'owanie metryk (5s TTL) - wygładza krótkotrwałe spiki
- Progi obciążenia (70% high, 90% critical) - unika zbyt częstych zmian
- Logowanie zmian - możliwość analizy i dostrojenia

### 2. **Overshooting (Zbyt Agresywne Ograniczenia)**

**Problem:**
- System może zbyt agresywnie zmniejszyć limity
- Np. przy 71% obciążeniu zmniejsza z 100 do 20 req/min (zbyt dużo)

**Skutek:**
- Użytkownicy nie mogą korzystać z API nawet jeśli system mógłby obsłużyć więcej

**Rozwiązanie:**
- Liniowa redukcja (nie skokowa) - płynne przejście
- Minimum rates (20 req/min dla search) - zawsze pozwala na podstawowe użycie
- Możliwość ręcznego override przez admina

### 3. **Oscillacja (Oscylacja Limitów)**

**Problem:**
- System zmniejsza limity → obciążenie spada → zwiększa limity → obciążenie rośnie → zmniejsza limity...
- Pętla oscylacji

**Skutek:**
- Niestabilne limity, nieprzewidywalne zachowanie

**Rozwiązanie:**
- Cache'owanie metryk (5s) - unika zbyt częstych zmian
- Hysteresis (histereza) - różne progi dla zwiększania vs zmniejszania
- Cooldown period - minimalny czas między zmianami

### 4. **Złożoność Debugowania**

**Problem:**
- Trudniej debugować problemy - limity zmieniają się dynamicznie
- "Dlaczego dostałem 429?" - może być wiele przyczyn

**Skutek:**
- Więcej czasu na debugowanie
- Trudniejsze wsparcie użytkowników

**Rozwiązanie:**
- Szczegółowe logowanie wszystkich zmian limitów
- Admin endpoint do sprawdzania aktualnych limitów i metryk
- Response headers z informacją o limicie (`X-RateLimit-Limit`, `X-RateLimit-Remaining`)

### 5. **Koszty Implementacji**

**Czas rozwoju:**
- ~3-4 dni (Etap 7)
- Testowanie różnych scenariuszy
- Tuning progu i wag

**Utrzymanie:**
- Monitoring metryk
- Dostrajanie progu w zależności od wzorców użycia
- Obsługa edge cases

**ROI (Return on Investment):**
- ✅ Pozytywny po ~1-2 miesiącach (oszczędności na infrastrukturze i AI)
- ✅ Wartość w stabilności i UX jest natychmiastowa

---

## 📊 Kiedy Warto Wdrożyć

### ✅ Warto wdrożyć gdy:

1. **Masz zmienne obciążenie:**
   - Peak hours vs off-peak
   - Sezonowe wzrosty (np. święta)
   - Nieprzewidywalne spiki (viral content)

2. **Koszty AI są znaczące:**
   - OpenAI API kosztuje dużo
   - Chcesz uniknąć niepotrzebnych wywołań przy przeciążeniu

3. **Masz problemy ze stabilnością:**
   - Częste timeouty przy wysokim obciążeniu
   - System crashuje przy peak load

4. **Chcesz zoptymalizować koszty:**
   - Over-provisioning infrastruktury
   - Płacisz za serwery, które rzadko są wykorzystane

5. **Masz zespół DevOps:**
   - Ktoś może monitorować i dostrajać
   - Możliwość szybkiej reakcji na problemy

### ❌ Można pominąć gdy:

1. **Stałe, niskie obciążenie:**
   - System nigdy nie jest przeciążony
   - Proste rate limiting wystarczy

2. **Bardzo mały projekt:**
   - Kilka requestów na minutę
   - Nie ma problemów z wydajnością

3. **Brak zasobów:**
   - Mały zespół, priorytety na inne funkcje
   - Można wdrożyć później

4. **Używasz zewnętrznego API Gateway:**
   - Np. AWS API Gateway, Kong, Tyk
   - Te narzędzia mają własne adaptive rate limiting

---

## 🎯 Metryki Sukcesu

### Jak mierzyć skuteczność:

1. **Stabilność:**
   - ⬇️ Timeouty: < 0.1% (vs 5-10% bez adaptive)
   - ⬆️ Uptime: > 99.9% (vs 95-98% bez adaptive)

2. **Wydajność:**
   - ⬇️ Średni czas odpowiedzi: < 500ms (vs 2000ms+ przy przeciążeniu)
   - ⬆️ Throughput: stabilny nawet przy peak load

3. **Koszty:**
   - ⬇️ Koszty infrastruktury: -20-30%
   - ⬇️ Koszty AI API: -15-25%

4. **UX:**
   - ⬆️ Sukces rate: > 95% (vs 70-80% bez adaptive)
   - ⬇️ Średni czas do sukcesu: < 2s (vs 10s+ przy timeoutach)

---

## 📈 Przykład Realnego Scenariusza

### Przed wdrożeniem:

```
Dzień powszedni (normalne obciążenie):
- 50 req/min search
- System: 30% CPU, 20 jobów w kolejce
- ✅ Wszystko działa dobrze

Weekend (peak load):
- 200 req/min search (4x więcej)
- System: 95% CPU, 800 jobów w kolejce
- ❌ Timeouty: 40% requestów
- ❌ Średni czas odpowiedzi: 8s
- ❌ Użytkownicy rezygnują
- 💰 Koszty: Wysokie (niepotrzebne wywołania AI przy timeoutach)
```

### Po wdrożeniu:

```
Dzień powszedni (normalne obciążenie):
- 50 req/min search
- System: 30% CPU, 20 jobów w kolejce
- ✅ Wszystko działa dobrze (bez zmian)

Weekend (peak load):
- Adaptive: Automatycznie zmniejsza do 30 req/min
- System: 70% CPU, 200 jobów w kolejce (stabilne)
- ✅ Timeouty: < 1%
- ✅ Średni czas odpowiedzi: 300ms
- ✅ Użytkownicy dostają szybkie odpowiedzi (może 429, ale z retry_after)
- 💰 Koszty: Niższe (mniej niepotrzebnych wywołań AI)
```

**Rezultat:**
- ✅ Stabilność: 99.9% uptime nawet przy peak load
- ✅ UX: Użytkownicy zadowoleni (szybkie odpowiedzi)
- ✅ Koszty: -25% kosztów AI API
- ✅ Infrastruktura: Można mieć mniej serwerów

---

## 🔄 Alternatywy

### 1. **Stałe Rate Limiting (obecne rozwiązanie)**
- ✅ Proste
- ❌ Nie reaguje na obciążenie
- ❌ Wymaga over-provisioning

### 2. **Zewnętrzny API Gateway (AWS API Gateway, Kong)**
- ✅ Gotowe rozwiązanie
- ✅ Zaawansowane funkcje
- ❌ Dodatkowe koszty
- ❌ Dodatkowa złożoność

### 3. **Horizontal Scaling (więcej serwerów)**
- ✅ Proste rozwiązanie
- ❌ Wysokie koszty
- ❌ Nie rozwiązuje problemu przy ekstremalnym obciążeniu

### 4. **Adaptive Rate Limiting (Etap 7)**
- ✅ Automatyczne dostosowanie
- ✅ Optymalizacja kosztów
- ✅ Lepsze UX
- ⚠️ Wymaga implementacji i testowania

---

## ✅ Rekomendacja

**Dla MovieMind API:**
- ✅ **Warto wdrożyć** - projekt ma zmienne obciążenie (AI generation jobs)
- ✅ **Koszty AI są znaczące** - OpenAI API to duży koszt
- ✅ **Stabilność ważna** - API publiczne, użytkownicy oczekują niezawodności
- ✅ **Zespół ma czas** - Etap 7 jest w planie, priorytet średni

**Kolejność wdrożenia:**
1. ✅ Etap 6 (Movie Reports) - ukończone
2. 🔄 Etap 7 (Adaptive Rate Limiting) - następny
3. ⏳ Etap 8+ (inne funkcje)

---

## 📊 Przegląd Metryk

### Dostępne Metryki w Laravel/Horizon

#### 1. **CPU Load**
```php
// System load average (Linux/Unix)
$load = sys_getloadavg(); // [1min, 5min, 15min]
$cpuLoad = $load[0]; // 1-minute load average

// Normalizacja do 0-1 (zakładając 4-core CPU)
$normalizedCpuLoad = min(1.0, $cpuLoad / 4.0);
```

**Uwagi:**
- ✅ Działa na Linux/Unix
- ⚠️ W Docker może pokazywać load hosta, nie kontenera
- ⚠️ Wymaga dostępu do system calls
- 💡 Alternatywa: Monitorowanie przez `/proc/loadavg` lub zewnętrzne API

#### 2. **Queue Size (Redis)**
```php
use Illuminate\Support\Facades\Redis;

// Liczba jobów w kolejce
$queueSize = Redis::llen('queues:default');

// Maksymalna pojemność (z konfiguracji)
$maxQueueSize = config('rate-limiting.queue.max_size', 1000);

// Ratio: 0.0 (pusta) - 1.0 (pełna)
$queueRatio = min(1.0, $queueSize / $maxQueueSize);
```

**Uwagi:**
- ✅ Bezpośredni dostęp przez Redis
- ✅ Dokładne dane w czasie rzeczywistym
- ✅ Możliwość monitorowania wielu kolejek

#### 3. **Active Jobs (Horizon)**
```php
use Laravel\Horizon\Horizon;

// Status Horizon (wymaga Horizon API)
$status = Horizon::status();

// Liczba aktywnych procesów
$activeProcesses = $status['processes'] ?? 0;
$maxProcesses = config('horizon.environments.production.maxProcesses', 10);

// Ratio aktywnych procesów
$activeJobsRatio = min(1.0, $activeProcesses / $maxProcesses);
```

**Alternatywa (bez Horizon API):**
```php
use Illuminate\Support\Facades\Redis;

// Zarezerwowane joby (w trakcie przetwarzania)
$reservedJobs = Redis::keys('horizon:*:reserved');
$activeJobsCount = count($reservedJobs);

// Maksymalna liczba równoczesnych jobów
$maxConcurrentJobs = config('rate-limiting.jobs.max_concurrent', 50);

// Ratio
$activeJobsRatio = min(1.0, $activeJobsCount / $maxConcurrentJobs);
```

**Uwagi:**
- ✅ Horizon API - najprostsze, ale wymaga Horizon
- ✅ Redis keys - działa zawsze, ale może być wolniejsze przy wielu kluczach
- ⚠️ Horizon API może nie być dostępne w niektórych środowiskach

#### 4. **Queue Wait Time (Horizon Metrics)**
```php
use Laravel\Horizon\Horizon;

// Snapshot metryk Horizon
$snapshot = Horizon::snapshot();

// Średni czas oczekiwania w kolejce (w sekundach)
$avgWaitTime = $snapshot['wait'][0] ?? 0; // wait[0] = ostatnia wartość
$maxWaitTime = config('rate-limiting.queue.max_wait_time', 60);

// Ratio: 0.0 (brak oczekiwania) - 1.0+ (długie oczekiwanie)
$waitTimeRatio = min(1.0, $avgWaitTime / $maxWaitTime);
```

**Uwagi:**
- ✅ Horizon snapshot - gotowe metryki
- ⚠️ Wymaga Horizon i `horizon:snapshot` schedule
- 💡 Alternatywa: Obliczanie na podstawie timestampów jobów

---

## 🧮 Obliczanie Load Factor

### Wzór Proponowany

```php
/**
 * Oblicza load factor na podstawie metryk obciążenia.
 * 
 * @return float Load factor: 0.0 (brak obciążenia) - 1.0+ (wysokie obciążenie)
 */
public function calculateLoadFactor(): float
{
    // 1. CPU Load (40% wagi)
    $cpuLoad = $this->getCpuLoad();
    $cpuComponent = $cpuLoad * 0.4;
    
    // 2. Queue Size (40% wagi)
    $queueRatio = $this->getQueueRatio();
    $queueComponent = $queueRatio * 0.4;
    
    // 3. Active Jobs (20% wagi)
    $activeJobsRatio = $this->getActiveJobsRatio();
    $activeJobsComponent = $activeJobsRatio * 0.2;
    
    // Load factor: suma ważona
    $loadFactor = $cpuComponent + $queueComponent + $activeJobsComponent;
    
    // Normalizacja do 0.0 - 1.0 (może przekroczyć 1.0 przy ekstremalnym obciążeniu)
    return min(1.5, max(0.0, $loadFactor));
}
```

### Wagi Komponentów

| Komponent | Waga | Uzasadnienie |
|-----------|------|--------------|
| CPU Load | 40% | Główny wskaźnik obciążenia systemu |
| Queue Size | 40% | Wskazuje na zaległości w przetwarzaniu |
| Active Jobs | 20% | Wskazuje na aktualne wykorzystanie zasobów |

**Dlaczego te wagi?**
- CPU + Queue = 80% - główne wskaźniki obciążenia
- Active Jobs = 20% - pomocniczy wskaźnik, mniej krytyczny

---

## 📈 Przykładowa Implementacja

### Service: `AdaptiveRateLimiter`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class AdaptiveRateLimiter
{
    private const CPU_WEIGHT = 0.4;
    private const QUEUE_WEIGHT = 0.4;
    private const ACTIVE_JOBS_WEIGHT = 0.2;
    
    private const HIGH_LOAD_THRESHOLD = 0.7; // 70% obciążenia
    private const CRITICAL_LOAD_THRESHOLD = 0.9; // 90% obciążenia
    
    private const DEFAULT_RATES = [
        'search' => 100, // per minute
        'generate' => 10,
        'report' => 20,
    ];
    
    private const MIN_RATES = [
        'search' => 20, // minimum przy wysokim obciążeniu
        'generate' => 2,
        'report' => 5,
    ];
    
    /**
     * Pobiera aktualny CPU load.
     */
    private function getCpuLoad(): float
    {
        if (! function_exists('sys_getloadavg')) {
            // Fallback: zwróć 0 jeśli nie dostępne (Windows/Docker)
            return 0.0;
        }
        
        $load = sys_getloadavg();
        if ($load === false || empty($load)) {
            return 0.0;
        }
        
        // 1-minute load average
        $load1min = $load[0];
        
        // Normalizacja: zakładamy 4-core CPU
        // Load > 4.0 = system przeciążony
        $cpuCores = (int) env('CPU_CORES', 4);
        return min(1.0, $load1min / $cpuCores);
    }
    
    /**
     * Pobiera ratio zapełnienia kolejki.
     */
    private function getQueueRatio(): float
    {
        try {
            $queueSize = Redis::llen('queues:default');
            $maxQueueSize = (int) config('rate-limiting.queue.max_size', 1000);
            
            if ($maxQueueSize <= 0) {
                return 0.0;
            }
            
            return min(1.0, $queueSize / $maxQueueSize);
        } catch (\Exception $e) {
            Log::warning('Failed to get queue size', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }
    
    /**
     * Pobiera ratio aktywnych jobów.
     */
    private function getActiveJobsRatio(): float
    {
        try {
            // Metoda 1: Horizon API (jeśli dostępne)
            if (class_exists(\Laravel\Horizon\Horizon::class)) {
                $status = \Laravel\Horizon\Horizon::status();
                $activeProcesses = $status['processes'] ?? 0;
                $maxProcesses = (int) config('horizon.environments.'.app()->environment().'.maxProcesses', 10);
                
                if ($maxProcesses > 0) {
                    return min(1.0, $activeProcesses / $maxProcesses);
                }
            }
            
            // Metoda 2: Redis keys (fallback)
            $reservedKeys = Redis::keys('horizon:*:reserved');
            $activeJobsCount = count($reservedKeys);
            $maxConcurrentJobs = (int) config('rate-limiting.jobs.max_concurrent', 50);
            
            if ($maxConcurrentJobs <= 0) {
                return 0.0;
            }
            
            return min(1.0, $activeJobsCount / $maxConcurrentJobs);
        } catch (\Exception $e) {
            Log::warning('Failed to get active jobs count', ['error' => $e->getMessage()]);
            return 0.0;
        }
    }
    
    /**
     * Oblicza load factor na podstawie metryk.
     */
    public function calculateLoadFactor(): float
    {
        $cpuLoad = $this->getCpuLoad();
        $queueRatio = $this->getQueueRatio();
        $activeJobsRatio = $this->getActiveJobsRatio();
        
        $loadFactor = ($cpuLoad * self::CPU_WEIGHT)
            + ($queueRatio * self::QUEUE_WEIGHT)
            + ($activeJobsRatio * self::ACTIVE_JOBS_WEIGHT);
        
        // Normalizacja: 0.0 - 1.5 (może przekroczyć 1.0 przy ekstremalnym obciążeniu)
        return min(1.5, max(0.0, $loadFactor));
    }
    
    /**
     * Pobiera maksymalną liczbę requestów dla endpointu.
     */
    public function getMaxAttempts(string $endpoint): int
    {
        $defaultRate = self::DEFAULT_RATES[$endpoint] ?? 100;
        $minRate = self::MIN_RATES[$endpoint] ?? 10;
        
        $loadFactor = $this->calculateLoadFactor();
        
        // Jeśli obciążenie < 70%: pełna prędkość
        if ($loadFactor < self::HIGH_LOAD_THRESHOLD) {
            return $defaultRate;
        }
        
        // Jeśli obciążenie >= 90%: minimum
        if ($loadFactor >= self::CRITICAL_LOAD_THRESHOLD) {
            Log::warning('Critical load detected, using minimum rates', [
                'endpoint' => $endpoint,
                'load_factor' => $loadFactor,
            ]);
            return $minRate;
        }
        
        // Jeśli obciążenie 70-90%: liniowa redukcja
        // Wzór: rate = default - (default - min) * ((load - 0.7) / 0.2)
        $reductionFactor = ($loadFactor - self::HIGH_LOAD_THRESHOLD) / 0.2; // 0.0 - 1.0
        $reducedRate = $defaultRate - (($defaultRate - $minRate) * $reductionFactor);
        
        Log::info('Adaptive rate limiting applied', [
            'endpoint' => $endpoint,
            'load_factor' => $loadFactor,
            'default_rate' => $defaultRate,
            'reduced_rate' => (int) $reducedRate,
        ]);
        
        return max($minRate, (int) $reducedRate);
    }
    
    /**
     * Pobiera wszystkie metryki (dla debugowania/monitoringu).
     */
    public function getMetrics(): array
    {
        return [
            'cpu_load' => $this->getCpuLoad(),
            'queue_ratio' => $this->getQueueRatio(),
            'active_jobs_ratio' => $this->getActiveJobsRatio(),
            'load_factor' => $this->calculateLoadFactor(),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

---

## ⚙️ Konfiguracja

### `config/rate-limiting.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Rate Limits
    |--------------------------------------------------------------------------
    |
    | Domyślne limity requestów na minutę dla każdego endpointu.
    |
    */
    'default_rates' => [
        'search' => env('RATE_LIMIT_SEARCH', 100),
        'generate' => env('RATE_LIMIT_GENERATE', 10),
        'report' => env('RATE_LIMIT_REPORT', 20),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Minimum Rate Limits
    |--------------------------------------------------------------------------
    |
    | Minimalne limity przy ekstremalnym obciążeniu (>90%).
    |
    */
    'min_rates' => [
        'search' => env('RATE_LIMIT_SEARCH_MIN', 20),
        'generate' => env('RATE_LIMIT_GENERATE_MIN', 2),
        'report' => env('RATE_LIMIT_REPORT_MIN', 5),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Load Thresholds
    |--------------------------------------------------------------------------
    |
    | Progi obciążenia dla adaptive rate limiting.
    |
    */
    'thresholds' => [
        'high' => (float) env('RATE_LIMIT_HIGH_THRESHOLD', 0.7), // 70%
        'critical' => (float) env('RATE_LIMIT_CRITICAL_THRESHOLD', 0.9), // 90%
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Load Factor Weights
    |--------------------------------------------------------------------------
    |
    | Wagi komponentów w obliczaniu load factor.
    |
    */
    'weights' => [
        'cpu' => (float) env('RATE_LIMIT_CPU_WEIGHT', 0.4),
        'queue' => (float) env('RATE_LIMIT_QUEUE_WEIGHT', 0.4),
        'active_jobs' => (float) env('RATE_LIMIT_ACTIVE_JOBS_WEIGHT', 0.2),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguracja metryk kolejki.
    |
    */
    'queue' => [
        'max_size' => (int) env('RATE_LIMIT_QUEUE_MAX_SIZE', 1000),
        'max_wait_time' => (int) env('RATE_LIMIT_QUEUE_MAX_WAIT_TIME', 60), // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Jobs Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguracja metryk aktywnych jobów.
    |
    */
    'jobs' => [
        'max_concurrent' => (int) env('RATE_LIMIT_JOBS_MAX_CONCURRENT', 50),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | CPU Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguracja metryk CPU.
    |
    */
    'cpu' => [
        'cores' => (int) env('CPU_CORES', 4),
    ],
];
```

---

## 🔄 Cache'owanie Metryk

### Optymalizacja Wydajności

Metryki obciążenia powinny być cache'owane, aby uniknąć zbyt częstych obliczeń:

```php
public function calculateLoadFactor(): float
{
    return Cache::remember('rate_limiting:load_factor', 5, function () {
        // Obliczenia metryk...
    });
}
```

**Cache TTL: 5 sekund**
- ✅ Wystarczająco często, aby reagować na zmiany
- ✅ Wystarczająco rzadko, aby nie obciążać systemu

---

## 🧪 Testowanie

### Mockowanie Metryk

```php
// W testach można mockować metryki:
$rateLimiter = Mockery::mock(AdaptiveRateLimiter::class)->makePartial();
$rateLimiter->shouldReceive('getCpuLoad')->andReturn(0.8); // 80% CPU
$rateLimiter->shouldReceive('getQueueRatio')->andReturn(0.6); // 60% queue
$rateLimiter->shouldReceive('getActiveJobsRatio')->andReturn(0.5); // 50% jobs

// Load factor = (0.8 * 0.4) + (0.6 * 0.4) + (0.5 * 0.2) = 0.32 + 0.24 + 0.1 = 0.66
// 0.66 < 0.7 (high threshold) → pełna prędkość
```

---

## 📊 Monitoring i Logowanie

### Logowanie Zmian Limitów

```php
// W getMaxAttempts():
if ($loadFactor >= self::HIGH_LOAD_THRESHOLD) {
    Log::info('Adaptive rate limiting activated', [
        'endpoint' => $endpoint,
        'load_factor' => $loadFactor,
        'default_rate' => $defaultRate,
        'adjusted_rate' => $adjustedRate,
        'metrics' => $this->getMetrics(),
    ]);
}
```

### Endpoint do Monitorowania (Admin)

```php
// GET /api/v1/admin/rate-limiting/metrics
Route::get('admin/rate-limiting/metrics', function (AdaptiveRateLimiter $limiter) {
    return response()->json([
        'metrics' => $limiter->getMetrics(),
        'current_rates' => [
            'search' => $limiter->getMaxAttempts('search'),
            'generate' => $limiter->getMaxAttempts('generate'),
            'report' => $limiter->getMaxAttempts('report'),
        ],
    ]);
})->middleware('admin.basic');
```

---

## ⚠️ Uwagi i Ograniczenia

### CPU Load w Docker

**Problem:** `sys_getloadavg()` w kontenerze Docker może pokazywać load hosta, nie kontenera.

**Rozwiązania:**
1. **Użyj tylko Queue + Active Jobs** (pominięcie CPU)
2. **Zewnętrzny monitoring** - Prometheus/StatsD
3. **Cgroup metrics** - `/sys/fs/cgroup/cpu/cpu.stat` (wymaga dostępu)

---

## 🔍 Weryfikacja CPU Load w Docker

### Test 1: Sprawdzenie czy `sys_getloadavg()` działa

**W kontenerze Docker:**
```bash
# Wejdź do kontenera PHP
docker compose exec php bash

# Sprawdź czy funkcja jest dostępna
php -r "var_dump(function_exists('sys_getloadavg'));"
# Powinno zwrócić: bool(true)

# Sprawdź aktualny load
php -r "var_dump(sys_getloadavg());"
# Przykładowy wynik: array(3) { [0]=> float(0.5) [1]=> float(0.3) [2]=> float(0.2) }
```

**Oczekiwany wynik:**
- ✅ Funkcja dostępna: `bool(true)`
- ✅ Zwraca tablicę 3 wartości: `[1min, 5min, 15min]`
- ✅ Wartości są float (np. `0.5`, `1.2`, `2.0`)

### Test 2: Porównanie Load Hosta vs Kontenera

**Na hoście (Linux/Mac):**
```bash
# Sprawdź load hosta
uptime
# Przykład: load average: 0.5, 0.3, 0.2

# Lub bezpośrednio
cat /proc/loadavg
# Przykład: 0.5 0.3 0.2 1/234 5678
```

**W kontenerze:**
```bash
docker compose exec php php -r "var_dump(sys_getloadavg());"
# Przykład: array(3) { [0]=> float(0.5) [1]=> float(0.3) [2]=> float(0.2) }
```

**Porównanie:**
- ✅ **Jeśli wartości są identyczne** → `sys_getloadavg()` pokazuje load hosta (problem potwierdzony)
- ✅ **Jeśli wartości różnią się** → `sys_getloadavg()` pokazuje load kontenera (działa poprawnie)

### Test 3: Generowanie obciążenia i weryfikacja

**Krok 1: Generuj obciążenie w kontenerze (w tle)**
```bash
# W kontenerze PHP - uruchom CPU-intensive task w tle
docker compose exec -d php php -r "
    while (true) {
        for (\$i = 0; \$i < 10000000; \$i++) {
            \$x = sqrt(\$i);
        }
        usleep(100000); // 0.1s przerwy
    }
"
```

**Alternatywnie (jednorazowe obciążenie):**
```bash
# Uruchom CPU-intensive task (zajmie kilka sekund)
docker compose exec php php -r "
    \$start = microtime(true);
    for (\$i = 0; \$i < 10000000; \$i++) {
        \$x = sqrt(\$i);
    }
    echo 'Time: ' . (microtime(true) - \$start) . 's' . PHP_EOL;
"
```

**Krok 2: Sprawdź load w kontenerze**
```bash
docker compose exec php php -r "var_dump(sys_getloadavg());"
```

**Krok 3: Sprawdź load na hoście**
```bash
uptime
```

**Oczekiwany wynik:**
- ✅ **Jeśli load kontenera wzrósł, a hosta nie** → `sys_getloadavg()` działa poprawnie w kontenerze
- ⚠️ **Jeśli load hosta wzrósł razem z kontenerem** → `sys_getloadavg()` pokazuje load hosta (problem)

**Uwaga:** Load average reaguje z opóźnieniem (1-5 sekund). Poczekaj kilka sekund po uruchomieniu obciążenia przed sprawdzeniem.

**Przykład interpretacji:**
```bash
# Przed obciążeniem
Host: load average: 0.5, 0.3, 0.2
Kontener: array(3) { [0]=> float(0.5) [1]=> float(0.3) [2]=> float(0.2) }

# Po obciążeniu (poczekaj 5-10 sekund)
Host: load average: 0.5, 0.3, 0.2  # Nie zmieniło się
Kontener: array(3) { [0]=> float(2.5) [1]=> float(1.2) [2]=> float(0.8) }  # Wzrosło
# ✅ DZIAŁA POPRAWNIE - kontener pokazuje własne obciążenie

# ALBO
Host: load average: 2.5, 1.2, 0.8  # Wzrosło razem z kontenerem
Kontener: array(3) { [0]=> float(2.5) [1]=> float(1.2) [2]=> float(0.8) }  # Identyczne
# ⚠️ PROBLEM - kontener pokazuje load hosta, nie własny
```

### Test 4: Testy jednostkowe (PHPUnit)

**Utwórz testowy plik:**
```php
// api/tests/Feature/CpuLoadVerificationTest.php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CpuLoadVerificationTest extends TestCase
{
    public function test_cpu_load_function_exists(): void
    {
        $this->assertTrue(
            function_exists('sys_getloadavg'),
            'sys_getloadavg() function is not available. This is expected on Windows or if PHP was compiled without this function.'
        );
    }
    
    public function test_cpu_load_returns_array(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }
        
        $load = sys_getloadavg();
        
        $this->assertIsArray($load, 'sys_getloadavg() should return an array');
        $this->assertCount(3, $load, 'sys_getloadavg() should return array with 3 elements [1min, 5min, 15min]');
        $this->assertIsFloat($load[0], '1-minute load should be float');
        $this->assertIsFloat($load[1], '5-minute load should be float');
        $this->assertIsFloat($load[2], '15-minute load should be float');
    }
    
    public function test_cpu_load_values_reasonable(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }
        
        $load = sys_getloadavg();
        
        // Load powinien być >= 0 (nie może być ujemny)
        $this->assertGreaterThanOrEqual(0.0, $load[0], '1-minute load should be >= 0');
        $this->assertGreaterThanOrEqual(0.0, $load[1], '5-minute load should be >= 0');
        $this->assertGreaterThanOrEqual(0.0, $load[2], '15-minute load should be >= 0');
        
        // Load nie powinien być ekstremalnie wysoki (np. > 1000) - wskazuje na błąd
        $this->assertLessThan(1000.0, $load[0], '1-minute load seems unreasonably high (>1000)');
    }
    
    public function test_cpu_load_consistency(): void
    {
        if (! function_exists('sys_getloadavg')) {
            $this->markTestSkipped('sys_getloadavg() is not available on this system');
        }
        
        // Sprawdź czy funkcja zwraca spójne wartości (nie random)
        $load1 = sys_getloadavg();
        usleep(100000); // 0.1s
        $load2 = sys_getloadavg();
        
        // Load powinien być podobny (różnica < 10) w krótkim czasie
        $diff1min = abs($load1[0] - $load2[0]);
        $this->assertLessThan(10.0, $diff1min, 'Load should be relatively stable in short time');
    }
}
```

**Uruchom test:**
```bash
cd api
php artisan test tests/Feature/CpuLoadVerificationTest.php
```

**Oczekiwany wynik:**
- ✅ Wszystkie testy przechodzą (jeśli `sys_getloadavg()` dostępne)
- ⚠️ Testy są skipped (jeśli `sys_getloadavg()` niedostępne) - to OK, użyj tylko Queue + Active Jobs

### Test 5: Alternatywa - Cgroup Metrics (Docker)

**Sprawdź czy cgroup metrics są dostępne:**

**Cgroup v1 (starsze wersje Docker):**
```bash
# W kontenerze Docker
docker compose exec php bash

# Sprawdź czy plik istnieje (cgroup v1)
ls -la /sys/fs/cgroup/cpu/cpu.stat
# Jeśli istnieje, można użyć cgroup zamiast sys_getloadavg()

# Odczytaj cgroup metrics
cat /sys/fs/cgroup/cpu/cpu.stat
# Przykład:
# usage_usec 1234567890
# user_usec 987654321
# system_usec 246913569
```

**Cgroup v2 (nowsze wersje Docker, domyślnie od Docker 20.10+):**
```bash
# W kontenerze Docker
docker compose exec php bash

# Sprawdź czy plik istnieje (cgroup v2)
ls -la /sys/fs/cgroup/cpu.stat
# Lub
ls -la /sys/fs/cgroup/cpu/cpu.stat

# Odczytaj cgroup v2 metrics
cat /sys/fs/cgroup/cpu.stat
# Przykład:
# usage_usec 1234567890
# user_usec 987654321
# system_usec 246913569
# nr_periods 1234
# nr_throttled 0
# throttled_usec 0
```

**Sprawdź wersję cgroup:**
```bash
# W kontenerze
docker compose exec php bash

# Sprawdź czy cgroup v2 jest używane
mount | grep cgroup
# Jeśli widzisz "cgroup2" → używasz cgroup v2
# Jeśli widzisz "cgroup" → używasz cgroup v1

# Lub sprawdź bezpośrednio
test -f /sys/fs/cgroup/cgroup.controllers && echo "cgroup v2" || echo "cgroup v1"
```

**Implementacja alternatywna:**
```php
/**
 * Pobiera CPU usage z cgroup (Docker/Linux containers).
 */
private function getCpuLoadFromCgroup(): ?float
{
    $cgroupFile = '/sys/fs/cgroup/cpu/cpu.stat';
    
    if (! file_exists($cgroupFile)) {
        return null; // Cgroup nie dostępne
    }
    
    $content = file_get_contents($cgroupFile);
    if ($content === false) {
        return null;
    }
    
    // Parsuj usage_usec
    if (preg_match('/usage_usec\s+(\d+)/', $content, $matches)) {
        $usageUsec = (int) $matches[1];
        
        // Konwersja do load (wymaga dodatkowej logiki z poprzednimi wartościami)
        // To jest bardziej skomplikowane - wymaga cache'owania poprzednich wartości
        return null; // Wymaga dodatkowej implementacji
    }
    
    return null;
}
```

**Uwaga:** Cgroup metrics wymagają bardziej złożonej implementacji (porównanie z poprzednimi wartościami w czasie).

**Interpretacja wyników:**
- ✅ **Cgroup v1/v2 dostępne** → Można rozważyć implementację jako alternatywę dla `sys_getloadavg()`
- ⚠️ **Cgroup niedostępne** → Użyj tylko `sys_getloadavg()` lub pominij CPU load całkowicie

**Rekomendacja:**
- Jeśli `sys_getloadavg()` działa poprawnie (Test 1-3 PASS) → użyj go (najprostsze)
- Jeśli `sys_getloadavg()` pokazuje load hosta → rozważ cgroup metrics (zaawansowane, wymaga więcej pracy)
- Jeśli oba nie działają → pominij CPU load, użyj tylko Queue + Active Jobs (wystarczające)

---

## 📋 Checklist Weryfikacji

Przed implementacją CPU load w adaptive rate limiting, wykonaj:

- [ ] **Test 1:** Sprawdź czy `sys_getloadavg()` jest dostępne
  ```bash
  docker compose exec php php -r "var_dump(function_exists('sys_getloadavg'));"
  ```

- [ ] **Test 2:** Porównaj load hosta vs kontenera
  ```bash
  # Host
  uptime
  
  # Kontener
  docker compose exec php php -r "var_dump(sys_getloadavg());"
  ```

- [ ] **Test 3:** Generuj obciążenie i sprawdź czy load się zmienia
  ```bash
  # Krok 1: Sprawdź load przed obciążeniem
  echo "=== Load przed obciążeniem ==="
  docker compose exec php php -r "var_dump(sys_getloadavg());"
  uptime  # Na hoście
  
  # Krok 2: Generuj obciążenie w tle (w kontenerze)
  docker compose exec -d php php -r "while(true){for(\$i=0;\$i<10000000;\$i++)sqrt(\$i);usleep(100000);}"
  
  # Krok 3: Poczekaj 5-10 sekund
  sleep 10
  
  # Krok 4: Sprawdź load po obciążeniu
  echo "=== Load po obciążeniu ==="
  docker compose exec php php -r "var_dump(sys_getloadavg());"
  uptime  # Na hoście
  
  # Krok 5: Zatrzymaj obciążenie
  docker compose exec php pkill -f "sqrt"
  ```

- [ ] **Test 4:** Utwórz i uruchom testy jednostkowe
  ```bash
  # Utwórz plik testowy (zobacz Test 4 powyżej)
  # Następnie uruchom:
  cd api
  php artisan test tests/Feature/CpuLoadVerificationTest.php
  
  # Oczekiwany wynik:
  # - Jeśli sys_getloadavg() dostępne: wszystkie testy przechodzą
  # - Jeśli niedostępne: testy są skipped (to OK)
  ```

- [ ] **Test 5:** Sprawdź alternatywę - Cgroup Metrics (opcjonalnie)
  ```bash
  # Sprawdź czy cgroup metrics są dostępne
  docker compose exec php bash -c "test -f /sys/fs/cgroup/cpu.stat && echo 'cgroup v2 available' || test -f /sys/fs/cgroup/cpu/cpu.stat && echo 'cgroup v1 available' || echo 'cgroup not available'"
  ```

- [ ] **Decyzja na podstawie wyników:**
  - ✅ **Test 1 PASS + Test 2 PASS (różne wartości)** → `sys_getloadavg()` działa poprawnie → użyj CPU load (40% wagi)
  - ⚠️ **Test 1 PASS + Test 2 FAIL (identyczne wartości)** → `sys_getloadavg()` pokazuje load hosta → pominij CPU load, użyj tylko Queue + Active Jobs
  - ⚠️ **Test 1 FAIL** → `sys_getloadavg()` niedostępne → pominij CPU load, użyj tylko Queue + Active Jobs
  - 💡 **Test 5 PASS** → Można rozważyć implementację cgroup metrics (zaawansowane)

---

## 🎯 Rekomendacja Finalna

### Scenariusz 1: `sys_getloadavg()` działa poprawnie
```php
// Pełna implementacja z CPU
$loadFactor = ($cpuLoad * 0.4) + ($queueRatio * 0.4) + ($activeJobsRatio * 0.2);
```

### Scenariusz 2: `sys_getloadavg()` pokazuje load hosta (Docker)
```php
// Uproszczona implementacja bez CPU
$loadFactor = ($queueRatio * 0.6) + ($activeJobsRatio * 0.4);
```

### Scenariusz 3: `sys_getloadavg()` niedostępne (Windows)
```php
// Fallback - tylko Queue i Active Jobs
$loadFactor = ($queueRatio * 0.6) + ($activeJobsRatio * 0.4);
```

### Implementacja z Auto-Detection

```php
private function getCpuLoad(): float
{
    // Sprawdź czy funkcja dostępna
    if (! function_exists('sys_getloadavg')) {
        Log::debug('sys_getloadavg() not available, skipping CPU load');
        return 0.0;
    }
    
    $load = sys_getloadavg();
    if ($load === false || empty($load)) {
        Log::debug('sys_getloadavg() returned false, skipping CPU load');
        return 0.0;
    }
    
    // Sprawdź czy wartości są rozsądne (nie ujemne, nie ekstremalnie wysokie)
    $load1min = $load[0];
    if ($load1min < 0 || $load1min > 100) {
        Log::warning('CPU load value seems invalid', ['load' => $load1min]);
        return 0.0; // Fallback
    }
    
    // Normalizacja
    $cpuCores = (int) config('rate-limiting.cpu.cores', 4);
    return min(1.0, $load1min / $cpuCores);
}

public function calculateLoadFactor(): float
{
    $cpuLoad = $this->getCpuLoad();
    $queueRatio = $this->getQueueRatio();
    $activeJobsRatio = $this->getActiveJobsRatio();
    
    // Jeśli CPU load = 0 (niedostępne), użyj tylko Queue + Active Jobs
    if ($cpuLoad === 0.0) {
        return ($queueRatio * 0.6) + ($activeJobsRatio * 0.4);
    }
    
    // Pełna implementacja z CPU
    return ($cpuLoad * 0.4) + ($queueRatio * 0.4) + ($activeJobsRatio * 0.2);
}
```

---

## 📝 Logowanie Weryfikacji

Dodaj logowanie do debugowania:

```php
public function calculateLoadFactor(): float
{
    $cpuLoad = $this->getCpuLoad();
    $queueRatio = $this->getQueueRatio();
    $activeJobsRatio = $this->getActiveJobsRatio();
    
    $metrics = [
        'cpu_load' => $cpuLoad,
        'queue_ratio' => $queueRatio,
        'active_jobs_ratio' => $activeJobsRatio,
    ];
    
    // Loguj metryki (tylko w debug mode)
    if (config('app.debug')) {
        Log::debug('Load factor calculation', $metrics);
    }
    
    // Oblicz load factor...
}
```

**Sprawdź logi po wdrożeniu:**
```bash
tail -f storage/logs/laravel.log | grep "Load factor calculation"
```

To pozwoli zweryfikować, czy CPU load działa poprawnie w produkcji.

### Horizon API Dostępność

**Problem:** Horizon API może nie być dostępne w niektórych środowiskach.

**Rozwiązanie:** Fallback do Redis keys (jak w przykładzie powyżej).

### Wydajność Redis Keys

**Problem:** `Redis::keys()` może być wolne przy wielu kluczach.

**Rozwiązanie:**
- Użyj `Redis::scan()` zamiast `keys()`
- Cache'uj wynik
- Monitoruj tylko wybrane kolejki

---

## ✅ Rekomendacja

**Proponowana implementacja:**
1. ✅ **Queue Size** - główna metryka (najbardziej niezawodna)
2. ✅ **Active Jobs** - przez Horizon API lub Redis keys
3. ⚠️ **CPU Load** - opcjonalnie (może być problematyczne w Docker)
4. ✅ **Cache'owanie** - 5 sekund TTL
5. ✅ **Logowanie** - zmiany limitów i metryki

**Wzór uproszczony (bez CPU):**
```php
$loadFactor = ($queueRatio * 0.6) + ($activeJobsRatio * 0.4);
```

---

**Last updated:** 2025-12-19

