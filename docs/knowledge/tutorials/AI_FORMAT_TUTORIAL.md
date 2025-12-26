# Tutorial: Formaty danych dla komunikacji z AI (JSON, TOON, CSV)

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Pełny tutorial od podstaw do implementacji  
> **Kategoria:** tutorials  
> **Zadanie:** TASK-040  
> **Poziom:** Od podstaw do zaawansowanego

---

## Spis treści

1. [Podstawy](#część-1-podstawy)
2. [Szczegółowa analiza formatów](#część-2-szczegółowa-analiza-formatów)
3. [Porównanie praktyczne](#część-3-porównanie-praktyczne)
4. [Implementacja w MovieMind API](#część-4-implementacja-w-moviemind-api)
5. [Best Practices](#część-5-best-practices)

---

## Część 1: Podstawy

### Czym są formaty danych w kontekście AI?

Format danych to sposób reprezentacji informacji w formie tekstowej, który może być zrozumiany zarówno przez ludzi, jak i przez maszyny (w tym AI).

**Przykład:** Ta sama informacja może być przedstawiona na różne sposoby:

**JSON:**
```json
{"title": "The Matrix", "year": 1999}
```

**TOON:**
```
title: The Matrix
year: 1999
```

**CSV:**
```csv
title,year
The Matrix,1999
```

### Dlaczego format ma znaczenie (koszty tokenów)

Gdy wysyłasz dane do LLM (Large Language Model), każdy znak jest konwertowany na **tokeny**. Tokeny to jednostki, za które płacisz.

**Przykład kosztów:**
- GPT-4: ~$0.03 za 1K tokenów input, ~$0.06 za 1K tokenów output
- GPT-4o-mini: ~$0.15 za 1M tokenów input, ~$0.60 za 1M tokenów output

**Dlaczego to ważne:**
- Jeśli wysyłasz 1000 filmów do AI, różnica między formatami może oznaczać setki dolarów miesięcznie
- Przykład: 1000 filmów w JSON = ~25,000 tokenów, w TOON = ~15,000 tokenów
- Oszczędność: 10,000 tokenów × $0.15/1M = $1.50 na jednej operacji
- Przy 100 operacjach/miesiąc = $150 oszczędności

### Wprowadzenie do JSON, TOON, CSV

#### JSON (JavaScript Object Notation)

**Czym jest:**
- Standardowy format wymiany danych
- Używany w większości API
- Wspierany przez wszystkie języki programowania

**Przykład:**
```json
{
  "title": "The Matrix",
  "release_year": 1999,
  "director": "The Wachowskis"
}
```

**Kiedy używać:**
- Komunikacja z API
- Gdy potrzebna pewność parsowania
- Gdy potrzebne zagnieżdżone struktury

#### TOON (Token-Oriented Object Notation)

**Czym jest:**
- Nowy format zaprojektowany specjalnie dla AI
- Oszczędza 30-60% tokenów vs JSON
- Łączy zalety JSON (struktura) i CSV (kompaktowość)

**Przykład:**
```
title: The Matrix
release_year: 1999
director: The Wachowskis
```

**Kiedy używać:**
- Listy obiektów z tymi samymi polami
- Gdy oszczędność tokenów jest ważna
- Tabularne dane

#### CSV (Comma-Separated Values)

**Czym jest:**
- Prosty format tabelaryczny
- Używany w Excel, Google Sheets
- Najmniejszy rozmiar dla czystych tabel

**Przykład:**
```csv
title,release_year,director
The Matrix,1999,The Wachowskis
```

**Kiedy używać:**
- Eksport do arkuszy kalkulacyjnych
- Import z zewnętrznych źródeł
- **NIE używaj do komunikacji z AI** (ma problemy z kontekstem)

---

## Część 2: Szczegółowa analiza formatów

### JSON: Struktura, zalety, wady, przykłady

#### Struktura JSON

JSON składa się z:
- **Obiekty:** `{"key": "value"}`
- **Tablice:** `["item1", "item2"]`
- **Wartości:** string, number, boolean, null

**Przykład zagnieżdżonej struktury:**
```json
{
  "movie": {
    "title": "The Matrix",
    "cast": [
      {"name": "Keanu Reeves", "role": "Neo"},
      {"name": "Laurence Fishburne", "role": "Morpheus"}
    ]
  }
}
```

#### Zalety JSON

✅ **Uniwersalne wsparcie**
- Wspierany przez wszystkie języki
- Standardowy format dla API
- Natywne parsery

✅ **Czytelność**
- Łatwy do odczytania przez ludzi
- Strukturalny i hierarchiczny

✅ **Trening LLM**
- LLM są intensywnie trenowane na JSON
- Wysoka dokładność parsowania (65.4%)

✅ **Elastyczność**
- Wspiera zagnieżdżone struktury
- Typowanie danych

#### Wady JSON

❌ **Wysokie zużycie tokenów**
- Powtarzanie kluczy dla każdego obiektu
- Zbędne cudzysłowy, nawiasy

❌ **Verbose dla tabularnych danych**
- Dla list obiektów z tymi samymi polami, JSON marnuje tokeny

#### Kiedy używać JSON

✅ **Pojedyncze obiekty**
- Generowanie opisu dla jednego filmu/osoby
- Małe oszczędności nie są warte komplikacji

✅ **Zagnieżdżone struktury**
- Dane z wieloma poziomami zagnieżdżenia
- TOON/CSV nie obsługują dobrze zagnieżdżeń

✅ **Komunikacja z zewnętrznymi API**
- Jeśli API wymaga JSON

#### Kiedy NIE używać JSON

❌ **Duże tabularne dane**
- Dla list >50 obiektów z tymi samymi polami
- Rozważyć TOON dla oszczędności

❌ **Gdy oszczędność tokenów jest krytyczna**
- Jeśli koszty API są wysokie

### TOON: Struktura, zalety, wady, przykłady

#### Struktura TOON

TOON używa:
- **Tabular arrays:** `[N]{key1,key2}:` + wiersze wartości
- **Indentation:** Wcięcia zamiast nawiasów
- **Smart quoting:** Cudzysłowy tylko gdy konieczne

**Przykład tabular array:**
```
movies[3]{title,year,director}:
The Matrix,1999,The Wachowskis
Inception,2010,Christopher Nolan
Interstellar,2014,Christopher Nolan
```

**Przykład zagnieżdżonej struktury:**
```
movie:
  title: The Matrix
  cast[2]{name,role}:
  Keanu Reeves,Neo
  Laurence Fishburne,Morpheus
```

#### Zalety TOON

✅ **Oszczędności tokenów**
- 30-60% redukcji tokenów dla danych tabelarycznych
- Bezpośrednie oszczędności kosztów

✅ **Lepsze zrozumienie przez AI (w niektórych przypadkach)**
- Wyższa dokładność parsowania w niektórych benchmarkach (70.1%)

✅ **Czytelność**
- Mniej "szumu" wizualnego niż JSON
- Podobny do YAML/CSV

#### Wady TOON

❌ **Brak wsparcia w ekosystemie**
- Wymaga własnej implementacji parsera

❌ **Ograniczenia dla złożonych struktur**
- Najlepiej działa dla danych tabelarycznych

❌ **KRYTYCZNE: LLM nie są trenowane na TOON**
- ⚠️ **WAŻNE:** Przed użyciem przetestować czy model rozumie TOON
- Może wymagać dodatkowych promptów

❌ **Bytes != Tokens**
- Mniej bajtów nie zawsze oznacza mniej tokenów
- Należy mierzyć rzeczywiste tokeny

#### Kiedy używać TOON

✅ **Listy filmów/osób (tabularne dane)**
- Tabular data - idealne dla TOON
- Duże oszczędności tokenów (30-50%)

✅ **Bulk operations**
- Masowe generowanie opisów
- Import danych

✅ **RAG (Retrieval Augmented Generation)**
- Przesyłanie wielu podobnych rekordów jako kontekst

#### Kiedy NIE używać TOON

❌ **Głęboko zagnieżdżone struktury**
- TOON najlepiej działa dla płaskich/tabularnych danych

❌ **Gdy LLM nie jest trenowany na TOON**
- ⚠️ **KRYTYCZNE:** Przed użyciem przetestować
- Jeśli model ma problemy, pozostać przy JSON

❌ **Komunikacja z zewnętrznymi API**
- Jeśli API wymaga JSON

#### WAŻNE: Analiza treningu LLM na TOON vs JSON

**JSON:**
- ✅ Intensywnie trenowany (wszystkie modele)
- ✅ Wysoka dokładność (65.4%)
- ✅ Model rozumie strukturę bez dodatkowych wyjaśnień

**TOON:**
- ⚠️ **NIE trenowany** (nowy format)
- ⚠️ Wymaga walidacji z konkretnym modelem
- ⚠️ Może wymagać dodatkowych promptów

**Rekomendacja:** Przed użyciem TOON w produkcji, przetestować z konkretnym modelem (np. gpt-4o-mini).

### CSV: Struktury, zalety, wady, przykłady

#### Struktura CSV

CSV składa się z:
- **Nagłówek:** Pierwsza linia z nazwami kolumn
- **Wiersze danych:** Każdy wiersz to wartości oddzielone przecinkami

**Przykład:**
```csv
title,release_year,director
The Matrix,1999,The Wachowskis
Inception,2010,Christopher Nolan
```

#### Warianty CSV

**Standardowy CSV:**
- Separator: przecinek (`,`)
- Cudzysłowy dla wartości ze spacjami/przecinkami

**TSV (Tab-Separated Values):**
- Separator: tabulacja (`\t`)
- Używany gdy wartości mogą zawierać przecinki

#### Zalety CSV

✅ **Minimalny rozmiar**
- Najmniejszy format dla czystych tabel
- Brak zbędnych znaków

✅ **Prostota**
- Bardzo prosty format
- Łatwy do wygenerowania

✅ **Szerokie wsparcie**
- Wspierany przez wszystkie narzędzia (Excel, Google Sheets)

#### Wady CSV

❌ **KRYTYCZNE: Problem z kontekstem kolumn**
- ⚠️ **POWAŻNY PROBLEM:** Im dalej od nagłówka, LLM traci kontekst kolumn
- CSV jest widziany jako długi ciąg danych bez struktury
- Dla długich plików (>100 wierszy) może prowadzić do błędów

❌ **Brak struktury**
- Brak typowania danych
- Brak zagnieżdżonych struktur

❌ **Wymaga dokładnych promptów**
- Musisz dokładnie opisać kolumny w promptach
- LLM może źle zinterpretować dane bez kontekstu

#### Kiedy używać CSV

✅ **Eksport danych do Excel/Google Sheets**
- CSV jest standardowym formatem dla arkuszy

✅ **Import danych z zewnętrznych źródeł**
- Jeśli źródło dostarcza dane w CSV

✅ **Bardzo proste dane tabelaryczne (<10 wierszy)**
- Dla bardzo krótkich list problem z kontekstem nie występuje

#### Kiedy NIE używać CSV

❌ **Komunikacja z AI (długie listy)**
- ⚠️ **NIEZALECANY** dla list >50 wierszy
- Problem z kontekstem kolumn
- Wymaga bardzo dokładnych promptów

❌ **Gdy potrzebna struktura**
- CSV nie ma struktury
- Wszystko jest stringiem

❌ **Gdy dane zawierają przecinki/cudzysłowy**
- Trudne do parsowania
- Może prowadzić do błędów

#### WAŻNE: Problem z kontekstem kolumn w długich plikach CSV

**Przykład problemu:**

```csv
title,release_year,director
The Matrix,1999,The Wachowskis
Inception,2010,Christopher Nolan
... (50 więcej wierszy) ...
Interstellar,2014,Christopher Nolan
```

**Problem:** Gdy LLM przetwarza 50. wiersz, może nie pamiętać że:
- Pierwsza kolumna = `title`
- Druga kolumna = `release_year`
- Trzecia kolumna = `director`

**Konsekwencje:**
- Błędna interpretacja danych
- Błędne odpowiedzi AI
- Trudne do debugowania

**Rozwiązanie:** Użyj bardziej strukturalnego formatu (JSON/TOON).

---

## Część 3: Porównanie praktyczne

### Benchmarki dla rzeczywistych danych MovieMind

#### Pojedynczy film

**Dane:**
- Tytuł: "The Matrix"
- Rok: 1999
- Reżyser: "The Wachowskis"
- Opis: "A computer hacker learns about the true nature of reality."
- Gatunki: ["Action", "Sci-Fi"]

**JSON:**
```json
{
  "title": "The Matrix",
  "release_year": 1999,
  "director": "The Wachowskis",
  "description": "A computer hacker learns about the true nature of reality.",
  "genres": ["Action", "Sci-Fi"]
}
```
**Tokeny:** ~45

**TOON:**
```
title: The Matrix
release_year: 1999
director: The Wachowskis
description: A computer hacker learns about the true nature of reality.
genres[2]: Action,Sci-Fi
```
**Tokeny:** ~35 (**22% oszczędności**)

**CSV:**
```csv
title,release_year,director,description,genres
The Matrix,1999,The Wachowskis,"A computer hacker learns about the true nature of reality.","Action,Sci-Fi"
```
**Tokeny:** ~30 (**33% oszczędności**)

**Wniosek:** Dla pojedynczych obiektów oszczędności są umiarkowane (20-30%).

#### Lista 10 filmów

**JSON:** ~250 tokenów  
**TOON:** ~150 tokenów (**40% oszczędności**)  
**CSV:** ~120 tokenów (**52% oszczędności**)

**Wniosek:** Dla list oszczędności są znaczące (40-50%).

#### Lista 100 filmów

**JSON:** ~2,500 tokenów  
**TOON:** ~1,500 tokenów (**40% oszczędności**)  
**CSV:** ~1,200 tokenów (**52% oszczędności**, ale ⚠️ **problem z kontekstem**)

**Wniosek:** CSV oferuje największe oszczędności, ale ma poważny problem z kontekstem dla długich list.

### Analiza "bytes vs tokens"

#### Dlaczego mniej bajtów nie zawsze oznacza mniej tokenów?

**Tokenizacja zależy od tokenizera:**
- Różne modele używają różnych tokenizerów
- GPT-4 używa tiktoken, Claude używa własnego tokenizera
- Ta sama sekwencja znaków może być tokenizowana inaczej

**Tokenizacja jest semantyczna:**
- Tokenizer rozbija tekst na znaczące jednostki (słowa, części słów, znaki specjalne)
- Przykład: `"title"` może być 1 tokenem, ale `"title,"` może być 2 tokenami

**Format wpływa na tokenizację:**
- JSON: `{"title":"The Matrix"}` - tokenizator widzi strukturę
- TOON: `title: The Matrix` - tokenizator widzi tekst
- CSV: `The Matrix` - tokenizator widzi tylko wartość

#### Jak weryfikować?

1. **Użyć tokenizera modelu**
   - Dla GPT-4: użyć `tiktoken`
   - Dla Claude: użyć tokenizera Claude
   - Zmierzyć rzeczywiste tokeny, nie tylko bajty

2. **Przetestować z rzeczywistymi danymi**
   - Wysłać dane w różnych formatach do API
   - Porównać rzeczywiste zużycie tokenów (z odpowiedzi API)
   - Zweryfikować czy oszczędności są rzeczywiste

### Testy czytelności przez LLM

#### JSON
- ✅ **Intensywnie trenowany:** Wszystkie główne LLM są trenowane na JSON
- ✅ **Wysoka dokładność:** 65.4% dokładność parsowania w benchmarkach
- ✅ **Zrozumienie struktury:** Model rozumie strukturę bez dodatkowych wyjaśnień

#### TOON
- ⚠️ **NIE trenowany:** LLM nie są trenowane na TOON
- ⚠️ **Wymaga walidacji:** Należy przetestować czy konkretny model dobrze rozumie TOON
- ⚠️ **Może wymagać dodatkowych promptów:** Może być konieczne wyjaśnienie formatu

#### CSV
- ⚠️ **Częściowo trenowany:** LLM widzą CSV w treningu, ale głównie jako dane tabelaryczne
- ⚠️ **Problem z kontekstem:** Im dalej od nagłówka, model traci kontekst kolumn
- ⚠️ **Wymaga dokładnych promptów:** Musisz dokładnie opisać kolumny w promptach

### Testy dokładności parsowania

**Metodologia:**
1. Przygotuj testowe dane (10-20 filmów)
2. Wyślij do API w różnych formatach
3. Porównaj dokładność parsowania (czy AI poprawnie zrozumiało dane)

**Oczekiwane wyniki:**
- JSON: 95-100% dokładność
- TOON: 90-95% dokładność (wymaga testów)
- CSV: 70-85% dokładność (problem z kontekstem)

### Analiza problemów z kontekstem (szczególnie dla CSV)

**Problem:** Dla długich plików CSV, LLM traci kontekst kolumn.

**Test:**
1. Przygotuj CSV z 100 wierszami
2. Poproś AI o interpretację 50. wiersza
3. Sprawdź czy AI poprawnie zidentyfikowało kolumny

**Oczekiwany wynik:** AI może błędnie zinterpretować kolumny dla wierszy dalekich od nagłówka.

**Rozwiązanie:** Użyj bardziej strukturalnego formatu (JSON/TOON).

---

## Część 4: Implementacja w MovieMind API

### Analiza obecnego kodu

**Plik:** `api/app/Services/OpenAiClient.php`

**Obecne użycie:**
- JSON do komunikacji z OpenAI API
- Pojedyncze obiekty (jeden film/osoba na raz)
- Brak bulk operations

**Przykład obecnego kodu:**
```php
$systemPrompt = "You are a movie database assistant. Return JSON with: title, release_year, director, description.";
$userPrompt = "Generate movie information for slug: {$slug}. Return JSON with: title, release_year, director, description.";
```

**Możliwości optymalizacji:**
- Dla list filmów/osób można użyć TOON
- Oszczędności 30-50% tokenów dla tabularnych danych

### Przykład konwertera JSON → TOON

**Plik:** `api/app/Services/ToonConverter.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

class ToonConverter
{
    /**
     * Convert array data to TOON format.
     *
     * @param  array<string, mixed>  $data
     * @return string TOON formatted string
     */
    public function convert(array $data): string
    {
        // Check if it's a tabular array (array of objects with same keys)
        if ($this->isTabularArray($data)) {
            return $this->convertTabularArray($data);
        }
        
        // For nested structures use YAML-like format
        return $this->convertNested($data);
    }
    
    /**
     * Check if array is tabular (array of objects with same structure).
     *
     * @param  array<string, mixed>  $data
     * @return bool
     */
    private function isTabularArray(array $data): bool
    {
        if (empty($data) || !isset($data[0])) {
            return false;
        }
        
        $firstKeys = array_keys($data[0]);
        
        // Check if all elements have the same keys
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            
            $keys = array_keys($item);
            if ($keys !== $firstKeys) {
                return false;
            }
            
            // Check if values are primitive (not nested)
            foreach ($item as $value) {
                if (is_array($value) || is_object($value)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Convert tabular array to TOON format.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return string
     */
    private function convertTabularArray(array $data): string
    {
        $count = count($data);
        $keys = array_keys($data[0]);
        $keysStr = implode(',', $keys);
        
        $rows = [];
        foreach ($data as $item) {
            $values = array_map(fn($key) => $this->escapeValue($item[$key] ?? ''), $keys);
            $rows[] = implode(',', $values);
        }
        
        return "[{$count}]{{$keysStr}}:\n" . implode("\n", $rows);
    }
    
    /**
     * Convert nested structure to TOON format (YAML-like).
     *
     * @param  array<string, mixed>  $data
     * @param  int  $indent
     * @return string
     */
    private function convertNested(array $data, int $indent = 0): string
    {
        $lines = [];
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isNumericArray($value)) {
                    // Array of values
                    $values = array_map(fn($v) => $this->escapeValue($v), $value);
                    $lines[] = "{$prefix}{$key}[".count($value)."]: ".implode(',', $values);
                } else {
                    // Nested object
                    $lines[] = "{$prefix}{$key}:";
                    $lines[] = $this->convertNested($value, $indent + 1);
                }
            } else {
                $lines[] = "{$prefix}{$key}: ".$this->escapeValue($value);
            }
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Check if array is numeric (list) vs associative (object).
     *
     * @param  array<int|string, mixed>  $array
     * @return bool
     */
    private function isNumericArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
    
    /**
     * Escape value for TOON format.
     *
     * @param  mixed  $value
     * @return string
     */
    private function escapeValue($value): string
    {
        if ($value === null) {
            return '';
        }
        
        $str = (string) $value;
        
        // Quotes only when necessary
        if (str_contains($str, ',') || str_contains($str, '"') || 
            str_contains($str, "\n") || preg_match('/^\s|\s$/', $str)) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        
        return $str;
    }
}
```

### Przykład konwertera JSON → CSV

**Plik:** `api/app/Services/CsvConverter.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

class CsvConverter
{
    /**
     * Convert array data to CSV format.
     *
     * @param  array<string, mixed>  $data
     * @param  string  $key  Key containing array of rows
     * @return string CSV formatted string
     */
    public function convert(array $data, string $key = 'data'): string
    {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new \InvalidArgumentException("Data must contain array under key '{$key}'");
        }
        
        $rows = $data[$key];
        if (empty($rows)) {
            return '';
        }
        
        // Get headers from first row
        $headers = array_keys($rows[0]);
        
        // Build CSV
        $csv = [];
        $csv[] = implode(',', $headers); // Header row
        
        foreach ($rows as $row) {
            $values = array_map(fn($key) => $this->escapeCsvValue($row[$key] ?? ''), $headers);
            $csv[] = implode(',', $values);
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Escape value for CSV format.
     *
     * @param  mixed  $value
     * @return string
     */
    private function escapeCsvValue($value): string
    {
        if ($value === null) {
            return '';
        }
        
        $str = (string) $value;
        
        // Escape if contains comma, quote, or newline
        if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n")) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        
        return $str;
    }
}
```

### Integracja z OpenAI API

**Przykład użycia w OpenAiClient:**

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

### Testy jednostkowe

**Plik:** `api/tests/Unit/Services/ToonConverterTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ToonConverter;
use Tests\TestCase;

class ToonConverterTest extends TestCase
{
    private ToonConverter $converter;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new ToonConverter();
    }
    
    public function test_converts_tabular_array_to_toon(): void
    {
        $data = [
            ['title' => 'The Matrix', 'year' => 1999, 'director' => 'The Wachowskis'],
            ['title' => 'Inception', 'year' => 2010, 'director' => 'Christopher Nolan'],
        ];
        
        $result = $this->converter->convert($data);
        
        $this->assertStringContainsString('[2]{title,year,director}:', $result);
        $this->assertStringContainsString('The Matrix,1999,The Wachowskis', $result);
        $this->assertStringContainsString('Inception,2010,Christopher Nolan', $result);
    }
    
    public function test_escapes_values_with_commas(): void
    {
        $data = [
            ['title' => 'Movie, The', 'year' => 1999],
        ];
        
        $result = $this->converter->convert($data);
        
        $this->assertStringContainsString('"Movie, The"', $result);
    }
    
    // ... więcej testów
}
```

---

## Część 5: Best Practices

### Kiedy używać którego formatu

**JSON:**
- Pojedyncze obiekty
- Zagnieżdżone struktury
- Komunikacja z zewnętrznymi API
- Gdy pewność jest ważniejsza niż oszczędności

**TOON:**
- Listy obiektów z tymi samymi polami
- Bulk operations
- RAG (Retrieval Augmented Generation)
- Gdy oszczędność tokenów jest ważna

**CSV:**
- Eksport do arkuszy kalkulacyjnych
- Import z zewnętrznych źródeł
- **NIE używaj do komunikacji z AI**

### Optymalizacja promptów

1. **Dla JSON:**
   - Standardowe prompty działają dobrze
   - Model rozumie strukturę bez dodatkowych wyjaśnień

2. **Dla TOON:**
   - Może wymagać wyjaśnienia formatu w system prompt
   - Przykład: "Data is provided in TOON format. TOON uses tabular arrays like [N]{keys}: followed by rows of comma-separated values."

3. **Dla CSV:**
   - **Wymaga bardzo dokładnego opisu kolumn**
   - Przykład: "Data is provided in CSV format. Columns are: title (string), release_year (integer), director (string)."

### Obsługa błędów parsowania

1. **Walidacja przed wysłaniem**
   - Sprawdź czy dane są poprawne
   - Waliduj strukturę przed konwersją

2. **Fallback do JSON**
   - Jeśli TOON nie działa, wróć do JSON
   - Użyj feature flag dla bezpiecznego rollbacku

3. **Logowanie błędów**
   - Loguj błędy parsowania
   - Monitoruj dokładność parsowania

### Monitoring i metryki

**Metryki do śledzenia:**
1. **Zużycie tokenów**
   - Porównaj tokeny przed/po zmianie formatu
   - Śledź oszczędności

2. **Dokładność parsowania**
   - Śledź błędy parsowania
   - Porównaj jakość odpowiedzi

3. **Czas odpowiedzi**
   - Mniej tokenów = szybsze odpowiedzi
   - Śledź czas przetwarzania

4. **Koszty**
   - Śledź koszty API przed/po zmianie
   - Oblicz ROI (Return on Investment)

### Testowanie przed implementacją

**Kroki testowania:**

1. **Przygotuj testowe dane**
   - Lista 10-20 filmów w JSON
   - Konwertuj do TOON i CSV
   - Zmierz rzeczywiste tokeny (używając tokenizera)

2. **Wyślij do API w różnych formatach**
   - JSON (baseline)
   - TOON
   - CSV (opcjonalnie)

3. **Porównaj wyniki**
   - Rzeczywiste zużycie tokenów
   - Dokładność parsowania
   - Jakość odpowiedzi AI

4. **Decyzja**
   - Jeśli oszczędności >30% i dokładność porównywalna → wdrożyć TOON
   - Jeśli oszczędności <30% lub dokładność spada → pozostać przy JSON

### Common Pitfalls

1. **Zakładanie że mniej bajtów = mniej tokenów**
   - Zawsze mierz rzeczywiste tokeny

2. **Używanie TOON bez testów**
   - Przetestuj przed wdrożeniem
   - Miej plan rollbacku

3. **Używanie CSV dla długich list**
   - Użyj TOON lub JSON dla list >50 wierszy

4. **Ignorowanie problemu z kontekstem**
   - Przetestuj z długimi listami
   - Sprawdź dokładność parsowania

---

## Podsumowanie

### Kluczowe wnioski

1. **JSON** - nadal najlepszy dla interoperacyjności i pewności
2. **TOON** - obiecujący dla tabularnych danych, ale wymaga testów
3. **CSV** - **NIEZALECANY** dla komunikacji z AI

### Następne kroki

1. Przetestuj TOON z gpt-4o-mini
2. Zmierz rzeczywiste oszczędności tokenów
3. Zdecyduj czy wdrożyć TOON w MovieMind API

---

## Powiązane dokumenty

- [TOON vs JSON vs CSV Analysis](../technical/TOON_VS_JSON_VS_CSV_ANALYSIS.md)
- [Format Comparison Article](../technical/FORMAT_COMPARISON_ARTICLE.md)
- [TASK-040 Recommendations](../../issue/TASK_040_RECOMMENDATIONS.md)

## Przykłady kodu

Pełne, działające przykłady implementacji konwerterów i parserów:

- [ToonConverter.php](../technical/examples/ToonConverter.php) - Konwerter JSON → TOON
- [ToonParser.php](../technical/examples/ToonParser.php) - Parser TOON → JSON
- [CsvConverter.php](../technical/examples/CsvConverter.php) - Konwerter JSON → CSV (⚠️ NIEZALECANY dla AI)
- [CsvParser.php](../technical/examples/CsvParser.php) - Parser CSV → JSON
- [OpenAiClientIntegration.php](../technical/examples/OpenAiClientIntegration.php) - Przykład integracji z OpenAiClient

---

**Ostatnia aktualizacja:** 2025-01-27

