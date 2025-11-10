# Detekcja aktywnych jobów generacji w kolejce

> **Data utworzenia:** 2025-11-10  
> **Kontekst:** Wiele wywołań endpointu `GET /api/v1/movies/{slug}` podczas trwającej generacji powoduje duplikowanie jobów w Horizon/Redis.  
> **Kategoria:** technical

## 🎯 Cel

Opisać strategię wykrywania „trwającego” joba generacji dla danego sluga, tak aby:
- nie uruchamiać kolejnych prób podczas oczekiwania na wynik,
- zwracać klientowi istniejący `job_id` i status,
- ograniczyć spamowanie API OpenAI i zaśmiecanie `failed_jobs`.

## 📋 Zawartość

### 1. Obecny przepływ (problem)

- Wywołanie `MovieController@show` dla nieistniejącego filmu uruchamia `QueueMovieGenerationAction::handle()`.
- Akcja generuje nowe `job_id`, inicjalizuje status w cache (Redis) i emituje `MovieGenerationRequested`.
- Listener `QueueMovieGenerationJob` bezwarunkowo wystawia nowy job (`RealGenerateMovieJob` lub `MockGenerateMovieJob`).
- Jeśli klient odpyta endpoint wielokrotnie, przed zakończeniem pierwszej generacji, każdy request zainicjuje nowy job.
- Rezultat: w Horizon widać wiele wpisów z tym samym slugiem, a po błędach (403/429) powstaje lawina wpisów w `failed_jobs`.

### 2. Detekcja aktywnych jobów

Najprostsze miejsce na wprowadzenie kontroli to `QueueMovieGenerationAction::handle()` – zanim wylosujemy nowe `job_id`, sprawdzamy cache jobów.

Propozycja:

```php
// app/Actions/QueueMovieGenerationAction.php
public function handle(string $slug, ?float $confidence = null, ?Movie $existingMovie = null): array
{
    if ($existingJob = $this->jobStatusService->findActiveJob('MOVIE', $slug)) {
        return [
            'job_id' => $existingJob['job_id'],
            'status' => $existingJob['status'],
            'message' => 'Generation already queued for movie by slug',
            'slug' => $slug,
            'confidence' => $existingJob['confidence'] ?? null,
            'confidence_level' => $this->confidenceLabel($existingJob['confidence'] ?? null),
        ];
    }

    // dotychczasowa logika tworzenia nowego joba...
}
```

Implementacja pomocnicza w `JobStatusService`:

```php
public function findActiveJob(string $entityType, string $slug): ?array
{
    return Cache::get("ai_job_lookup:{$entityType}:{$slug}");
}

public function trackJobSlug(string $jobId, string $entityType, string $slug): void
{
    Cache::put("ai_job_lookup:{$entityType}:{$slug}", [
        'job_id' => $jobId,
        'status' => 'PENDING',
    ], now()->addMinutes(self::CACHE_TTL_MINUTES));
}
```

Aktualizacje:
- podczas `initializeStatus()` zapisz lookup po slugu (`trackJobSlug`),
- `markDone` i `markFailed` powinny usuwać lookup (lub aktualizować status),
- po całkowitym zakończeniu joba (`failed()` w jobie) trzeba również wyczyścić wpis.

### 3. Odświeżenie odpowiedzi kontrolera

- Gdy `QueueMovieGenerationAction::handle()` zwróci status `PENDING` dla istniejącego joba, `MovieController@show` odpowie 202 z tym samym `job_id`.
- Klient może odczytać wynik przez `GET /api/v1/jobs/{job_id}` bez tworzenia nowych zadań.

### 4. Korzyści i uwagi

- Mniej wpisów `failed_jobs`, brak wielokrotnego uderzania w API OpenAI.
- Horizon pokazuje pojedynczy job per slug, łatwiej analizować retry.
- Warto kontrolować TTL wpisów w cache – rekomendowane ≥ czas największego backoff + margines.
- Jeśli w przyszłości dodamy feature manualnej regeneracji, warto przewidzieć parametr „force” omijający blokadę.

## 🔗 Powiązane Dokumenty

- `docs/knowledge/reference/FEATURE_FLAGS.md` – opis flag i zachowania generacji.
- `docs/knowledge/technical/QUEUE_ASYNC_EXPLANATION.md` – ogólna architektura kolejki.

## 📌 Notatki

- Po wdrożeniu pamiętaj o testach feature (sprawdzenie, że drugi request zwraca ten sam `job_id`).
- W środowiskach lokalnych monitoruj Redis (`redis-cli keys ai_job:*`) aby upewnić się, że wpisy są sprzątane.

---

**Ostatnia aktualizacja:** 2025-11-10

