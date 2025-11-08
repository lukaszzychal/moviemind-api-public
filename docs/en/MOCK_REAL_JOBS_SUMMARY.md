# Mock vs Real Jobs – Implementation Summary

## ✅ Implemented components
- **Mock jobs** (`AI_SERVICE=mock`): `MockGenerateMovieJob`, `MockGeneratePersonJob` simulate AI payloads.  
- **Real jobs** (`AI_SERVICE=real`): `RealGenerateMovieJob`, `RealGeneratePersonJob` call the OpenAI client (with retry/timeouts).
- **Listeners:** `QueueMovieGenerationJob` / `QueuePersonGenerationJob` resolve the configured driver and dispatch either mock or real jobs.
- **JobStatusService:** initialises cache entries (`ai_job:{id}`) and exposes helpers for status polling (`JobsController`).

## 🔀 Switching drivers
- Toggle `AI_SERVICE` in `.env` (`mock` vs `real`).  
- Listeners respect the setting; no controller changes required.  
- Horizon must be running for asynchronous processing.

## 🧪 Testing & monitoring
- Unit tests cover both job paths.  
- Horizon dashboard tracks queue throughput, failures, runtime.  
- For real jobs ensure secrets (`OPENAI_API_KEY`, etc.) are configured.

**Polish source:** [`../pl/MOCK_REAL_JOBS_SUMMARY.md`](../pl/MOCK_REAL_JOBS_SUMMARY.md)
