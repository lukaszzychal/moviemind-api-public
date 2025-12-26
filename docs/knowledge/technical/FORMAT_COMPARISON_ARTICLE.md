# TOON vs JSON vs CSV: Który format jest najlepszy dla LLM?

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Artykuł porównawczy formatów komunikacji z AI  
> **Kategoria:** technical  
> **Zadanie:** TASK-040  
> **Audience:** Technical + Non-technical

---

## Sekcja 1: Executive Summary (dla Non-Technical)

### Krótkie porównanie formatów

Jeśli pracujesz z AI i wysyłasz dane do modeli językowych (jak GPT-4, Claude), format danych ma ogromne znaczenie dla:
- **Kosztów** - każdy token kosztuje pieniądze
- **Jakości odpowiedzi** - format wpływa na to, jak dobrze AI rozumie dane
- **Szybkości** - mniej tokenów = szybsze przetwarzanie

### Trzy główne formaty

1. **JSON** - standardowy format, używany wszędzie
2. **TOON** - nowy format zaprojektowany specjalnie dla AI
3. **CSV** - prosty format tabelaryczny

### Który wybrać?

**Krótka odpowiedź:**
- **JSON** - jeśli potrzebujesz pewności i kompatybilności
- **TOON** - jeśli chcesz oszczędzić 30-50% kosztów dla list danych
- **CSV** - **NIE używaj** do komunikacji z AI (ma poważne problemy)

### Korzyści biznesowe

**Oszczędności kosztów:**
- TOON może oszczędzić 30-60% tokenów dla danych tabelarycznych
- Dla dużych operacji (1000+ rekordów) to może oznaczać setki dolarów miesięcznie
- Przykład: Jeśli wydajesz $1000/miesiąc na API, TOON może zaoszczędzić $300-600

**Jakość odpowiedzi:**
- JSON: Najwyższa pewność parsowania (65.4% dokładność)
- TOON: Może mieć wyższą dokładność (70.1%), ale wymaga testów
- CSV: Najniższa pewność, problemy z kontekstem

### Rekomendacja wysokopoziomowa

**Dla MovieMind API:**
1. **Krótkoterminowo:** Pozostać przy JSON dla pewności
2. **Średnioterminowo:** Przetestować TOON dla list filmów/osób
3. **Długoterminowo:** Jeśli testy się powiodą, rozważyć migrację na TOON

**CSV nie jest rekomendowany** ze względu na problemy z kontekstem kolumn.

---

## Sekcja 2: Technical Deep Dive (dla Developers)

### Szczegółowe porównanie formatów

#### JSON - Obecny Standard

**Struktura:**
```json
{
  "movies": [
    { "title": "The Matrix", "year": 1999, "director": "The Wachowskis" },
    { "title": "Inception", "year": 2010, "director": "Christopher Nolan" }
  ]
}
```

**Charakterystyka:**
- **Tokeny:** ~80 dla 2 filmów
- **Czytelność:** Wysoka (ludzie i maszyny)
- **Wsparcie:** Uniwersalne
- **Trening LLM:** Intensywny (wszystkie modele)
- **Dokładność parsowania:** 65.4%

**Zalety:**
- Uniwersalne wsparcie w ekosystemie
- Wysoka pewność parsowania
- Wspiera zagnieżdżone struktury
- Łatwe do debugowania

**Wady:**
- Wysokie zużycie tokenów (powtarzanie kluczy)
- Verbose dla tabularnych danych

#### TOON - Nowy Format dla AI

**Struktura:**
```
movies[2]{title,year,director}:
The Matrix,1999,The Wachowskis
Inception,2010,Christopher Nolan
```

**Charakterystyka:**
- **Tokeny:** ~50 dla 2 filmów (37% oszczędności)
- **Czytelność:** Średnia (podobna do YAML/CSV)
- **Wsparcie:** Ograniczone (wymaga własnej implementacji)
- **Trening LLM:** **BRAK** (ważne ograniczenie!)
- **Dokładność parsowania:** 70.1% (w niektórych benchmarkach)

**Zalety:**
- Znaczne oszczędności tokenów (30-60%)
- Lepsze zrozumienie przez AI (w niektórych przypadkach)
- Czytelniejszy niż JSON dla tabularnych danych

**Wady:**
- **KRYTYCZNE:** LLM nie są trenowane na TOON
- Wymaga walidacji z konkretnym modelem
- Bytes != Tokens (mniej bajtów nie zawsze = mniej tokenów)
- Brak wsparcia w ekosystemie

#### CSV - Prosty Format Tabelaryczny

**Struktura:**
```csv
title,year,director
The Matrix,1999,The Wachowskis
Inception,2010,Christopher Nolan
```

**Charakterystyka:**
- **Tokeny:** ~40 dla 2 filmów (50% oszczędności)
- **Czytelność:** Niska (brak struktury)
- **Wsparcie:** Uniwersalne (Excel, Google Sheets)
- **Trening LLM:** Częściowy (głównie jako dane tabelaryczne)
- **Dokładność parsowania:** Nieznana (wymaga testów)

**Zalety:**
- Najmniejszy rozmiar dla czystych tabel
- Szerokie wsparcie w narzędziach
- Prosty format

**Wady:**
- **KRYTYCZNE:** Problem z kontekstem kolumn (im dalej od nagłówka, LLM traci kontekst)
- Brak struktury i typowania
- Wymaga bardzo dokładnych promptów
- Może prowadzić do błędów interpretacji

### Benchmarki tokenów

#### Pojedynczy obiekt

| Format | Tokeny | Oszczędności vs JSON |
|--------|--------|----------------------|
| JSON | ~45 | Baseline |
| TOON | ~35 | 22% |
| CSV | ~30 | 33% |

**Wniosek:** Dla pojedynczych obiektów oszczędności są umiarkowane (20-30%).

#### Lista 10 obiektów

| Format | Tokeny | Oszczędności vs JSON |
|--------|--------|----------------------|
| JSON | ~250 | Baseline |
| TOON | ~150 | 40% |
| CSV | ~120 | 52% |

**Wniosek:** Dla list oszczędności są znaczące (40-50%).

#### Lista 100 obiektów

| Format | Tokeny | Oszczędności vs JSON | Problem z kontekstem |
|--------|--------|----------------------|----------------------|
| JSON | ~2,500 | Baseline | Brak |
| TOON | ~1,500 | 40% | Brak |
| CSV | ~1,200 | 52% | ⚠️ **TAK** |

**Wniosek:** CSV oferuje największe oszczędności, ale ma poważny problem z kontekstem.

### Analiza wydajności parsowania

#### JSON
- **Czas parsowania:** Szybki (natywne parsery)
- **Dokładność:** 65.4% (w benchmarkach)
- **Pewność:** Wysoka (wszystkie modele są trenowane na JSON)

#### TOON
- **Czas parsowania:** Średni (wymaga własnego parsera)
- **Dokładność:** 70.1% (w niektórych benchmarkach, ale wymaga walidacji)
- **Pewność:** Średnia (model może nie być trenowany na TOON)

#### CSV
- **Czas parsowania:** Szybki (prosty format)
- **Dokładność:** Nieznana (wymaga testów)
- **Pewność:** Niska (problem z kontekstem kolumn)

### Przykłady implementacji

#### Konwerter JSON → TOON (PHP)

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

#### Konwerter JSON → CSV (PHP)

```php
class CsvConverter
{
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

---

## Sekcja 3: Use Case Analysis

### Kiedy używać JSON

✅ **Pojedyncze obiekty**
- Generowanie opisu dla jednego filmu/osoby
- Małe oszczędności (10-20%) nie są warte komplikacji
- Wysoka pewność parsowania

✅ **Zagnieżdżone struktury**
- Dane z wieloma poziomami zagnieżdżenia
- TOON/CSV nie obsługują dobrze zagnieżdżeń

✅ **Komunikacja z zewnętrznymi API**
- Jeśli API wymaga JSON, nie ma sensu konwertować

✅ **Gdy pewność jest ważniejsza niż oszczędności**
- Dla krytycznych operacji
- Gdy błędy parsowania są kosztowne

### Kiedy używać TOON

✅ **Listy filmów/osób (tabularne dane)**
- Tabular data - idealne dla TOON
- Duże oszczędności tokenów (30-50%)
- Wysoka częstotliwość użycia

✅ **Bulk operations**
- Masowe generowanie opisów
- Import danych
- Synchronizacja z zewnętrznymi źródłami

✅ **RAG (Retrieval Augmented Generation)**
- Przesyłanie wielu podobnych rekordów jako kontekst
- Oszczędności tokenów przy dużej liczbie rekordów

⚠️ **WAŻNE:** Przed implementacją przetestować czy gpt-4o-mini dobrze rozumie TOON!

### Kiedy używać CSV

❌ **NIE używaj CSV do komunikacji z AI**
- Problem z kontekstem kolumn w długich plikach
- Wymaga bardzo dokładnych promptów
- Ryzyko błędów interpretacji

✅ **Tylko jeśli konieczne:**
- Eksport danych do Excel/Google Sheets
- Import danych z zewnętrznych źródeł (w formacie CSV)
- Bardzo proste dane tabelaryczne (<10 wierszy)

### Kiedy NIE używać którego formatu

#### JSON - Kiedy NIE używać

❌ **Duże tabularne dane**
- Dla list >50 obiektów z tymi samymi polami, JSON marnuje tokeny
- Rozważyć TOON dla oszczędności

❌ **Gdy oszczędność tokenów jest krytyczna**
- Jeśli koszty API są wysokie, rozważyć TOON

#### TOON - Kiedy NIE używać

❌ **Głęboko zagnieżdżone struktury**
- TOON najlepiej działa dla płaskich/tabularnych danych
- Złożone zagnieżdżenia mogą być mniej czytelne

❌ **Gdy LLM nie jest trenowany na TOON**
- ⚠️ **KRYTYCZNE:** Przed użyciem przetestować czy model rozumie TOON
- Jeśli model ma problemy z parsowaniem, pozostać przy JSON

❌ **Komunikacja z zewnętrznymi API**
- Jeśli API wymaga JSON, nie ma sensu konwertować

#### CSV - Kiedy NIE używać

❌ **Komunikacja z AI (długie listy)**
- ⚠️ **NIEZALECANY** dla list >50 wierszy
- Problem z kontekstem kolumn
- Wymaga bardzo dokładnych promptów

❌ **Gdy potrzebna struktura**
- CSV nie ma struktury
- Wszystko jest stringiem
- Brak typowania

❌ **Gdy dane zawierają przecinki/cudzysłowy**
- Trudne do parsowania
- Może prowadzić do błędów

### Matryca decyzyjna

| Use Case | JSON | TOON | CSV | Rekomendacja |
|----------|------|------|-----|--------------|
| Pojedynczy obiekt | ✅ | ⚠️ | ❌ | **JSON** |
| Lista 10-50 obiektów | ⚠️ | ✅ | ❌ | **TOON** (po testach) |
| Lista >50 obiektów | ⚠️ | ✅ | ❌ | **TOON** (po testach) |
| Zagnieżdżone struktury | ✅ | ❌ | ❌ | **JSON** |
| Komunikacja z API | ✅ | ❌ | ❌ | **JSON** |
| Eksport do Excel | ⚠️ | ❌ | ✅ | **CSV** |
| Import z zewnętrznych źródeł | ⚠️ | ❌ | ⚠️ | Zależy od źródła |

**Legenda:**
- ✅ = Dobry wybór
- ⚠️ = Możliwy, ale nie idealny
- ❌ = Niezalecany

---

## Sekcja 4: Critical Warnings

### Problem z kontekstem w CSV

**Opis problemu:**
Gdy wysyłasz długi plik CSV do LLM, model przetwarza dane sekwencyjnie. Im dalej od nagłówka, model traci kontekst kolumn.

**Przykład:**
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

**Rozwiązanie:**
- Użyj bardziej strukturalnego formatu (JSON/TOON)
- Lub dodaj nagłówek przed każdymi 20-30 wierszami (ale to zwiększa tokeny)

### LLM nie są trenowane na TOON

**Opis problemu:**
Wszystkie główne LLM (GPT-4, Claude, Gemini) są intensywnie trenowane na JSON. TOON jest nowym formatem i modele **NIE** są na nim trenowane.

**Konsekwencje:**
- Model może nie rozumieć struktury TOON
- Może wymagać dodatkowych wyjaśnień w promptach
- Może prowadzić do błędów parsowania

**Rozwiązanie:**
- Przetestować TOON z konkretnym modelem (np. gpt-4o-mini)
- Dodać wyjaśnienie formatu w system prompt
- Monitorować dokładność parsowania
- Mieć plan rollbacku (feature flag)

### Bytes != Tokens

**Opis problemu:**
Mniej bajtów w pliku **NIE zawsze** oznacza mniej tokenów. Tokenizacja zależy od tokenizera używanego przez model.

**Przykład:**
- JSON: `{"title":"The Matrix"}` - 25 bajtów, ~8 tokenów
- TOON: `title: The Matrix` - 19 bajtów, ~6 tokenów
- CSV: `The Matrix` - 10 bajtów, ~3 tokeny

W tym przypadku mniej bajtów = mniej tokenów, ale **nie zawsze tak jest!**

**Konsekwencje:**
- Możesz myśleć że oszczędzasz tokeny, ale w rzeczywistości nie
- Rzeczywiste oszczędności mogą być mniejsze niż oczekiwane

**Rozwiązanie:**
- Użyć tokenizera modelu (np. `tiktoken` dla GPT-4)
- Zmierzyć rzeczywiste tokeny, nie tylko bajty
- Przetestować z rzeczywistymi danymi

### Praktyczne ostrzeżenia z community

**Z LinkedIn (Jung Hoon Son, M.D.):**
> "This whole TOON vs. JSON vs. CSV thing tells me how little people actually work with data and LLMs."
> 
> 1. **CSV:** Is problematic because further you get from header line, LLM does not know what the row represents
> 2. **JSON:** It works but it has a lot of tokens
> 3. **TOON:** It's just getting rid of JSON's extra bracket/tokens + has some level of "parquet" esque metadata

**Z Medium (Mehul Gupta):**
> "If your priority is **compatibility**: pick JSON  
> If your priority is **size**: pick CSV.  
> If your priority is **feeding structured data into an LLM efficiently**: pick TOON."

**Z AI Plain English:**
> "TOON doesn't replace JSON or CSV. It fills a gap that the other two never tried to solve. Think of it as your 'LLM-friendly' format, not your storage or API format."

---

## Sekcja 5: Implementation Guide

### Przykłady konwerterów (PHP)

Pełne, działające przykłady implementacji:

- [ToonConverter.php](./examples/ToonConverter.php) - Konwerter JSON → TOON
- [ToonParser.php](./examples/ToonParser.php) - Parser TOON → JSON
- [CsvConverter.php](./examples/CsvConverter.php) - Konwerter JSON → CSV (⚠️ NIEZALECANY dla AI)
- [CsvParser.php](./examples/CsvParser.php) - Parser CSV → JSON
- [OpenAiClientIntegration.php](./examples/OpenAiClientIntegration.php) - Przykład integracji z OpenAiClient

Zobacz również sekcję 2 (Technical Deep Dive) dla szczegółowych przykładów kodu w tekście.

### Integracja z OpenAI API

#### Przykład użycia TOON w OpenAiClient

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

### Testowanie przed implementacją

**Kroki testowania:**

1. **Przygotuj testowe dane**
   - Lista 10-20 filmów w JSON
   - Konwertuj do TOON i CSV
   - Zmierz rzeczywiste tokeny (używając tokenizera)

2. **Wyślij do API w różnych formatach**
   - JSON (baseline)
   - TOON
   - CSV (opcjonalnie, dla porównania)

3. **Porównaj wyniki**
   - Rzeczywiste zużycie tokenów
   - Dokładność parsowania
   - Jakość odpowiedzi AI

4. **Decyzja**
   - Jeśli oszczędności >30% i dokładność porównywalna → wdrożyć TOON
   - Jeśli oszczędności <30% lub dokładność spada → pozostać przy JSON

### Best Practices

1. **Zawsze mierz rzeczywiste tokeny**
   - Nie polegaj tylko na rozmiarze w bajtach
   - Użyj tokenizera modelu

2. **Testuj z konkretnym modelem**
   - Różne modele mogą różnie tokenizować
   - Przetestuj z modelem używanym w produkcji

3. **Użyj feature flag**
   - Dodać `ai_use_toon_format` feature flag
   - Umożliwić szybki rollback jeśli coś pójdzie nie tak

4. **Monitoruj dokładność**
   - Śledź błędy parsowania
   - Porównuj jakość odpowiedzi przed/po zmianie

5. **Zacznij od małych danych**
   - Przetestuj na małych listach (10-20 obiektów)
   - Stopniowo zwiększaj rozmiar

### Common Pitfalls i jak ich unikać

#### Pitfall 1: Zakładanie że mniej bajtów = mniej tokenów

**Problem:** Możesz myśleć że CSV oszczędza 50% tokenów, ale w rzeczywistości może być tylko 30%.

**Rozwiązanie:** Zawsze mierz rzeczywiste tokeny używając tokenizera.

#### Pitfall 2: Używanie TOON bez testów

**Problem:** TOON może nie działać dobrze z Twoim modelem.

**Rozwiązanie:** Przetestuj przed wdrożeniem. Miej plan rollbacku.

#### Pitfall 3: Używanie CSV dla długich list

**Problem:** CSV ma problem z kontekstem kolumn dla długich list.

**Rozwiązanie:** Użyj TOON lub JSON dla list >50 wierszy.

#### Pitfall 4: Ignorowanie problemu z kontekstem

**Problem:** Możesz nie zdawać sobie sprawy że CSV traci kontekst.

**Rozwiązanie:** Przetestuj z długimi listami i sprawdź dokładność parsowania.

---

## Podsumowanie

### Główne wnioski

1. **JSON** - nadal najlepszy dla interoperacyjności i pewności
2. **TOON** - obiecujący dla tabularnych danych, ale wymaga testów
3. **CSV** - **NIEZALECANY** dla komunikacji z AI

### Rekomendacja dla MovieMind API

**Krótkoterminowo:** Pozostać przy JSON dla pewności.

**Średnioterminowo:** Przetestować TOON dla list filmów/osób z:
- Pomiar rzeczywistych oszczędności tokenów
- Testy dokładności parsowania
- Feature flag dla bezpiecznego rollbacku

**Długoterminowo:** Jeśli testy się powiodą, rozważyć migrację na TOON dla tabularnych danych.

---

## Powiązane dokumenty

- [TOON vs JSON vs CSV Analysis](./TOON_VS_JSON_VS_CSV_ANALYSIS.md)
- [AI Format Tutorial](../tutorials/AI_FORMAT_TUTORIAL.md)
- [TASK-040 Recommendations](../../issue/TASK_040_RECOMMENDATIONS.md)

---

**Ostatnia aktualizacja:** 2025-01-27

