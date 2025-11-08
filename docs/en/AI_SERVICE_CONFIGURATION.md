# AI Service Configuration – Mock vs Real

## 🎯 Overview
MovieMind ships with two AI execution modes controlled by the `AI_SERVICE` environment variable. The mode determines which queue job listeners dispatch and whether a real OpenAI call is made.

- **Mock mode** (`AI_SERVICE=mock`) keeps everything deterministic and cost-free for demos and CI.
- **Real mode** (`AI_SERVICE=real`) calls `OpenAiClientInterface` inside `RealGenerateMovieJob` / `RealGeneratePersonJob` and persists the generated content.

## ⚙️ Quick Configuration

1. **Environment variables (`.env`)**

```env
# AI Service configuration
AI_SERVICE=mock            # or 'real'

# Required only for AI_SERVICE=real
OPENAI_API_KEY=sk-********
OPENAI_MODEL=gpt-4o-mini   # optional override
OPENAI_URL=https://api.openai.com/v1/chat/completions
```

1. **`config/services.php` excerpt**

```php
'ai' => [
    'service' => env('AI_SERVICE', 'mock'),
],

'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
],
```

No manual container binding is required — the listeners use `AiServiceSelector` and the jobs resolve `OpenAiClientInterface` automatically.

## 🔁 How the Selector Works

1. `MovieGenerationRequested` / `PersonGenerationRequested` events are fired by controllers and actions.
2. `QueueMovieGenerationJob` / `QueuePersonGenerationJob` call `AiServiceSelector::getService()`.
3. The selector checks `config('services.ai.service')`:
   - `mock` → dispatches `MockGenerate*Job`.
   - `real` → dispatches `RealGenerate*Job`.
4. `RealGenerate*Job` receives `OpenAiClientInterface` via method injection and performs the actual API call using the OpenAI credentials above.

## 🔄 Switching Modes

```bash
# Toggle mode
echo "AI_SERVICE=real" >> .env
echo "OPENAI_API_KEY=sk-..." >> .env

# Refresh configuration
php artisan config:clear
php artisan queue:restart
```

In Docker environments, rebuild/restart the containers after changing env vars.

## ✅ Recommended Usage

| Scenario | Suggested Setting | Notes |
|----------|-------------------|-------|
| Local development & CI | `AI_SERVICE=mock` | Fast, stable outputs, no external dependencies |
| Demo showcasing real AI | `AI_SERVICE=real` with a demo key | Use rate limits and shorter prompts |
| Production | `AI_SERVICE=real` | Ensure secrets are stored in your secret manager and rotate keys regularly |

Always keep mock mode available — it is valuable for regression tests and offline development.

---

**Polish version:** [`../pl/AI_SERVICE_CONFIGURATION.md`](../pl/AI_SERVICE_CONFIGURATION.md)
