# Porównanie: Luźne tablice vs DTO w monitorowaniu statusu jobów

> **Data utworzenia:** 2025-11-10  
> **Kontekst:** Analiza `JobStatusService` oraz ocena migracji przechowywanych statusów z tablic asocjacyjnych do obiektów DTO.  
> **Kategoria:** technical

## 🎯 Cel

Porównać aktualne podejście oparte na luźnych tablicach z propozycją wprowadzenia obiektów DTO dla statusów jobów (np. `ai_job:*` w Redis) i wskazać konsekwencje techniczne każdej opcji.

## 📋 Zawartość

### 1. Stan obecny – luźne tablice
- `JobStatusService` serializuje statusy jobów bezpośrednio do tablic (`initializeStatus`, `updateStatus`, `findActiveJobForSlug`).
- `array_merge` scala dotychczasowy stan z nowymi polami bez walidacji.
- Klucze (`status`, `entity`, `slug`, `requested_slug`, `locale`, `context_tag`, `error`, `entity_id`, `confidence`) są rozproszone po serwisie i wywołujących miejscach (np. `RealGenerateMovieJob`, `QueueMovieGenerationAction`).
- Odczytujący musi znać strukturę tablicy i samodzielnie pilnować typów.

### 2. Zalety podejścia tablicowego
- **Prostota implementacji** – brak dodatkowych klas, szybkie dopisanie kolejnych pól.
- **Elastyczność schematu** – łatwo przechowywać dowolne (również opcjonalne) wartości.
- **Minimalny narzut** – brak konwersji obiekt ↔ tablica, szczególnie przy częstych aktualizacjach cache.

### 3. Wady podejścia tablicowego
- **Brak kontroli typów i dozwolonych kluczy** – literówka lub nieprawidłowy typ nadpisze poprawne dane.
- **Ryzyko `array_merge`** – scalenie pustymi lub nieoczekiwanymi wartościami może „wyczyścić” status.
- **Trudniejsza ewolucja** – każda zmiana schematu wymaga przeszukania całego kodu i ręcznej synchronizacji pól.
- **Brak spójnych helperów** – logika walidacji i formatowania dubluje się w wielu miejscach.

### 4. DTO – potencjalne korzyści
- **Jawna struktura** – centralne zarządzanie polami (`JobStatusSnapshot::status()`, `::entityId()` itd.).
- **Walidacja** – konstruktor/`fromArray()` może wymuszać poprawne typy, statusy i wymagane pola.
- **Bezpieczne aktualizacje** – metody `withStatus()`, `merge()` mogą kontrolować dopuszczalne zmiany.
- **Lepsza czytelność i IDE support** – autouzupełnianie, brak „magicznych stringów”.
- **Możliwość rozszerzeń** – np. konwersja do API resource, logowanie zmian, metryki.

### 5. Koszty migracji na DTO
- **Nakład implementacyjny** – stworzenie klasy DTO, testów, refaktoryzacja miejsc użycia.
- **Wydajność** – dodatkowa konwersja obiekt ↔ tablica przy zapisie/odczycie cache (zwykle marginalna).
- **Kompatybilność** – trzeba zapewnić zgodność ze starymi wpisami w cache (`fromArray()` akceptujący brakujące pola).
- **Rozbudowa testów** – warto pokryć DTO testami jednostkowymi (walidacja, serializacja).

### 6. Rekomendacje dla MovieMind API
- Pozostań przy tablicach, jeśli:
  - struktura statusu jest stabilna i zmienia się rzadko,
  - krytyczne jest minimalne zużycie zasobów Redis/CPU,
  - kontrola typów odbywa się w innych warstwach (np. zasoby API).
- Rozważ DTO, gdy:
  - planowana jest dalsza rozbudowa statusów (np. śledzenie czasu życia, metadanych AI),
  - chcemy zredukować dług techniczny i literówki powtarzające się w kodzie,
  - statusy mają być konsumowane w wielu miejscach (API, dashboardy, raporty),
  - potrzebna jest walidacja reguł biznesowych (np. tylko określone przejścia statusów).
- Możliwy etap pośredni: wprowadzić DTO tylko w warstwie publicznego API serwisu (`getStatus(): ?JobStatusSnapshot`) i nadal magazynować tablice w Redis.

## 🔗 Powiązane Dokumenty
- `docs/knowledge/technical/STATUS_IMPLEMENTATION_REPORT.md`
- `docs/knowledge/technical/SUMMARY_STATUS_AND_RECOMMENDATIONS.md`
- `docs/knowledge/technical/QUEUE_ASYNC_EXPLANATION.md`

## 📌 Notatki
- Jeżeli zdecydujemy się na DTO, warto przygotować migrację danych w cache (czyszczenie starych wpisów po wdrożeniu).
- Dobrą praktyką będzie dodanie testów integracyjnych `JobStatusService`, aby potwierdzić brak regresji po ewentualnej migracji.

---

**Ostatnia aktualizacja:** 2025-11-10

