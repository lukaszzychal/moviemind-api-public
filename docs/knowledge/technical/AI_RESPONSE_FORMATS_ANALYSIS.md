# Analiza formatów odpowiedzi AI - JSON Schema vs Tool Calling

> **Data utworzenia:** 2025-11-30  
> **Kontekst:** Analiza różnych formatów odpowiedzi AI w kontekście projektu MovieMind API  
> **Kategoria:** technical

## Cel

Przeanalizowanie dostępnych formatów odpowiedzi AI (JSON Schema, Tool Calling, Structured Outputs) i określenie optymalnego rozwiązania dla projektu MovieMind API.

## Dostępne formaty odpowiedzi AI w OpenAI API

### 1. JSON Schema (obecnie używany)

**Opis:**
Format wymuszający strukturę odpowiedzi JSON zgodnie z zdefiniowanym schematem. AI zwraca dane w dokładnie określonym formacie.

**Przykład użycia:**
```php
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Generate movie info for: the-matrix-1999'],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'movie_response',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'release_year' => ['type' => 'integer'],
                    'director' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'genres' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
];
```

**Odpowiedź:**
```json
{
    "title": "The Matrix",
    "release_year": 1999,
    "director": "Lana Wachowski",
    "description": "A computer hacker learns about the true nature of reality...",
    "genres": ["Action", "Sci-Fi"]
}
```

**Zalety:**
- ✅ Wymusza strukturę odpowiedzi
- ✅ Walidacja na poziomie API
- ✅ Przewidywalny format
- ✅ Łatwe parsowanie
- ✅ Wspierane przez większość modeli OpenAI

**Wady:**
- ❌ Ogranicza elastyczność AI
- ❌ Może wymagać retry jeśli AI nie może spełnić schematu
- ❌ Wymaga definicji schematu dla każdego typu odpowiedzi
- ❌ Problemy z Responses API (błąd 400 - parametr przeniesiony)

**Use case dla MovieMind API:**
- ✅ Generowanie danych filmów/osób (obecne użycie)
- ✅ Gdy potrzebujemy ściśle określonej struktury
- ✅ Gdy walidacja struktury jest krytyczna

**Kiedy używać:**
- Gdy struktura odpowiedzi jest zawsze taka sama
- Gdy potrzebujemy walidacji na poziomie API
- Gdy parsowanie musi być proste i przewidywalne

**Kiedy NIE używać:**
- Gdy struktura może się różnić w zależności od kontekstu
- Gdy potrzebujemy większej elastyczności od AI
- Gdy Responses API nie działa (obecny problem)

---

### 2. Tool Calling / Function Calling

**Opis:**
Mechanizm pozwalający AI wywoływać "funkcje" (tools) zdefiniowane przez użytkownika. AI decyduje kiedy i jakiej funkcji użyć.

**Przykład użycia:**
```php
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Generate movie info for: the-matrix-1999'],
    ],
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'generate_movie_data',
                'description' => 'Generate movie information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'release_year' => ['type' => 'integer'],
                        'director' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'genres' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['title', 'release_year'],
                ],
            ],
        ],
    ],
    'tool_choice' => 'required', // Wymusza użycie funkcji
];
```

**Odpowiedź:**
```json
{
    "id": "chatcmpl-123",
    "choices": [{
        "message": {
            "role": "assistant",
            "content": null,
            "tool_calls": [{
                "id": "call_abc123",
                "type": "function",
                "function": {
                    "name": "generate_movie_data",
                    "arguments": "{\"title\":\"The Matrix\",\"release_year\":1999,...}"
                }
            }]
        }
    }]
}
```

**Zalety:**
- ✅ Bardziej elastyczne niż JSON Schema
- ✅ AI może wybierać między różnymi funkcjami
- ✅ Możliwość łańcuchowego wywoływania funkcji
- ✅ Lepsze dla złożonych scenariuszy
- ✅ Działa z Chat Completions API (nie wymaga Responses API)

**Wady:**
- ❌ Wymaga dodatkowego parsowania `tool_calls`
- ❌ Więcej kodu do obsługi
- ❌ Może być overkill dla prostych przypadków
- ❌ AI może zdecydować nie użyć funkcji (jeśli `tool_choice` nie jest `required`)

**Use case dla MovieMind API:**
- ✅ Gdy potrzebujemy większej elastyczności
- ✅ Gdy chcemy pozwolić AI wybierać format odpowiedzi
- ✅ Gdy planujemy rozszerzyć funkcjonalność (np. weryfikacja istnienia, walidacja)

**Kiedy używać:**
- Gdy potrzebujemy wielu różnych typów odpowiedzi
- Gdy AI powinno decydować o formacie
- Gdy planujemy rozbudowę funkcjonalności

**Kiedy NIE używać:**
- Gdy potrzebujemy prostego, przewidywalnego formatu
- Gdy struktura jest zawsze taka sama
- Gdy nie potrzebujemy elastyczności

---

### 3. Structured Outputs (OpenAI)

**Opis:**
Nowy format wprowadzony przez OpenAI, który łączy zalety JSON Schema z lepszą obsługą błędów i walidacją.

**Przykład użycia:**
```php
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Generate movie info for: the-matrix-1999'],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'movie_response',
            'strict' => true, // Wymusza dokładne dopasowanie
            'schema' => [
                'type' => 'object',
                'properties' => [...],
            ],
        ],
    ],
];
```

**Zalety:**
- ✅ Lepsza walidacja niż standardowy JSON Schema
- ✅ Tryb `strict` wymusza dokładne dopasowanie
- ✅ Lepsze komunikaty błędów
- ✅ Wspierane przez nowsze modele

**Wady:**
- ❌ Wymaga nowszych modeli OpenAI
- ❌ Może nie być dostępne dla wszystkich modeli
- ❌ Podobne problemy jak JSON Schema z Responses API

**Use case dla MovieMind API:**
- ✅ Gdy potrzebujemy silnej walidacji
- ✅ Gdy używamy nowszych modeli OpenAI
- ✅ Gdy chcemy uniknąć błędów parsowania

---

### 4. Standard JSON Object (bez schematu)

**Opis:**
Prosty format wymuszający tylko JSON, bez określonej struktury.

**Przykład użycia:**
```php
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Generate movie info for: the-matrix-1999. Return JSON with: title, release_year, director, description, genres.'],
    ],
    'response_format' => ['type' => 'json_object'],
];
```

**Zalety:**
- ✅ Najprostszy format
- ✅ Działa z wszystkimi modelami
- ✅ Nie wymaga definicji schematu
- ✅ Elastyczny

**Wady:**
- ❌ Brak walidacji struktury
- ❌ AI może zwrócić nieprawidłową strukturę
- ❌ Wymaga ręcznej walidacji w kodzie
- ❌ Mniej przewidywalny

**Use case dla MovieMind API:**
- ✅ Fallback gdy inne formaty nie działają
- ✅ Prototypowanie
- ✅ Gdy struktura może się różnić

---

## Porównanie formatów

| Format | Walidacja | Elastyczność | Złożoność | Wsparcie API | Rekomendacja |
|--------|-----------|--------------|-----------|--------------|--------------|
| **JSON Schema** | ✅ Silna | ❌ Niska | 🟡 Średnia | ❌ Problemy z Responses API | ⚠️ Jeśli naprawimy API |
| **Tool Calling** | ✅ Średnia | ✅ Wysoka | 🔴 Wysoka | ✅ Dobre | ✅ Dla rozbudowanych funkcji |
| **Structured Outputs** | ✅ Bardzo silna | ❌ Niska | 🟡 Średnia | ⚠️ Zależy od modelu | ✅ Dla nowych modeli |
| **JSON Object** | ❌ Brak | ✅ Wysoka | 🟢 Niska | ✅ Dobre | ✅ Fallback |

---

## Analiza obecnego użycia w MovieMind API

### Obecny stan

**Lokalizacja:** `api/app/Services/OpenAiClient.php`

**Używany format:** JSON Schema z Responses API

**Problemy:**
- ❌ Błąd 400 "unsupported_parameter" z Responses API
- ❌ Format `response_format` nie jest wspierany (przeniesiony do `text.format`)
- ❌ Wymaga naprawy lub zmiany formatu

**Obecne schematy:**
- `movieResponseSchema()` - dla filmów
- `personResponseSchema()` - dla osób

---

## Rekomendacje dla MovieMind API

### Krótkoterminowe (natychmiastowe)

**Opcja 1: Naprawa Responses API**
- Przenieść `response_format` do `text.format` w strukturze `input`
- Zachować JSON Schema
- **Zalety:** Minimalne zmiany w kodzie
- **Wady:** Nadal zależność od Responses API

**Opcja 2: Przełączenie na Chat Completions API**
- Użyć standardowego `/v1/chat/completions`
- Zachować JSON Schema (działa z Chat Completions)
- **Zalety:** Stabilne API, dobrze udokumentowane
- **Wady:** Wymaga zmiany endpointu

**Opcja 3: Fallback na JSON Object**
- Tymczasowo użyć `response_format: {type: 'json_object'}`
- Dodać walidację w kodzie aplikacji
- **Zalety:** Działa natychmiast
- **Wady:** Brak walidacji struktury na poziomie API

**Rekomendacja:** Opcja 2 (Chat Completions API) - najbardziej stabilne rozwiązanie

### Średnioterminowe (1-2 miesiące)

**Rozważenie Tool Calling:**
- Jeśli planujemy rozbudowę funkcjonalności (weryfikacja istnienia, walidacja danych)
- Jeśli potrzebujemy większej elastyczności
- **Zalety:** Lepsze dla złożonych scenariuszy
- **Wady:** Więcej kodu do obsługi

### Długoterminowe (3-6 miesięcy)

**Structured Outputs:**
- Gdy przejdziemy na nowsze modele OpenAI
- Gdy potrzebujemy silniejszej walidacji
- **Zalety:** Najlepsza walidacja
- **Wady:** Wymaga nowszych modeli

---

## Przykłady implementacji

### Przykład 1: JSON Schema z Chat Completions API

```php
private function sendRequest(string $systemPrompt, string $userPrompt, array $jsonSchema)
{
    $payload = [
        'model' => $this->model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => $jsonSchema,
        ],
        'temperature' => self::DEFAULT_TEMPERATURE,
    ];

    return Http::timeout(self::DEFAULT_TIMEOUT)
        ->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.openai.com/v1/chat/completions', $payload);
}
```

### Przykład 2: Tool Calling

```php
private function sendRequestWithToolCalling(string $systemPrompt, string $userPrompt)
{
    $payload = [
        'model' => $this->model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'tools' => [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_movie_data',
                    'description' => 'Generate movie information from slug',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'release_year' => ['type' => 'integer'],
                            'director' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'genres' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['title', 'release_year'],
                    ],
                ],
            ],
        ],
        'tool_choice' => 'required',
        'temperature' => self::DEFAULT_TEMPERATURE,
    ];

    return Http::timeout(self::DEFAULT_TIMEOUT)
        ->withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.openai.com/v1/chat/completions', $payload);
}
```

---

## Wnioski

1. **Obecny format (JSON Schema z Responses API)** - wymaga naprawy
2. **Chat Completions API z JSON Schema** - najlepsze krótkoterminowe rozwiązanie
3. **Tool Calling** - warto rozważyć dla przyszłych rozszerzeń
4. **Structured Outputs** - do rozważenia gdy przejdziemy na nowsze modele

## Powiązane dokumenty

- [AI Validation and Hallucination Prevention](./AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
- [Task TASK-039](../../issue/pl/TASKS.md#task-039)
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)

---

**Ostatnia aktualizacja:** 2025-11-30

