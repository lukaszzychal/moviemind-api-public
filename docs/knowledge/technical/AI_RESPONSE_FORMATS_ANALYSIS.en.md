# AI Response Formats Analysis - JSON Schema vs Tool Calling

> **Created:** 2025-11-30  
> **Context:** Analysis of different AI response formats in the context of MovieMind API project  
> **Category:** technical

## Purpose

Analyzing available AI response formats (JSON Schema, Tool Calling, Structured Outputs) and determining the optimal solution for the MovieMind API project.

## Available AI Response Formats in OpenAI API

### 1. JSON Schema (currently used)

**Description:**
Format that enforces JSON response structure according to a defined schema. AI returns data in exactly the specified format.

**Usage example:**
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

**Response:**
```json
{
    "title": "The Matrix",
    "release_year": 1999,
    "director": "Lana Wachowski",
    "description": "A computer hacker learns about the true nature of reality...",
    "genres": ["Action", "Sci-Fi"]
}
```

**Advantages:**
- ✅ Enforces response structure
- ✅ Validation at API level
- ✅ Predictable format
- ✅ Easy parsing
- ✅ Supported by most OpenAI models

**Disadvantages:**
- ❌ Limits AI flexibility
- ❌ May require retry if AI cannot satisfy schema
- ❌ Requires schema definition for each response type
- ❌ Issues with Responses API (400 error - parameter moved)

**Use case for MovieMind API:**
- ✅ Generating movie/person data (current usage)
- ✅ When we need strictly defined structure
- ✅ When structure validation is critical

**When to use:**
- When response structure is always the same
- When we need API-level validation
- When parsing must be simple and predictable

**When NOT to use:**
- When structure may vary depending on context
- When we need more flexibility from AI
- When Responses API doesn't work (current problem)

---

### 2. Tool Calling / Function Calling

**Description:**
Mechanism allowing AI to call "functions" (tools) defined by the user. AI decides when and which function to use.

**Usage example:**
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
    'tool_choice' => 'required', // Forces function usage
];
```

**Response:**
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

**Advantages:**
- ✅ More flexible than JSON Schema
- ✅ AI can choose between different functions
- ✅ Possibility of chained function calls
- ✅ Better for complex scenarios
- ✅ Works with Chat Completions API (doesn't require Responses API)

**Disadvantages:**
- ❌ Requires additional parsing of `tool_calls`
- ❌ More code to handle
- ❌ May be overkill for simple cases
- ❌ AI may decide not to use function (if `tool_choice` is not `required`)

**Use case for MovieMind API:**
- ✅ When we need more flexibility
- ✅ When we want to allow AI to choose response format
- ✅ When planning to extend functionality (e.g., existence verification, validation)

**When to use:**
- When we need many different response types
- When AI should decide on format
- When planning functionality expansion

**When NOT to use:**
- When we need simple, predictable format
- When structure is always the same
- When we don't need flexibility

---

### 3. Structured Outputs (OpenAI)

**Description:**
New format introduced by OpenAI that combines advantages of JSON Schema with better error handling and validation.

**Usage example:**
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
            'strict' => true, // Forces exact match
            'schema' => [
                'type' => 'object',
                'properties' => [...],
            ],
        ],
    ],
];
```

**Advantages:**
- ✅ Better validation than standard JSON Schema
- ✅ `strict` mode forces exact match
- ✅ Better error messages
- ✅ Supported by newer models

**Disadvantages:**
- ❌ Requires newer OpenAI models
- ❌ May not be available for all models
- ❌ Similar problems as JSON Schema with Responses API

**Use case for MovieMind API:**
- ✅ When we need strong validation
- ✅ When using newer OpenAI models
- ✅ When we want to avoid parsing errors

---

### 4. Standard JSON Object (without schema)

**Description:**
Simple format that only enforces JSON, without specified structure.

**Usage example:**
```php
$payload = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Generate movie info for: the-matrix-1999. Return JSON with: title, release_year, director, description, genres.'],
    ],
    'response_format' => ['type' => 'json_object'],
];
```

**Advantages:**
- ✅ Simplest format
- ✅ Works with all models
- ✅ Doesn't require schema definition
- ✅ Flexible

**Disadvantages:**
- ❌ No structure validation
- ❌ AI may return incorrect structure
- ❌ Requires manual validation in code
- ❌ Less predictable

**Use case for MovieMind API:**
- ✅ Fallback when other formats don't work
- ✅ Prototyping
- ✅ When structure may vary

---

## Format Comparison

| Format | Validation | Flexibility | Complexity | API Support | Recommendation |
|--------|-----------|-------------|------------|-------------|----------------|
| **JSON Schema** | ✅ Strong | ❌ Low | 🟡 Medium | ❌ Issues with Responses API | ⚠️ If we fix API |
| **Tool Calling** | ✅ Medium | ✅ High | 🔴 High | ✅ Good | ✅ For extended features |
| **Structured Outputs** | ✅ Very strong | ❌ Low | 🟡 Medium | ⚠️ Depends on model | ✅ For newer models |
| **JSON Object** | ❌ None | ✅ High | 🟢 Low | ✅ Good | ✅ Fallback |

---

## Analysis of Current Usage in MovieMind API

### Current State

**Location:** `api/app/Services/OpenAiClient.php`

**Used format:** JSON Schema with Responses API

**Problems:**
- ❌ 400 "unsupported_parameter" error from Responses API
- ❌ `response_format` format not supported (moved to `text.format`)
- ❌ Requires fix or format change

**Current schemas:**
- `movieResponseSchema()` - for movies
- `personResponseSchema()` - for people

---

## Recommendations for MovieMind API

### Short-term (immediate)

**Option 1: Fix Responses API**
- Move `response_format` to `text.format` in `input` structure
- Keep JSON Schema
- **Advantages:** Minimal code changes
- **Disadvantages:** Still depends on Responses API

**Option 2: Switch to Chat Completions API**
- Use standard `/v1/chat/completions`
- Keep JSON Schema (works with Chat Completions)
- **Advantages:** Stable API, well documented
- **Disadvantages:** Requires endpoint change

**Option 3: Fallback to JSON Object**
- Temporarily use `response_format: {type: 'json_object'}`
- Add validation in application code
- **Advantages:** Works immediately
- **Disadvantages:** No structure validation at API level

**Recommendation:** Option 2 (Chat Completions API) - most stable solution

### Medium-term (1-2 months)

**Consider Tool Calling:**
- If planning functionality expansion (existence verification, data validation)
- If we need more flexibility
- **Advantages:** Better for complex scenarios
- **Disadvantages:** More code to handle

### Long-term (3-6 months)

**Structured Outputs:**
- When switching to newer OpenAI models
- When we need stronger validation
- **Advantages:** Best validation
- **Disadvantages:** Requires newer models

---

## Implementation Examples

### Example 1: JSON Schema with Chat Completions API

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

### Example 2: Tool Calling

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

## Conclusions

1. **Current format (JSON Schema with Responses API)** - requires fix
2. **Chat Completions API with JSON Schema** - best short-term solution
3. **Tool Calling** - worth considering for future extensions
4. **Structured Outputs** - to consider when switching to newer models

## Related Documents

- [AI Validation and Hallucination Prevention](./AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
- [Task TASK-039](../../issue/en/TASKS.md#task-039)
- [OpenAI API Documentation](https://platform.openai.com/docs/api-reference)

---

**Last updated:** 2025-11-30

