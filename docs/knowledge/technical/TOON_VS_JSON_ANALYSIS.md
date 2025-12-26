# Analiza formatu TOON vs JSON dla komunikacji z AI

> **Data utworzenia:** 2025-11-30  
> **Kontekst:** Analiza formatu TOON (Token-Oriented Object Notation) jako alternatywy dla JSON w komunikacji z AI  
> **Kategoria:** technical  
> **Źródło:** [TOON vs JSON: The New Format Designed for AI](https://dev.to/akki907/toon-vs-json-the-new-format-designed-for-ai-nk5)

## Cel

Przeanalizowanie formatu TOON (Token-Oriented Object Notation) jako alternatywy dla JSON w komunikacji z AI w projekcie MovieMind API. TOON może oszczędzać 30-60% tokenów w porównaniu do JSON, co przekłada się na znaczące oszczędności kosztów API.

## Czym jest TOON?

**TOON (Token-Oriented Object Notation)** to nowy format serializacji zaprojektowany specjalnie dla komunikacji z Large Language Models (LLM). Głównym celem TOON jest redukcja liczby tokenów potrzebnych do przekazania danych do AI, co bezpośrednio przekłada się na niższe koszty API.

### Problem z JSON

JSON jest standardowym formatem, ale dla AI ma wady:
- Powtarzanie kluczy dla każdego obiektu w tablicy
- Zbędne cudzysłowy i nawiasy
- Wysokie zużycie tokenów dla powtarzalnych struktur

**Przykład - JSON:**
```json
{
  "users": [
    { "id": 1, "name": "Alice", "role": "admin", "salary": 75000 },
    { "id": 2, "name": "Bob", "role": "user", "salary": 65000 },
    { "id": 3, "name": "Charlie", "role": "user", "salary": 70000 }
  ]
}
```
**Tokeny: 257**

### Rozwiązanie TOON

**Przykład - TOON:**
```
users[3]{id,name,role,salary}:
1,Alice,admin,75000
2,Bob,user,65000
3,Charlie,user,70000
```
**Tokeny: 166 (35% oszczędności)**

## Główne cechy TOON

### 1. Tabular Arrays - Deklaracja raz, użycie wiele razy

**Kluczowa idea:** Gdy mamy jednorodne tablice obiektów (te same pola, te same typy), po co powtarzać klucze dla każdego obiektu?

**JSON (powtarzalne):**
```json
[
  { "sku": "A1", "qty": 2, "price": 9.99 },
  { "sku": "B2", "qty": 1, "price": 14.50 }
]
```

**TOON (efektywne):**
```
[2]{sku,qty,price}:
A1,2,9.99
B2,1,14.5
```

Schemat jest zadeklarowany raz w nagłówku `{sku,qty,price}`, a każdy wiersz to tylko wartości w stylu CSV. To jest miejsce gdzie TOON błyszczy najbardziej.

### 2. Smart Quoting

TOON używa cudzysłowów tylko gdy absolutnie konieczne:

- `hello world` → Brak cudzysłowów (spacje wewnętrzne są OK)
- `hello 👋 world` → Brak cudzysłowów (Unicode jest bezpieczny)
- `"hello, world"` → Cudzysłowy wymagane (zawiera przecinek)
- `" padded "` → Cudzysłowy wymagane (spacje na początku/końcu)

To minimalne podejście do cudzysłowów oszczędza tokeny, zachowując jednoznaczność danych.

### 3. Indentation zamiast nawiasów

Podobnie jak YAML, TOON używa wcięć zamiast nawiasów klamrowych dla zagnieżdżonych struktur:

**JSON:**
```json
{
  "user": {
    "id": 123,
    "profile": {
      "name": "Ada"
    }
  }
}
```

**TOON:**
```
user:
  id: 123
  profile:
    name: Ada
```

Czytelniejsze, bardziej zwięzłe i mniej tokenów.

### 4. Explicit Array Lengths

TOON zawiera długość tablicy w nawiasach kwadratowych (`[N]`), co pomaga LLM zrozumieć i zwalidować strukturę:

```
tags[3]: admin,ops,dev
```

Ta jawna metadane redukuje błędy parsowania gdy LLM generuje lub interpretuje strukturalne dane.

## Benchmarki - Oszczędności tokenów

Według badań projektu TOON:

| Dataset | JSON Tokens | TOON Tokens | Oszczędności |
|---------|-------------|-------------|--------------|
| GitHub Repos (100 rekordów) | 15,145 | 8,745 | **42.3%** |
| Analytics (180 dni) | 10,977 | 4,507 | **58.9%** |
| E-commerce Orders | 257 | 166 | **35.4%** |

**Najlepsze wyniki:** Jednorodne dane tabelaryczne - rekordy ze spójnymi schematami w wielu wierszach. Im więcej powtarzających się kluczy JSON, tym więcej TOON może zoptymalizować.

### Zrozumienie przez LLM

Efektywność tokenów nie ma znaczenia jeśli LLM nie może zrozumieć formatu. Benchmarki przetestowały 4 różne modele (GPT-5 Nano, Claude Haiku, Gemini Flash, Grok) na 154 pytaniach o pobieranie danych:

- **Dokładność TOON:** 70.1%
- **Dokładność JSON:** 65.4%
- **Redukcja tokenów:** 46.3%

TOON nie tylko oszczędza tokeny, ale również poprawia dokładność zrozumienia przez AI!

## Przykłady dla MovieMind API

### Przykład 1: Dane filmu

**JSON:**
```json
{
  "title": "The Matrix",
  "release_year": 1999,
  "director": "Lana Wachowski",
  "description": "A computer hacker learns about the true nature of reality.",
  "genres": ["Action", "Sci-Fi"]
}
```
**Szacowane tokeny:** ~45

**TOON:**
```
title: The Matrix
release_year: 1999
director: Lana Wachowski
description: A computer hacker learns about the true nature of reality.
genres[2]: Action,Sci-Fi
```
**Szacowane tokeny:** ~35 (**~22% oszczędności**)

### Przykład 2: Lista filmów (tabular data)

**JSON:**
```json
{
  "movies": [
    { "title": "The Matrix", "year": 1999, "director": "Lana Wachowski" },
    { "title": "Inception", "year": 2010, "director": "Christopher Nolan" },
    { "title": "Interstellar", "year": 2014, "director": "Christopher Nolan" }
  ]
}
```
**Szacowane tokeny:** ~80

**TOON:**
```
movies[3]{title,year,director}:
The Matrix,1999,Lana Wachowski
Inception,2010,Christopher Nolan
Interstellar,2014,Christopher Nolan
```
**Szacowane tokeny:** ~50 (**~37% oszczędności**)

### Przykład 3: Dane osoby z biografią

**JSON:**
```json
{
  "name": "Keanu Reeves",
  "birth_date": "1964-09-02",
  "birthplace": "Beirut, Lebanon",
  "biography": "Keanu Charles Reeves is a Canadian actor..."
}
```
**Szacowane tokeny:** ~30

**TOON:**
```
name: Keanu Reeves
birth_date: 1964-09-02
birthplace: Beirut, Lebanon
biography: Keanu Charles Reeves is a Canadian actor...
```
**Szacowane tokeny:** ~25 (**~17% oszczędności**)

## Zalety TOON

### 1. Oszczędności tokenów
- ✅ 30-60% redukcji tokenów dla danych tabelarycznych
- ✅ Bezpośrednie oszczędności kosztów API
- ✅ Szybsze przetwarzanie (mniej tokenów = szybsze odpowiedzi)

### 2. Lepsze zrozumienie przez AI
- ✅ Wyższa dokładność parsowania (70.1% vs 65.4% dla JSON)
- ✅ Explicit array lengths pomagają w walidacji
- ✅ Czytelniejszy format dla AI

### 3. Czytelność
- ✅ Mniej "szumu" wizualnego niż JSON
- ✅ Podobny do YAML/CSV (znane formaty)
- ✅ Łatwiejszy do debugowania

## Wady TOON

### 1. Brak wsparcia w ekosystemie
- ❌ Nie jest standardowym formatem (jak JSON)
- ❌ Brak natywnego wsparcia w większości bibliotek
- ❌ Wymaga własnej implementacji parsera/serializatora

### 2. Ograniczenia dla złożonych struktur
- ❌ Najlepiej działa dla danych tabelarycznych
- ❌ Mniej efektywny dla głęboko zagnieżdżonych struktur
- ❌ Może być mniej czytelny dla bardzo złożonych danych

### 3. Koszt implementacji
- ❌ Wymaga implementacji konwertera JSON → TOON
- ❌ Wymaga implementacji parsera TOON → JSON
- ❌ Dodatkowe testy i utrzymanie

### 4. Kompatybilność
- ❌ Nie wszystkie LLM mogą równie dobrze rozumieć TOON
- ❌ Wymaga walidacji z używanym modelem AI
- ❌ Może wymagać dodatkowych promptów wyjaśniających format

## Use case'y dla MovieMind API

### ✅ Kiedy używać TOON

1. **Generowanie list filmów/osób**
   - Tabular data - idealne dla TOON
   - Duże oszczędności tokenów (30-50%)
   - Wysoka częstotliwość użycia

2. **Bulk operations**
   - Masowe generowanie opisów
   - Import danych
   - Synchronizacja z zewnętrznymi źródłami

3. **RAG (Retrieval Augmented Generation)**
   - Przesyłanie wielu podobnych rekordów jako kontekst
   - Oszczędności tokenów przy dużej liczbie rekordów

### ❌ Kiedy NIE używać TOON

1. **Pojedyncze obiekty**
   - Małe oszczędności (10-20%)
   - Nie warto komplikować dla małych zysków

2. **Głęboko zagnieżdżone struktury**
   - TOON najlepiej działa dla płaskich/tabularnych danych
   - Złożone zagnieżdżenia mogą być mniej czytelne

3. **Komunikacja z zewnętrznymi API**
   - Jeśli API wymaga JSON, nie ma sensu konwertować

## Analiza dla MovieMind API

### Obecne użycie JSON

**Lokalizacja:** `api/app/Services/OpenAiClient.php`

**Obecne dane:**
- Pojedyncze obiekty filmów/osób
- Strukturyzowane odpowiedzi z JSON Schema
- Relatywnie proste struktury

**Szacowane oszczędności:**
- Pojedyncze obiekty: **15-25%** oszczędności tokenów
- Listy filmów/osób: **35-50%** oszczędności tokenów
- Bulk operations: **40-60%** oszczędności tokenów

### Potencjalne zastosowania

1. **Generowanie opisów dla wielu filmów naraz**
   - Zamiast wielu wywołań API, jedno z listą filmów w TOON
   - Znaczne oszczędności przy bulk operations

2. **RAG - przesyłanie podobnych opisów jako kontekst**
   - Lista podobnych filmów w TOON jako kontekst
   - Oszczędności przy dużej liczbie rekordów

3. **Import danych z zewnętrznych źródeł**
   - Konwersja danych importowych do TOON przed wysłaniem do AI
   - Oszczędności przy dużych importach

## Rekomendacje

### Krótkoterminowe (1-2 miesiące)

**Opcja 1: Eksperyment z TOON dla list**
- Implementacja konwertera JSON → TOON dla tabularnych danych
- Testowanie z rzeczywistym API OpenAI
- Pomiar rzeczywistych oszczędności tokenów
- **Zalety:** Niskie ryzyko, możliwość weryfikacji
- **Wady:** Wymaga implementacji

**Opcja 2: Czekać na dojrzewanie formatu**
- Monitorować rozwój TOON
- Sprawdzić czy pojawią się biblioteki/parser
- **Zalety:** Mniej pracy teraz
- **Wady:** Możemy przegapić oszczędności

**Rekomendacja:** Opcja 1 - eksperyment z TOON dla list filmów/osób

### Średnioterminowe (3-6 miesięcy)

**Jeśli eksperyment się powiedzie:**
- Rozszerzyć użycie TOON na wszystkie tabularne dane
- Zaimplementować parser TOON → JSON dla odpowiedzi AI
- Dodać feature flag `ai_use_toon_format`
- Zaktualizować dokumentację

### Długoterminowe (6+ miesięcy)

**Jeśli TOON stanie się standardem:**
- Rozważyć pełną migrację na TOON dla komunikacji z AI
- Utrzymać JSON dla komunikacji z klientami API
- Zoptymalizować wszystkie ścieżki komunikacji z AI

## Implementacja

### Przykład konwertera JSON → TOON

```php
class ToonConverter
{
    public function convert(array $data): string
    {
        // Sprawdź czy to tabular array
        if ($this->isTabularArray($data)) {
            return $this->convertTabularArray($data);
        }
        
        // Dla zagnieżdżonych struktur użyj YAML-like format
        return $this->convertNested($data);
    }
    
    private function isTabularArray(array $data): bool
    {
        if (empty($data) || !isset($data[0])) {
            return false;
        }
        
        $firstKeys = array_keys($data[0]);
        
        // Sprawdź czy wszystkie elementy mają te same klucze
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            
            $keys = array_keys($item);
            if ($keys !== $firstKeys) {
                return false;
            }
            
            // Sprawdź czy wartości są prymitywne
            foreach ($item as $value) {
                if (is_array($value) || is_object($value)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function convertTabularArray(array $data): string
    {
        $count = count($data);
        $keys = array_keys($data[0]);
        $keysStr = implode(',', $keys);
        
        $rows = [];
        foreach ($data as $item) {
            $values = array_map(fn($key) => $this->escapeValue($item[$key]), $keys);
            $rows[] = implode(',', $values);
        }
        
        return "[{$count}]{{$keysStr}}:\n" . implode("\n", $rows);
    }
    
    private function escapeValue($value): string
    {
        if ($value === null) {
            return '';
        }
        
        $str = (string) $value;
        
        // Cudzysłowy tylko gdy konieczne
        if (str_contains($str, ',') || str_contains($str, '"') || 
            str_contains($str, "\n") || preg_match('/^\s|\s$/', $str)) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        
        return $str;
    }
}
```

### Przykład użycia w OpenAiClient

```php
private function sendRequestWithToon(string $systemPrompt, string $userPrompt, array $data): array
{
    $toonConverter = new ToonConverter();
    $toonData = $toonConverter->convert($data);
    
    $payload = [
        'model' => $this->model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt . "\n\nData in TOON format:\n" . $toonData
            ],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => self::DEFAULT_TEMPERATURE,
    ];
    
    // ... reszta implementacji
}
```

## Wnioski

1. **TOON oferuje znaczące oszczędności tokenów** (30-60%) dla danych tabelarycznych
2. **Lepsze zrozumienie przez AI** - wyższa dokładność parsowania
3. **Najlepiej działa dla tabularnych danych** - listy filmów, osób, etc.
4. **Wymaga implementacji** - brak natywnego wsparcia
5. **Warto przetestować** - potencjalne oszczędności są znaczące

## Powiązane dokumenty

- [TOON vs JSON vs CSV Analysis (rozszerzona analiza)](./TOON_VS_JSON_VS_CSV_ANALYSIS.md)
- [Format Comparison Article](./FORMAT_COMPARISON_ARTICLE.md)
- [AI Format Tutorial](../tutorials/AI_FORMAT_TUTORIAL.md)
- [TASK-040 Recommendations](../../issue/TASK_040_RECOMMENDATIONS.md)
- [AI Validation and Hallucination Prevention](./AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
- [Task TASK-040](../../issue/pl/TASKS.md#task-040)
- [TOON vs JSON: The New Format Designed for AI](https://dev.to/akki907/toon-vs-json-the-new-format-designed-for-ai-nk5)

---

## Aktualizacja: Rozszerzenie o CSV

**Data aktualizaji:** 2025-01-27

Analiza została rozszerzona o format CSV. Zobacz [TOON vs JSON vs CSV Analysis](./TOON_VS_JSON_VS_CSV_ANALYSIS.md) dla kompleksowej analizy wszystkich trzech formatów.

**Kluczowe wnioski z rozszerzonej analizy:**
- CSV ma poważne problemy z kontekstem kolumn w długich plikach
- CSV jest **NIEZALECANY** dla komunikacji z AI
- TOON wymaga testów z konkretnym modelem (LLM nie są trenowane na TOON)
- Bytes != Tokens (mniej bajtów nie zawsze oznacza mniej tokenów)

**Rekomendacja:** Rozważyć eksperyment z TOON dla list filmów/osób, ale przetestować przed pełnym wdrożeniem.

---

**Ostatnia aktualizacja:** 2025-01-27

