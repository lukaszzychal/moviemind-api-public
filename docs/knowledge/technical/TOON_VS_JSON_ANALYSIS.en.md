# TOON vs JSON Format Analysis for AI Communication

> **Created:** 2025-11-30  
> **Context:** Analysis of TOON (Token-Oriented Object Notation) format as an alternative to JSON for AI communication  
> **Category:** technical  
> **Source:** [TOON vs JSON: The New Format Designed for AI](https://dev.to/akki907/toon-vs-json-the-new-format-designed-for-ai-nk5)

## Purpose

Analyzing TOON (Token-Oriented Object Notation) format as an alternative to JSON for AI communication in MovieMind API project. TOON can save 30-60% tokens compared to JSON, which translates to significant API cost savings.

## What is TOON?

**TOON (Token-Oriented Object Notation)** is a new serialization format designed specifically for communication with Large Language Models (LLM). The main goal of TOON is to reduce the number of tokens needed to pass data to AI, which directly translates to lower API costs.

### The JSON Problem

JSON is a standard format, but for AI it has disadvantages:
- Repeating keys for each object in an array
- Unnecessary quotes and brackets
- High token consumption for repetitive structures

**Example - JSON:**
```json
{
  "users": [
    { "id": 1, "name": "Alice", "role": "admin", "salary": 75000 },
    { "id": 2, "name": "Bob", "role": "user", "salary": 65000 },
    { "id": 3, "name": "Charlie", "role": "user", "salary": 70000 }
  ]
}
```
**Tokens: 257**

### TOON Solution

**Example - TOON:**
```
users[3]{id,name,role,salary}:
1,Alice,admin,75000
2,Bob,user,65000
3,Charlie,user,70000
```
**Tokens: 166 (35% savings)**

## Key Features of TOON

### 1. Tabular Arrays - Declare Once, Use Many Times

**Key insight:** When we have uniform arrays of objects (same fields, same types), why repeat keys for each object?

**JSON (repetitive):**
```json
[
  { "sku": "A1", "qty": 2, "price": 9.99 },
  { "sku": "B2", "qty": 1, "price": 14.50 }
]
```

**TOON (efficient):**
```
[2]{sku,qty,price}:
A1,2,9.99
B2,1,14.5
```

The schema is declared once in the header `{sku,qty,price}`, then each row is just CSV-style values. This is where TOON shines brightest.

### 2. Smart Quoting

TOON uses quotes only when absolutely necessary:

- `hello world` → No quotes needed (inner spaces are fine)
- `hello 👋 world` → No quotes (Unicode is safe)
- `"hello, world"` → Quotes required (contains comma)
- `" padded "` → Quotes required (leading/trailing spaces)

This minimal-quoting approach saves tokens while keeping data unambiguous.

### 3. Indentation Instead of Brackets

Like YAML, TOON uses indentation instead of curly braces for nested structures:

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

Cleaner, more readable, and fewer tokens.

### 4. Explicit Array Lengths

TOON includes array length in brackets (`[N]`), which helps LLMs understand and validate structure:

```
tags[3]: admin,ops,dev
```

This explicit metadata reduces parsing errors when LLMs generate or interpret structured data.

## Benchmarks - Token Savings

According to TOON project research:

| Dataset | JSON Tokens | TOON Tokens | Savings |
|---------|-------------|-------------|---------|
| GitHub Repos (100 records) | 15,145 | 8,745 | **42.3%** |
| Analytics (180 days) | 10,977 | 4,507 | **58.9%** |
| E-commerce Orders | 257 | 166 | **35.4%** |

**Sweet spot:** Uniform tabular data - records with consistent schemas across many rows. The more repetitive JSON keys, the more TOON can optimize.

### LLM Comprehension

Token efficiency doesn't matter if LLM can't understand the format. Benchmarks tested 4 different models (GPT-5 Nano, Claude Haiku, Gemini Flash, Grok) on 154 data retrieval questions:

- **TOON accuracy:** 70.1%
- **JSON accuracy:** 65.4%
- **Token reduction:** 46.3%

TOON not only saves tokens but also improves AI comprehension accuracy!

## Examples for MovieMind API

### Example 1: Movie Data

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
**Estimated tokens:** ~45

**TOON:**
```
title: The Matrix
release_year: 1999
director: Lana Wachowski
description: A computer hacker learns about the true nature of reality.
genres[2]: Action,Sci-Fi
```
**Estimated tokens:** ~35 (**~22% savings**)

### Example 2: Movie List (tabular data)

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
**Estimated tokens:** ~80

**TOON:**
```
movies[3]{title,year,director}:
The Matrix,1999,Lana Wachowski
Inception,2010,Christopher Nolan
Interstellar,2014,Christopher Nolan
```
**Estimated tokens:** ~50 (**~37% savings**)

### Example 3: Person Data with Biography

**JSON:**
```json
{
  "name": "Keanu Reeves",
  "birth_date": "1964-09-02",
  "birthplace": "Beirut, Lebanon",
  "biography": "Keanu Charles Reeves is a Canadian actor..."
}
```
**Estimated tokens:** ~30

**TOON:**
```
name: Keanu Reeves
birth_date: 1964-09-02
birthplace: Beirut, Lebanon
biography: Keanu Charles Reeves is a Canadian actor...
```
**Estimated tokens:** ~25 (**~17% savings**)

## Advantages of TOON

### 1. Token Savings
- ✅ 30-60% token reduction for tabular data
- ✅ Direct API cost savings
- ✅ Faster processing (fewer tokens = faster responses)

### 2. Better AI Comprehension
- ✅ Higher parsing accuracy (70.1% vs 65.4% for JSON)
- ✅ Explicit array lengths help with validation
- ✅ More readable format for AI

### 3. Readability
- ✅ Less visual "noise" than JSON
- ✅ Similar to YAML/CSV (familiar formats)
- ✅ Easier to debug

## Disadvantages of TOON

### 1. Lack of Ecosystem Support
- ❌ Not a standard format (like JSON)
- ❌ No native support in most libraries
- ❌ Requires own parser/serializer implementation

### 2. Limitations for Complex Structures
- ❌ Works best for tabular data
- ❌ Less efficient for deeply nested structures
- ❌ May be less readable for very complex data

### 3. Implementation Cost
- ❌ Requires JSON → TOON converter implementation
- ❌ Requires TOON → JSON parser implementation
- ❌ Additional tests and maintenance

### 4. Compatibility
- ❌ Not all LLMs may understand TOON equally well
- ❌ Requires validation with used AI model
- ❌ May require additional prompts explaining the format

## Use Cases for MovieMind API

### ✅ When to Use TOON

1. **Generating lists of movies/people**
   - Tabular data - ideal for TOON
   - Large token savings (30-50%)
   - High frequency of use

2. **Bulk operations**
   - Mass description generation
   - Data import
   - Synchronization with external sources

3. **RAG (Retrieval Augmented Generation)**
   - Sending many similar records as context
   - Token savings with large number of records

### ❌ When NOT to Use TOON

1. **Single objects**
   - Small savings (10-20%)
   - Not worth complicating for small gains

2. **Deeply nested structures**
   - TOON works best for flat/tabular data
   - Complex nesting may be less readable

3. **Communication with external APIs**
   - If API requires JSON, no point in converting

## Analysis for MovieMind API

### Current JSON Usage

**Location:** `api/app/Services/OpenAiClient.php`

**Current data:**
- Single movie/person objects
- Structured responses with JSON Schema
- Relatively simple structures

**Estimated savings:**
- Single objects: **15-25%** token savings
- Movie/person lists: **35-50%** token savings
- Bulk operations: **40-60%** token savings

### Potential Applications

1. **Generating descriptions for multiple movies at once**
   - Instead of multiple API calls, one with movie list in TOON
   - Significant savings for bulk operations

2. **RAG - sending similar descriptions as context**
   - List of similar movies in TOON as context
   - Savings with large number of records

3. **Data import from external sources**
   - Convert import data to TOON before sending to AI
   - Savings for large imports

## Recommendations

### Short-term (1-2 months)

**Option 1: Experiment with TOON for lists**
- Implement JSON → TOON converter for tabular data
- Test with real OpenAI API
- Measure actual token savings
- **Advantages:** Low risk, ability to verify
- **Disadvantages:** Requires implementation

**Option 2: Wait for format maturity**
- Monitor TOON development
- Check if libraries/parsers appear
- **Advantages:** Less work now
- **Disadvantages:** May miss savings

**Recommendation:** Option 1 - experiment with TOON for movie/person lists

### Medium-term (3-6 months)

**If experiment succeeds:**
- Extend TOON usage to all tabular data
- Implement TOON → JSON parser for AI responses
- Add feature flag `ai_use_toon_format`
- Update documentation

### Long-term (6+ months)

**If TOON becomes standard:**
- Consider full migration to TOON for AI communication
- Keep JSON for client API communication
- Optimize all AI communication paths

## Implementation

### Example JSON → TOON Converter

```php
class ToonConverter
{
    public function convert(array $data): string
    {
        // Check if it's tabular array
        if ($this->isTabularArray($data)) {
            return $this->convertTabularArray($data);
        }
        
        // For nested structures use YAML-like format
        return $this->convertNested($data);
    }
    
    private function isTabularArray(array $data): bool
    {
        if (empty($data) || !isset($data[0])) {
            return false;
        }
        
        $firstKeys = array_keys($data[0]);
        
        // Check if all elements have same keys
        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
            
            $keys = array_keys($item);
            if ($keys !== $firstKeys) {
                return false;
            }
            
            // Check if values are primitive
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
        
        // Quotes only when necessary
        if (str_contains($str, ',') || str_contains($str, '"') || 
            str_contains($str, "\n") || preg_match('/^\s|\s$/', $str)) {
            return '"' . str_replace('"', '""', $str) . '"';
        }
        
        return $str;
    }
}
```

### Example Usage in OpenAiClient

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
    
    // ... rest of implementation
}
```

## Conclusions

1. **TOON offers significant token savings** (30-60%) for tabular data
2. **Better AI comprehension** - higher parsing accuracy
3. **Works best for tabular data** - movie lists, people, etc.
4. **Requires implementation** - no native support
5. **Worth testing** - potential savings are significant

## Related Documents

- [AI Validation and Hallucination Prevention](./AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
- [Task TASK-040](../../issue/en/TASKS.md#task-040)
- [TOON vs JSON: The New Format Designed for AI](https://dev.to/akki907/toon-vs-json-the-new-format-designed-for-ai-nk5)

---

**Last updated:** 2025-11-30

