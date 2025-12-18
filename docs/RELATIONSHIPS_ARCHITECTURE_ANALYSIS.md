# 🏗️ Analiza Architektury Relacji Filmów

**Data analizy:** 2025-01-XX  
**Problem:** Rozróżnienie między różnymi typami relacji i ich przechowywanie  
**Status:** 🔴 Wymaga decyzji architektonicznej

---

## 📋 Obecna Implementacja

### Typy Relacji (`RelationshipType` enum):

```php
enum RelationshipType: string
{
    case SEQUEL = 'SEQUEL';        // Kolejna część serii (np. Matrix 2)
    case PREQUEL = 'PREQUEL';      // Poprzednia część serii (np. Matrix 4 → Matrix 1)
    case REMAKE = 'REMAKE';        // Remake filmu
    case SERIES = 'SERIES';        // Część serii (pozycja neutralna)
    case SPINOFF = 'SPINOFF';      // Spin-off (np. Hobbit → Władca Pierścieni)
    case SAME_UNIVERSE = 'SAME_UNIVERSE'; // Podobne filmy (Similar Movies z TMDB)
}
```

### Źródła Relacji:

1. **Collection Relationships** (z TMDB Collections):
   - Typy: `SEQUEL`, `PREQUEL`, `SERIES`
   - Przykład: The Matrix Collection → The Matrix, The Matrix Reloaded, The Matrix Revolutions
   - **Charakterystyka:** Stałe, strukturalne relacje między filmami
   - **Zmienność:** Rzadko się zmieniają (tylko gdy TMDB dodaje nowy film do kolekcji)

2. **Similar Movies** (z TMDB Similar Movies API):
   - Typ: `SAME_UNIVERSE`
   - Przykład: The Matrix → Inception, Blade Runner 2049, Interstellar
   - **Charakterystyka:** Algorytmiczne rekomendacje oparte na:
     - Podobnych gatunkach
     - Podobnych aktorach/reżyserach
     - Popularności
     - Oceny użytkowników
   - **Zmienność:** Często się zmieniają (algorytm TMDB może się zmienić, nowe filmy wpływają na ranking)

---

## 🤔 Problem: Czy "Similar Movies" to relacje czy filtrowanie?

### Argumenty ZA przechowywaniem jako relacje:

✅ **Korzyści:**
- Szybki dostęp do powiązanych filmów bez zapytań do TMDB API
- Możliwość cache'owania wyników
- Spójność z Collection relationships (wszystko w jednym miejscu)
- Możliwość filtrowania po typie relacji w API (`/movies/{slug}/related?type=SAME_UNIVERSE`)

❌ **Problemy:**
- **Zmienność:** Similar Movies mogą się zmieniać w czasie (nowe filmy wpływają na ranking)
- **Staleness:** Dane mogą być nieaktualne (stare rekomendacje)
- **Nie są to prawdziwe relacje:** To są rekomendacje algorytmiczne, nie strukturalne powiązania
- **Efekt kaskadowy:** Tworzenie filmów tylko po to, żeby mieć "podobne" może prowadzić do kaskady

### Argumenty PRZECIW przechowywaniu jako relacje:

✅ **Korzyści:**
- Similar Movies to bardziej "filtrowanie/wyszukiwanie" niż relacje
- Mogą być generowane dynamicznie z TMDB API gdy potrzebne
- Nie zajmują miejsca w bazie danych
- Zawsze aktualne (pobierane na żądanie)
- Brak problemu z kaskadą (nie tworzymy filmów tylko dla podobnych)

❌ **Problemy:**
- Wymaga zapytań do TMDB API przy każdym wywołaniu `/related`
- Wolniejsze odpowiedzi (zależność od zewnętrznego API)
- Możliwe limity rate limiting TMDB API
- Brak cache'owania (lub potrzeba osobnego cache'u)

---

## 💡 Proponowane Rozwiązania

### Rozwiązanie 1: Rozdzielenie Collection i Similar Movies

**Koncepcja:**
- **Collection Relationships** → przechowywane w bazie (`movie_relationships` table)
- **Similar Movies** → generowane dynamicznie z TMDB API (lub cache'owane krótkoterminowo)

**Implementacja:**

```php
// W MovieController::related()
public function related(string $slug, Request $request): JsonResponse
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    
    $type = $request->input('type'); // 'collection', 'similar', 'all'
    
    $relationships = [];
    
    // Collection relationships - z bazy danych
    if ($type === null || $type === 'collection' || $type === 'all') {
        $relationships['collection'] = $movie->relatedMovies()
            ->whereIn('relationship_type', [
                RelationshipType::SEQUEL,
                RelationshipType::PREQUEL,
                RelationshipType::SERIES,
                RelationshipType::SPINOFF,
                RelationshipType::REMAKE,
            ])
            ->get();
    }
    
    // Similar movies - dynamicznie z TMDB API (z cache'em)
    if ($type === null || $type === 'similar' || $type === 'all') {
        $relationships['similar'] = $this->getSimilarMoviesFromTmdb($movie, limit: 10);
    }
    
    return response()->json([
        'movie' => new MovieResource($movie),
        'relationships' => $relationships,
    ]);
}

private function getSimilarMoviesFromTmdb(Movie $movie, int $limit = 10): array
{
    // Cache podobnych filmów na 24h (mogą się zmieniać, ale nie tak często)
    return Cache::remember(
        "movie_similar_{$movie->id}_{$limit}",
        now()->addHours(24),
        function () use ($movie, $limit) {
            $snapshot = $movie->tmdbSnapshot;
            if (!$snapshot) {
                return [];
            }
            
            $tmdbService = app(TmdbVerificationService::class);
            $movieDetails = $tmdbService->getMovieDetails($snapshot->tmdb_id);
            
            return array_slice($movieDetails['similar']['results'] ?? [], 0, $limit);
        }
    );
}
```

**Konsekwencje:**

✅ **Pozytywne:**
- Collection relationships są stałe i przechowywane w bazie
- Similar Movies są zawsze aktualne (z cache'em 24h)
- Brak efektu kaskady dla Similar Movies (nie tworzymy filmów tylko dla podobnych)
- Możliwość filtrowania po typie relacji

❌ **Negatywne:**
- Wymaga zapytań do TMDB API dla Similar Movies (ale z cache'em)
- Dwie różne ścieżki danych (baza vs API)

---

### Rozwiązanie 2: Similar Movies jako "soft relationships" z TTL

**Koncepcja:**
- Przechowywać Similar Movies w bazie, ale z `expires_at` timestamp
- Automatycznie odświeżać gdy wygasną

**Implementacja:**

```php
// Migration: add expires_at to movie_relationships
Schema::table('movie_relationships', function (Blueprint $table) {
    $table->timestamp('expires_at')->nullable()->after('order');
    $table->index('expires_at');
});

// W SyncMovieRelationshipsJob
private function syncSimilarMovies(...): void
{
    foreach ($similarMovies as $similarMovie) {
        // Tylko linkuj istniejące filmy, nie twórz nowych
        $relatedMovie = Movie::where('tmdb_id', $tmdbId)->first();
        if (!$relatedMovie) {
            continue; // Nie tworz filmów dla Similar Movies!
        }
        
        // Create relationship with expiration (30 days)
        MovieRelationship::updateOrCreate(
            [
                'movie_id' => $movie->id,
                'related_movie_id' => $relatedMovie->id,
                'relationship_type' => RelationshipType::SAME_UNIVERSE,
            ],
            [
                'expires_at' => now()->addDays(30), // Expire after 30 days
            ]
        );
    }
}

// Job to refresh expired similar movies
class RefreshExpiredSimilarMoviesJob implements ShouldQueue
{
    public function handle(): void
    {
        $expiredRelationships = MovieRelationship::where('relationship_type', RelationshipType::SAME_UNIVERSE)
            ->where('expires_at', '<', now())
            ->get();
        
        foreach ($expiredRelationships as $relationship) {
            // Re-sync similar movies for this movie
            SyncMovieRelationshipsJob::dispatch($relationship->movie_id);
        }
    }
}
```

**Konsekwencje:**

✅ **Pozytywne:**
- Wszystkie relacje w jednym miejscu (baza danych)
- Automatyczne odświeżanie starych danych
- Brak efektu kaskady (nie tworzymy filmów dla Similar Movies)
- Możliwość filtrowania po typie relacji

❌ **Negatywne:**
- Wymaga dodatkowego joba do odświeżania
- Złożoność zarządzania TTL
- Nadal przechowujemy dane, które mogą być nieaktualne przez 30 dni

---

### Rozwiązanie 3: Similar Movies tylko jako cache (nie w bazie)

**Koncepcja:**
- Collection relationships → baza danych (stałe)
- Similar Movies → tylko cache Redis/Memcached (TTL 24h)

**Implementacja:**

```php
// W MovieController::related()
public function related(string $slug, Request $request): JsonResponse
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    
    $type = $request->input('type');
    
    $relationships = [];
    
    // Collection - zawsze z bazy danych
    if ($type === null || $type === 'collection' || $type === 'all') {
        $relationships['collection'] = $movie->relatedMovies()
            ->whereIn('relationship_type', [
                RelationshipType::SEQUEL,
                RelationshipType::PREQUEL,
                RelationshipType::SERIES,
                RelationshipType::SPINOFF,
                RelationshipType::REMAKE,
            ])
            ->get();
    }
    
    // Similar - tylko z cache (nie przechowujemy w bazie)
    if ($type === null || $type === 'similar' || $type === 'all') {
        $relationships['similar'] = Cache::remember(
            "movie_similar_{$movie->id}",
            now()->addHours(24),
            function () use ($movie) {
                return $this->fetchSimilarMoviesFromTmdb($movie);
            }
        );
    }
    
    return response()->json([
        'movie' => new MovieResource($movie),
        'relationships' => $relationships,
    ]);
}
```

**Konsekwencje:**

✅ **Pozytywne:**
- Najprostsze rozwiązanie
- Collection relationships są stałe (baza)
- Similar Movies są zawsze aktualne (cache z TTL)
- Brak efektu kaskady
- Nie zaśmieca bazy danych

❌ **Negatywne:**
- Wymaga Redis/Memcached (ale już mamy Redis dla cache)
- Cache może być wyczyszczony (ale to OK, odświeży się automatycznie)

---

## 🔄 Co jeśli coś się zmieni w TMDB?

### Scenariusz 1: Nowy film dodany do kolekcji

**Przykład:** TMDB dodaje "The Matrix 5" do The Matrix Collection

**Obecne zachowanie:**
- ❌ Nie wykryje automatycznie
- ❌ Trzeba ręcznie wywołać `/movies/{slug}/refresh`

**Proponowane rozwiązanie:**

```php
// W SyncMovieRelationshipsJob - sprawdź czy kolekcja się zmieniła
private function syncCollectionRelationships(...): void
{
    $collectionData = $tmdbVerificationService->getCollectionDetails($collectionId);
    
    // Porównaj z snapshotem w bazie
    $snapshot = TmdbSnapshot::where('entity_type', 'COLLECTION')
        ->where('tmdb_id', $collectionId)
        ->first();
    
    if ($snapshot) {
        $oldParts = $snapshot->raw_data['parts'] ?? [];
        $newParts = $collectionData['parts'] ?? [];
        
        // Sprawdź czy dodano nowe filmy
        $oldIds = array_column($oldParts, 'id');
        $newIds = array_column($newParts, 'id');
        $addedIds = array_diff($newIds, $oldIds);
        
        if (!empty($addedIds)) {
            Log::info('New movies added to collection', [
                'collection_id' => $collectionId,
                'added_movie_ids' => $addedIds,
            ]);
            
            // Utwórz relacje dla nowych filmów
            // (ale nie tworz filmów automatycznie - tylko jeśli istnieją lokalnie)
        }
    }
    
    // Zaktualizuj snapshot kolekcji
    $tmdbVerificationService->saveSnapshot(
        'COLLECTION',
        null, // Collection nie ma lokalnego ID
        $collectionId,
        'collection',
        $collectionData
    );
}
```

### Scenariusz 2: Similar Movies się zmieniły

**Przykład:** TMDB zmienia algorytm rekomendacji, "Inception" nie jest już w top 10 podobnych do "The Matrix"

**Rozwiązanie 1 (Cache):**
- ✅ Automatycznie odświeży się po wygaśnięciu cache (24h)
- ✅ Brak problemu - cache się odświeży

**Rozwiązanie 2 (Baza z TTL):**
- ✅ Job `RefreshExpiredSimilarMoviesJob` odświeży po 30 dniach
- ⚠️ Może być nieaktualne przez 30 dni

**Rozwiązanie 3 (Tylko cache):**
- ✅ Automatycznie odświeży się po wygaśnięciu cache (24h)
- ✅ Najlepsze rozwiązanie dla Similar Movies

---

## 🎯 Rekomendacja

### Dla Collection Relationships:
✅ **Przechowywać w bazie danych** (`movie_relationships` table)
- Są stałe i strukturalne
- Rzadko się zmieniają
- Są to prawdziwe relacje między filmami

### Dla Similar Movies:
✅ **Rozwiązanie 3: Tylko cache (nie w bazie)**
- To są rekomendacje algorytmiczne, nie relacje
- Mogą się zmieniać często
- Nie powinny powodować efektu kaskady
- Cache z TTL 24h zapewnia aktualność

### Implementacja:

1. **Usuń tworzenie filmów dla Similar Movies:**
   ```php
   // W SyncMovieRelationshipsJob::syncSimilarMovies()
   // Tylko linkuj istniejące filmy, nie twórz nowych
   $relatedMovie = Movie::where('tmdb_id', $tmdbId)->first();
   if (!$relatedMovie) {
       continue; // Pomiń, nie twórz filmów dla Similar Movies
   }
   ```

2. **Usuń przechowywanie Similar Movies w bazie:**
   - Nie zapisuj `SAME_UNIVERSE` relationships w `movie_relationships`
   - Używaj tylko cache dla Similar Movies

3. **Dodaj endpoint z filtrowaniem:**
   ```php
   GET /api/v1/movies/{slug}/related?type=collection  // Tylko collection
   GET /api/v1/movies/{slug}/related?type=similar     // Tylko similar (z cache)
   GET /api/v1/movies/{slug}/related                  // Oba (domyślnie)
   ```

---

## 📊 Porównanie Rozwiązań

| Aspekt | Rozwiązanie 1 (Rozdzielenie) | Rozwiązanie 2 (TTL w bazie) | Rozwiązanie 3 (Tylko cache) |
|--------|------------------------------|----------------------------|----------------------------|
| **Collection** | Baza danych ✅ | Baza danych ✅ | Baza danych ✅ |
| **Similar** | Cache + API ✅ | Baza z TTL ⚠️ | Tylko cache ✅ |
| **Aktualność** | 24h cache ✅ | 30 dni TTL ⚠️ | 24h cache ✅ |
| **Efekt kaskady** | Brak ✅ | Brak ✅ | Brak ✅ |
| **Złożoność** | Średnia | Wysoka | Niska ✅ |
| **Zajętość bazy** | Niska ✅ | Średnia | Najniższa ✅ |

---

## 🚀 Plan Implementacji

1. ✅ **Krok 1:** Usuń tworzenie filmów dla Similar Movies
2. ✅ **Krok 2:** Zmień `syncSimilarMovies()` aby tylko linkować istniejące filmy
3. ✅ **Krok 3:** Dodaj cache dla Similar Movies w `MovieController::related()`
4. ✅ **Krok 4:** Usuń zapisywanie `SAME_UNIVERSE` w bazie (lub oznacz jako deprecated)
5. ✅ **Krok 5:** Dodaj filtrowanie `?type=collection|similar|all` w endpoint `/related`
6. ✅ **Krok 6:** Dodaj job do odświeżania Collection snapshots (opcjonalnie)

---

**Ostatnia aktualizacja:** 2025-01-XX  
**Autor:** AI Assistant (Claude)  
**Status:** 🔴 Wymaga decyzji i implementacji

