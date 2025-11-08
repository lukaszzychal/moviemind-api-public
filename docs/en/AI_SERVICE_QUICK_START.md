# AI Service – Quick Start

## 🚀 Quick setup
1. Add to `.env`:
   ```env
   # AI Service Configuration
   # Options: 'mock' (default) or 'real'
   AI_SERVICE=mock
   ```

2. Choose the driver:
   - **Development / tests** → `AI_SERVICE=mock`
     - Uses `MockAiService`
     - Simulates AI responses (sleep + mock payloads)
   - **Staging / production** → `AI_SERVICE=real`
     - Uses `RealAiService`
     - Emits events + jobs, ready for real integrations

3. Check which implementation is active:
   ```bash
   php artisan tinker
   >>> app(App\Services\AiServiceInterface::class)
   App\Services\MockAiService  # or App\Services\RealAiService
   ```

## 🔄 Switching environments
- Update `.env` and run `php artisan config:clear` (or restart container) to reload the driver.
- In Docker deployments set `AI_SERVICE` via environment configuration.

## 🧪 Testing tips
- Keep `mock` for all automated pipelines.  
- When experimenting with `real`, stub external APIs in jobs or use fake clients to avoid paid calls.

**Polish source:** [`../pl/AI_SERVICE_QUICK_START.md`](../pl/AI_SERVICE_QUICK_START.md)
