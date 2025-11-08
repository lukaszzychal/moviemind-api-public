# Mock vs Real Jobs – Configuration

## 🎯 Overview
- `AI_SERVICE=mock` → dispatch `MockGenerate*Job` classes (simulate AI).  
- `AI_SERVICE=real` → dispatch `RealGenerate*Job` classes (call real provider).

## 📁 Project structure
```
app/Jobs/
├── MockGenerateMovieJob.php
├── MockGeneratePersonJob.php
├── RealGenerateMovieJob.php
└── RealGeneratePersonJob.php
```

## 🔄 Listeners
`QueueMovieGenerationJob` / `QueuePersonGenerationJob` read `config('services.ai.service')` and choose the appropriate job. They also store initial status via `JobStatusService`.

## 🧰 Usage tips
- Keep `mock` for local dev/test pipelines for deterministic outputs.  
- Use `real` on staging/production; configure secrets and monitor job failures.  
- Horizon or `queue:work` must be running for async processing.  
- Poll `/api/v1/jobs/{id}` to observe job status (pending/done/failed).

**Polish source:** [`../pl/MOCK_VS_REAL_JOBS.md`](../pl/MOCK_VS_REAL_JOBS.md)
