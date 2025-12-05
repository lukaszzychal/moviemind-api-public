# Porównanie podejść do weryfikacji istnienia encji przed generowaniem AI

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Szczegółowe porównanie różnych podejść do rozwiązania problemu weryfikacji istnienia encji  
> **Kategoria:** technical

## 🎯 Przegląd podejść

### 1. OpenAI Functions/Tools do wyszukiwania w zewnętrznych API

### 2. Retrieval-Augmented Generation (RAG) z własną bazą danych

### 3. Integracja z TMDb/IMDb API przed wywołaniem AI

---

## 1. OpenAI Functions/Tools do wyszukiwania w zewnętrznych API

### Opis

**OpenAI Functions/Tools** to mechanizm, który pozwala AI wywoływać zewnętrzne funkcje/API podczas generowania odpowiedzi. AI może samodzielnie zdecydować, kiedy i jak użyć funkcji do wyszukiwania informacji.

### Jak to działa

```
User Request → AI decides to search → Calls TMDb Function → Gets results → AI generates response
```

**Przepływ:**
1. Użytkownik: "Generate movie info for slug: bad-boys"
2. AI analizuje request i decyduje: "Potrzebuję sprawdzić czy film istnieje"
3. AI wywołuje funkcję `search_tmdb_movie(slug: "bad-boys")`
4. Funkcja zwraca dane z TMDb
5. AI używa tych danych do generacji odpowiedzi

### Przykład implementacji

```php
// Konfiguracja OpenAI Functions
$functions = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'search_tmdb_movie',
            'description' => 'Search for a movie in TMDb database',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'slug' => [
                        'type' => 'string',
                        'description' => 'Movie slug to search for'
                    ]
                ],
                'required' => ['slug']
            ]
        ]
    ]
];

// Wywołanie OpenAI z Functions
$response = $openai->chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'Generate movie info. Use search_tmdb_movie if needed.'],
        ['role' => 'user', 'content' => 'Generate movie info for slug: bad-boys']
    ],
    'tools' => $functions,
    'tool_choice' => 'auto' // AI decyduje czy użyć funkcji
]);

// AI może wywołać funkcję
if ($response->choices[0]->message->toolCalls) {
    foreach ($response->choices[0]->message->toolCalls as $toolCall) {
        if ($toolCall->function->name === 'search_tmdb_movie') {
            $slug = json_decode($toolCall->function->arguments)->slug;
            $tmdbResult = $this->tmdbClient->search()->movies($slug);
            // Zwróć wynik do AI
        }
    }
}
```

### Zalety

- ✅ **AI decyduje** - model sam wybiera, kiedy wyszukać informacje
- ✅ **Mniej zmian w kodzie** - głównie konfiguracja promptu
- ✅ **Elastyczność** - AI może użyć wielu funkcji w jednym wywołaniu
- ✅ **Inteligentne wyszukiwanie** - AI może poprawić slug przed wyszukiwaniem

### Wady

- ⚠️ **Wymaga OpenAI Functions/Tools** - może nie być dostępne w `gpt-4o-mini`
- ⚠️ **Więcej wywołań API** - każde wywołanie funkcji = dodatkowe koszty
- ⚠️ **Mniej kontroli** - nie kontrolujemy dokładnie kiedy AI wyszukuje
- ⚠️ **Złożoność** - wymaga obsługi tool calls i multiple round trips
- ⚠️ **Niezawodność** - AI może nie wywołać funkcji gdy powinien

### Wymagania

- OpenAI API z obsługą Functions/Tools
- Model który obsługuje Functions (np. gpt-4, gpt-4-turbo)
- Implementacja handlerów dla funkcji
- Obsługa multiple round trips (AI → Function → AI)

### Koszt

- **Wyższy** - więcej wywołań API (AI + Functions)
- Przykład: 1 request = 1 AI call + 1 TMDb call = ~$0.01-0.02

### Kiedy użyć

- Gdy potrzebujesz elastycznego, inteligentnego wyszukiwania
- Gdy AI powinien sam decydować o wyszukiwaniu
- Gdy masz dostęp do OpenAI Functions/Tools
- Gdy koszty nie są problemem

---

## 2. Retrieval-Augmented Generation (RAG) z własną bazą danych

### Opis

**RAG (Retrieval-Augmented Generation)** to technika, która łączy generowanie tekstu z odzyskiwaniem informacji z własnej bazy danych. System najpierw wyszukuje informacje w bazie, a potem używa ich jako kontekstu dla AI.

### Jak to działa

```
User Request → Search in DB → Get relevant data → Pass to AI as context → AI generates
```

**Przepływ:**
1. Użytkownik: "Generate movie info for slug: bad-boys"
2. System wyszukuje w własnej bazie danych (embedding search)
3. Znajduje podobne filmy lub informacje
4. Przekazuje znalezione dane jako kontekst do AI
5. AI generuje odpowiedź na podstawie kontekstu

### Przykład implementacji

```php
// 1. Przygotowanie danych (embedding)
$movieData = [
    'title' => 'Bad Boys',
    'year' => 1995,
    'director' => 'Michael Bay',
    // ...
];

// Konwersja do wektora (embedding)
$embedding = $this->embeddingService->createEmbedding(
    "Movie: Bad Boys (1995) by Michael Bay"
);

// Zapis w bazie wektorowej
$this->vectorDB->store('movie:bad-boys', $embedding, $movieData);

// 2. Wyszukiwanie (retrieval)
$query = "bad-boys";
$queryEmbedding = $this->embeddingService->createEmbedding($query);

// Wyszukaj podobne w bazie
$similar = $this->vectorDB->search($queryEmbedding, limit: 5);

// 3. Generowanie z kontekstem (augmentation)
$context = $this->formatContext($similar);

$prompt = "
Context from database:
{$context}

Generate movie info for slug: {$slug}
";

$response = $this->openAiClient->generate($prompt);
```

### Architektura

```
┌─────────────────────────────────────────┐
│         Vector Database                 │
│  (Embeddings of movies, people, etc.)   │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      Embedding Service                  │
│  (Converts text to vectors)             │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      RAG Service                        │
│  (Search + Context + AI Generation)     │
└─────────────────────────────────────────┘
```

### Zalety

- ✅ **Własna baza danych** - pełna kontrola nad danymi
- ✅ **Szybkie wyszukiwanie** - wektorowe wyszukiwanie jest szybkie
- ✅ **Aktualne dane** - możesz aktualizować bazę na bieżąco
- ✅ **Prywatność** - dane nie wychodzą poza system
- ✅ **Skalowalność** - można dodać wiele źródeł danych

### Wady

- ⚠️ **Wymaga przygotowania danych** - trzeba stworzyć bazę wektorową
- ⚠️ **Koszt embeddingów** - generowanie wektorów kosztuje
- ⚠️ **Złożoność** - wymaga vector database (np. Pinecone, Weaviate, PostgreSQL pgvector)
- ⚠️ **Maintenance** - trzeba aktualizować bazę danych
- ⚠️ **Nie weryfikuje istnienia** - tylko wyszukuje podobne, nie weryfikuje czy encja istnieje

### Wymagania

- Vector database (Pinecone, Weaviate, PostgreSQL z pgvector)
- Embedding model (OpenAI text-embedding-ada-002, lub inny)
- Service do generowania embeddingów
- Service do wyszukiwania wektorowego

### Koszt

- **Średni** - koszt embeddingów + AI generation
- Przykład: 1 request = 1 embedding ($0.0001) + 1 AI call ($0.01) = ~$0.01

### Kiedy użyć

- Gdy masz własną bazę danych z danymi
- Gdy potrzebujesz wyszukiwać podobne encje
- Gdy chcesz pełną kontrolę nad danymi
- Gdy prywatność jest ważna

---

## 3. Integracja z TMDb/IMDb API przed wywołaniem AI

### Opis

**Integracja z zewnętrznymi API** (TMDb, IMDb) do weryfikacji istnienia encji **przed** wywołaniem AI. System najpierw sprawdza czy encja istnieje w zewnętrznej bazie, a potem przekazuje dane do AI jako kontekst.

### Jak to działa

```
User Request → Check TMDb → Found? → Pass data to AI → AI generates
                ↓
            Not found → Return 404
```

**Przepływ:**
1. Użytkownik: "Generate movie info for slug: bad-boys"
2. System wyszukuje w TMDb API: `search/movie?query=bad-boys`
3. Jeśli znaleziono → przekazuje dane (title, year, director) do AI jako kontekst
4. Jeśli nie znaleziono → zwraca 404 od razu (bez wywołania AI)
5. AI generuje odpowiedź na podstawie danych z TMDb

### Przykład implementacji

```php
// 1. Weryfikacja w Controller
public function show(Request $request, string $slug): JsonResponse
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    if ($movie) {
        return $this->respondWithExistingMovie($movie);
    }

    if (!Feature::active('ai_description_generation')) {
        return response()->json(['error' => 'Movie not found'], 404);
    }

    // NOWE: Weryfikacja przed queue job
    $tmdbResult = $this->tmdbClient->search()->movies($slug);
    if (empty($tmdbResult['results'])) {
        return response()->json(['error' => 'Movie not found'], 404);
    }

    // Przekaż dane z TMDb do job
    $tmdbData = $tmdbResult['results'][0]; // Najlepszy match
    
    $result = $this->queueMovieGenerationAction->handle(
        $slug,
        locale: Locale::EN_US->value,
        tmdbData: $tmdbData // Kontekst dla AI
    );

    return response()->json($result, 202);
}

// 2. W Job - użyj danych z TMDb w prompt
private function createMovieRecord(OpenAiClientInterface $openAiClient): array
{
    // Przekaż dane z TMDb jako kontekst
    $context = $this->formatTmdbContext($this->tmdbData);
    
    $prompt = "
    Movie data from TMDb:
    {$context}
    
    Generate unique description for this movie.
    ";
    
    $aiResponse = $openAiClient->generateMovie($this->slug, $context);
    // ...
}
```

### Architektura

```
┌─────────────────────────────────────────┐
│         TMDb API                       │
│  (External movie database)              │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      TMDb Client Service                │
│  (Search, verify, get data)             │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      Controller                         │
│  (Verify before queue job)              │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│      AI Generation Job                 │
│  (Use TMDb data as context)             │
└─────────────────────────────────────────┘
```

### Zalety

- ✅ **Weryfikacja przed AI** - sprawdzamy istnienie przed kosztownym wywołaniem AI
- ✅ **Aktualne dane** - TMDb ma aktualne informacje o filmach
- ✅ **Rozwiązywanie niejednoznaczności** - możemy wybrać najlepszy match
- ✅ **Mniej kosztów** - nie wywołujemy AI dla nieistniejących encji
- ✅ **Lepsze prompty** - AI dostaje kontekst z TMDb (mniej halucynacji)
- ✅ **Prostota** - łatwa implementacja i utrzymanie

### Wady

- ⚠️ **Zależność od zewnętrznego API** - TMDb może być niedostępny
- ⚠️ **Rate limiting** - TMDb ma limity wywołań
- ⚠️ **Koszt czasu** - dodatkowe wywołanie API (ale szybkie)
- ⚠️ **Wymaga API key** - trzeba mieć klucz TMDb

### Wymagania

- TMDb API key (bezpłatny, z limitami)
- HTTP client do wywołań TMDb API
- Service do wyszukiwania i weryfikacji
- Cache dla wyników (opcjonalnie, ale zalecane)

### Koszt

- **Niski** - TMDb API jest bezpłatne (z limitami)
- Przykład: 1 request = 1 TMDb call (free) + 1 AI call ($0.01) = ~$0.01
- Z cache: 1 request = cache hit (free) + 1 AI call = ~$0.01

### Kiedy użyć

- ✅ **REKOMENDOWANE** - najlepsze rozwiązanie dla większości przypadków
- Gdy potrzebujesz weryfikacji istnienia przed generowaniem
- Gdy chcesz aktualne dane z zewnętrznej bazy
- Gdy chcesz prostą, niezawodną implementację
- Gdy koszty są ważne (mniej wywołań AI)

---

## 📊 Porównanie tabelaryczne

| Aspekt | OpenAI Functions | RAG z własną bazą | TMDb API Integration |
|--------|------------------|-------------------|----------------------|
| **Weryfikacja istnienia** | ✅ (przez AI) | ❌ (tylko podobne) | ✅ (przed AI) |
| **Aktualne dane** | ✅ | ⚠️ (zależy od aktualizacji) | ✅ |
| **Koszt** | 🔴 Wysoki | 🟡 Średni | 🟢 Niski |
| **Złożoność** | 🔴 Wysoka | 🔴 Wysoka | 🟢 Niska |
| **Kontrola** | ⚠️ (AI decyduje) | ✅ (pełna) | ✅ (pełna) |
| **Niezawodność** | ⚠️ (AI może nie wywołać) | ✅ | ✅ |
| **Czas implementacji** | 🔴 Długi | 🔴 Długi | 🟢 Krótki |
| **Maintenance** | 🟡 Średni | 🔴 Wysoki | 🟢 Niski |
| **Skalowalność** | ✅ | ✅ | ✅ |

## 🎯 Rekomendacja

### Dla MovieMind API: **TMDb API Integration** ✅

**Dlaczego:**
1. ✅ **Najprostsze** - łatwa implementacja i utrzymanie
2. ✅ **Najtańsze** - TMDb API jest bezpłatne
3. ✅ **Najszybsze** - weryfikacja przed kosztownym AI
4. ✅ **Najbardziej niezawodne** - pełna kontrola nad procesem
5. ✅ **Najlepsze dla MVP** - szybka implementacja, dobre wyniki

**Kiedy rozważyć inne podejścia:**
- **OpenAI Functions** - gdy potrzebujesz bardzo elastycznego wyszukiwania
- **RAG** - gdy masz własną bazę danych i chcesz wyszukiwać podobne encje

## 🔗 Powiązane dokumenty

- [`AI_MOVIE_VERIFICATION_PROBLEM.md`](./AI_MOVIE_VERIFICATION_PROBLEM.md)
- [`AI_VERIFICATION_ANALYSIS_ALL_TYPES.md`](./AI_VERIFICATION_ANALYSIS_ALL_TYPES.md)
- [TMDb API Documentation](https://www.themoviedb.org/documentation/api)
- [OpenAI Functions Documentation](https://platform.openai.com/docs/guides/function-calling)

---

**Ostatnia aktualizacja:** 2025-12-01

