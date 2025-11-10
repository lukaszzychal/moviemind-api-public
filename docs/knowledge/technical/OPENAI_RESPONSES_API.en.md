# OpenAI Responses API vs Chat Completions

> **Created:** 2025-11-10  
> **Context:** Migration of MovieMind's OpenAI client to the unified Responses API  
> **Category:** technical

## 🎯 Purpose

Explain why the project switched to the `/v1/responses` endpoint, how the payload differs from `chat/completions`, and what fallbacks remain available.

## 📋 Contents

1. **New default endpoint**  
   - `OpenAiClient` now targets `https://api.openai.com/v1/responses` by default.  
   - Built-in `response_format.type=json_schema` guarantees structured JSON responses.

2. **Request structure**  
   - The Responses API expects an `input` array with `input_text` blocks (system + user).  
   - The desired payload shape is expressed through `json_schema` (movie info, bios, etc.).

3. **Legacy support**  
   - Setting `OPENAI_URL=https://api.openai.com/v1/chat/completions` re-enables the classic `messages` payload.  
   - The client normalises outputs so downstream services receive the same associative array.

4. **Tests & documentation**  
   - `OpenAiClientTest` covers both response formats, including payload assertions.  
   - `AI_SERVICE_CONFIGURATION` documents the new default URL and the override.

5. **Fallback & safety**  
   - `extractContent()` understands `json_schema`, `output_text`, and legacy chat content.  
   - Error logging and backoff strategy remain unchanged.

## 🔗 Related Documents

- [AI Service Configuration – Mock vs Real](./AI_SERVICE_CONFIGURATION.md)
- [OpenAI Responses API vs Chat Completions (PL)](./OPENAI_RESPONSES_API.md)

## 📌 Notes

- Validate `json_schema` compatibility when switching models.  
- The helper script `scripts/openai-test.php` now defaults to the Responses API as well.

---

**Last updated:** 2025-11-10

