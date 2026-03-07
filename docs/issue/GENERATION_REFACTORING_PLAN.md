# Plan refaktoryzacji: generowanie opisów (Movie, Person, TV Series, TV Show)

**Status:** ZAKOŃCZONY  
**Cel:** Uprościć i ujednolicić kod generowania treści AI dla Movie, Person, TvSeries i TvShow – mniej duplikacji, czytelniejsza struktura, ewentualne abstrakcje tam, gdzie się opłaca.

---

## 1. Analiza obecnego stanu

### 1.1 Rozmiary (linie kodu)

| Obszar | Movie | Person | TvSeries | TvShow |
|--------|-------|--------|----------|--------|
| RealGenerate*Job | 1677 | 892 | 661 | 661 |
| MockGenerate*Job | 508 | 475 | 459 | 460 |
| Queue*GenerationAction | 316 | 295 | 173 | 173 |
| Queue*GenerationJob (listener) | 69 | 43 | 45 | 45 |

Łącznie ok. **~7k linii** w jobach, akcjach i listenerach. TvSeries i TvShow są niemal identyczne (661 vs 661; 173 vs 173).

### 1.2 Wspólne wzorce

**A. Queue*GenerationAction (4 akcje)**  
- Ten sam przepływ: `normalizeLocale` → `normalizeContextTag` → szukanie istniejącego obiektu → `findActiveJobForSlug` → `acquireGenerationSlot` → `initializeStatus` → dispatch eventu → budowa odpowiedzi.
- **Zduplikowane:** `normalizeLocale()`, `normalizeContextTag()`, `confidenceLabel()`, schemat `buildExistingJobResponse()`.
- **Różnice:** typ encji (MOVIE / TV_SERIES / TV_SHOW / PERSON), model (Movie/TvSeries/TvShow/Person), klasa eventu, nazwa pola baseline (baselineDescriptionId / baselineBioId).

**B. RealGenerate*Job i MockGenerate*Job (po 4 joby)**  
- **Wspólna logika:**  
  - `resolveLocale()`, `normalizeLocale()`, `normalizeContextTag()`, `determineContextTag()`, `nextContextTag()` – identyczne lub prawie identyczne.  
  - `shouldUpdateBaseline()`, `getBaselineDescription()`, `updateBaselineDescription()` / `updateBaselineBio()` – ten sam wzorzec (find by entity+locale+context_tag, update or create).  
  - `updateCache()`, `cacheKey()`, `baselineLockingEnabled()`, `failed()`, `promoteDefaultIfEligible()` – ta sama struktura, różne klucze/typy.  
  - `refreshExisting*`: resolve locale/context → wywołanie AI → walidacja (AiOutputValidator) → persist description/bio → promocja default / invalidate cache.  
  - `create*Record`: lock → wywołanie AI → utworzenie encji + opisu/bio → zwrócenie.
- **Różnice:** typ encji, repozytorium, model, metoda AI (`generateMovie` / `generatePerson` / `generateTvSeries` / `generateTvShow`), Movie ma dodatkowo cast/crew i dwie ścieżki (generateMovie vs generateMovieDescription).

**C. Listeners Queue*GenerationJob (4)**  
- Ten sam schemat: `AiServiceSelector::getService()` → `validate()` → dispatch Real lub Mock job z parametrami z eventu.  
- Różnica tylko w typie eventu i klasie jobu.

**D. OpenAiClient**  
- `getContextTagInstructions()` już współdzielone.  
- `generatePerson`, `generateTvSeries`, `generateTvShow`: wspólny schemat (locale + styleInstructions w promptach, podobna obsługa JSON).  
- Movie: dwa wejścia (`generateMovie` z pełnym tworzeniem, `generateMovieDescription` tylko opis) – sensownie zostawić, ewentualnie współdzielić fragmenty promptów (język, styl).

---

## 2. Proponowane kierunki refaktoryzacji

### 2.1 Warstwa Actions – współdzielone helpery (niski ryzyk)

- **Wyniesienie do klasy pomocniczej lub trait:**  
  - `normalizeLocale(?string $locale): ?string`  
  - `normalizeContextTag(?string $contextTag): ?string`  
  - `confidenceLabel(?float $confidence): string`  
- **Miejsce:** np. `App\Helpers\GenerationRequestNormalizer` lub trait `App\Actions\NormalizesGenerationRequest` używany przez wszystkie Queue*GenerationAction.
- **Efekt:** jedna implementacja zamiast 4 × po ok. 15–25 linii; spójna walidacja locale/context_tag w całym API.

### 2.2 Warstwa Listeners – ujednolicenie (niski ryzyk)

- **Opcja A:** Jeden listener generyczny rejestrowany pod 4 eventami, wewnątrz rozgałęzienie po typie eventu (np. `movie` / `person` / `tv_series` / `tv_show`) → wybór klasy jobu (Real/Mock) i dispatch.  
- **Opcja B:** Zostawić 4 osobne listenery, ale wyciągnąć wspólną logikę do helpera, np. `AiServiceSelector::dispatchGenerationJob(string $entityType, object $event): void`, który przyjmuje event i na podstawie entityType wywołuje odpowiedni Real/Mock job.  
- Rekomendacja: **Opcja B** – mniej inwazyjna, łatwiejsze testy i rejestracja w EventServiceProvider bez zmian w sygnaturach.

### 2.3 Joby – trait dla wspólnej logiki locale/context/baseline (średni zysk, średnie ryzyko)

- **Trait (np. `App\Jobs\Concerns\ResolvesLocaleAndContext`):**  
  - `resolveLocale(): Locale`  
  - `normalizeLocale(string $locale): ?string`  
  - `normalizeContextTag(string $contextTag): ?string`  
  - `determineContextTag(object $entity, Locale $locale): string` – wymaga przekazania encji (Movie/Person/TvSeries/TvShow) i wywołania `nextContextTag($entity)` zależnego od typu.  
- **Trait (np. `App\Jobs\Concerns\ManagesGenerationCache`):**  
  - `cacheKey(): string` (abstrakcja: job musi mieć `$this->jobId`),  
  - `updateCache(...)` – sygnatura zależna od entity (id, slug, descriptionId/bioId, locale, contextTag), można ujednolicić na wspólny zestaw argumentów.  
- **Trait (np. `App\Jobs\Concerns\BaselineLocking`):**  
  - `baselineLockingEnabled(): bool`  
  - `shouldUpdateBaseline(object $entity, Locale $locale): bool` – wymaga abstrakcji „pobierz baseline” (np. metoda na jobie `getBaselineDescription($entity)`).  
  - `updateBaselineDescription` / `updateBaselineBio` – trudniejsze do ujednolicenia bez interfejsu „HasDescriptions” (entity + description model); można w pierwszej iteracji zostawić w jobach, tylko wyciągnąć `nextContextTag()` do współdzielonego helpera.
- **Kolejność:** najpierw trait `ResolvesLocaleAndContext` + wspólny `nextContextTag(array $existingTags): string` (lista tagów z encji). Potem ewentualnie cache i baseline.

### 2.4 TvSeries vs TvShow – maksymalne uproszczenie (wysoki zysk, niski ryzyk)

- RealGenerateTvSeriesJob i RealGenerateTvShowJob są niemal identyczne (661 linii każdy); to samo MockGenerateTvSeriesJob vs MockGenerateTvShowJob.
- **Opcja A:** Jeden generyczny job np. `RealGenerateTvContentJob` z parametrem `entityType: 'TV_SERIES'|'TV_SHOW'`; wewnątrz rozgałęzienie na TvSeriesRepository/TvSeriesDescription vs TvShowRepository/TvShowDescription.  
- **Opcja B:** Zachować dwa joby, ale wydzielić wspólną klasę serwisową np. `TvContentGenerationPipeline`, która przyjmuje typ (TV_SERIES/TV_SHOW), slug, locale, contextTag, tmdbData i wykonuje: resolve locale/context → wywołanie OpenAiClient (generateTvSeries lub generateTvShow) → walidacja → persist (przez repository/factory). Joby tylko orkiestrują (lock, find existing, wywołanie pipeline, cache, failed).  
- Rekomendacja: **Opcja B** – jeden wspólny pipeline dla TvSeries i TvShow, joby cienkie. Mniejsza zmiana w rejestracji queue/listenerów niż łączenie jobów w jeden generyczny.

### 2.5 OpenAiClient – tylko lekkie uproszczenia (opcjonalnie)

- Wspólna metoda pomocnicza typu `buildLocaleAndStyleInstructions(string $locale, string $contextTag): string` używana w `generatePerson`, `generateTvSeries`, `generateTvShow` (i ewentualnie w fragmentach promptów dla Movie).  
- Nie łączyć metod `generatePerson` / `generateTvSeries` / `generateTvShow` w jedną generyczną bez wyraźnej potrzeby – różne schematy JSON i wymagania promptów; ryzyko regresji większe niż zysk.

### 2.6 Movie – bez łączenia z innymi encjami

- RealGenerateMovieJob jest duży (1677 linii) z powodu cast/crew, TMDb, dwóch ścieżek (refresh vs create).  
- Na tym etapie **nie** łączyć Movie z Person/TV w jedną abstrakcję; ewentualnie wewnątrz Movie jobu użyć traitów z p. 2.3 (resolveLocale, normalizeContextTag, cache, baseline), żeby zmniejszyć duplikację z innymi jobami.

---

## 3. Kolejność wdrożenia (rekomendowana)

1. **Faza 1 – Actions + Listeners (niski ryzyk) (✅ Zrobione)**  
   - Wprowadzić helper/trait dla Actions: `normalizeLocale`, `normalizeContextTag`, `confidenceLabel`.  
   - Ujednolicić listenery (wspólny helper dispatchu), ewentualnie uprościć QueueMovieGenerationJob do tego samego wzorca co Person/TvSeries/TvShow (match + brak zbędnego logowania).

2. **Faza 2 – Trait dla jobów (locale/context) (✅ Zrobione)**  
   - Trait `ResolvesLocaleAndContext`: `resolveLocale`, `normalizeLocale`, `normalizeContextTag`, `determineContextTag`, `nextContextTag`.  
   - Wymaga w jobach: `$this->locale`, `$this->contextTag`, oraz metody zwracającej listę istniejących context_tagów z encji.  
   - Podłączyć trait w RealGenerateMovieJob, RealGeneratePersonJob, RealGenerateTvSeriesJob, RealGenerateTvShowJob (oraz w Mock wersjach).  
   - Testy: istniejące testy jednostkowe/feature dla generowania muszą przechodzić.

3. **Faza 3 – TvSeries + TvShow pipeline (✅ Zrobione)**  
   - Wydzielić klasę (np. `TvContentGenerationPipeline` lub `TvDescriptionGenerationService`) z logiką: resolve locale/context → wywołanie AI → walidacja → persist.  
   - RealGenerateTvSeriesJob i RealGenerateTvShowJob wywołują ten pipeline z odpowiednim typem (TV_SERIES / TV_SHOW).  
   - Mock jobi analogicznie (wspólna logika mocka dla TV lub osobna, zależnie od tego, jak bardzo się różnią).  
   - Po wdrożeniu: usunąć zduplikowany kod z obu jobów (np. po ok. 200–250 linii mniej w sumie).

4. **Faza 4 – opcjonalnie (✅ Zrobione)**  
   - Trait/helper dla cache i baseline w jobach (jeśli po Fazie 2–3 wciąż będzie wyraźna duplikacja).  
   - OpenAiClient: `buildLocaleAndStyleInstructions()` jeśli ułatwi to utrzymanie promptów.

---

## 4. Pliki do zmiany (szacunkowo)

| Faza | Pliki |
|------|--------|
| 1 | `app/Actions/Queue*GenerationAction.php` (4), nowy `app/Helpers/GenerationRequestNormalizer.php` lub trait; `app/Listeners/Queue*GenerationJob.php` (4), ewentualnie `app/Helpers/GenerationJobDispatcher.php` |
| 2 | Nowy trait `app/Jobs/Concerns/ResolvesLocaleAndContext.php`; wszystkie 8 jobów (Real+Mock dla Movie, Person, TvSeries, TvShow) |
| 3 | Nowy `app/Services/TvContentGenerationPipeline.php` (lub podobna nazwa); `RealGenerateTvSeriesJob.php`, `RealGenerateTvShowJob.php`, ewentualnie Mock wersje |
| 4 | Pozostałe joby (Movie, Person) – trait cache/baseline; `OpenAiClient.php` – opcjonalna metoda pomocnicza |

---

## 5. Kryteria sukcesu

- Mniej zduplikowanego kodu (szczególnie locale/context, TvSeries vs TvShow).  
- Zachowanie zachowania: wszystkie istniejące testy (feature + jednostkowe) przechodzą.  
- Brak breaking changes w API (request/response, eventy, nazwy jobów).  
- Czytelność: nowy developer szybciej ogarnia „gdzie jest wspólna logika”, a gdzie różnice między encjami.

---

## 6. Czego nie robić

- Nie łączyć wszystkich czterech typów encji (Movie, Person, TvSeries, TvShow) w jeden generyczny job ani jeden „mega-service” – różnice (cast/crew, bio vs description, dwa tryby Movie) są na tyle duże, że wspólna abstrakcja byłaby krucha.  
- Nie refaktoryzować w jednym ogromnym PR – lepiej 2–4 mniejsze (np. Faza 1, Faza 2, Faza 3 osobno).  
- Nie zmieniać sygnatur publicznych eventów ani kontraktu API bez osobnego planu (breaking changes).
