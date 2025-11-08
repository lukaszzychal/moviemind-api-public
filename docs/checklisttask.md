# MovieMind API — Checklist (Docs, API, README)

Tags: @docs @api @README.md

## Status Summary

- Codebase: Laravel 12 API under `api/` with v1 endpoints and automated tests
- Docs: PL/EN specs maintained; Postman/Insomnia assets available
- README: Root and PL versions aligned with Laravel stack and current Quick Start
- Backlog: Aktualne zadania znajdują się w `docs/issue/pl/TASKS.md` oraz `docs/issue/en/TASKS.md`

---

## ✅ Snapshot (reference only)

Poniższa lista odzwierciedla ukończone elementy MVP i służy jako szybka ściągawka. Aktualny status zadań organizacyjnych utrzymujemy w plikach `TASKS.md`.

### API

- [x] GET `/api/v1/movies`
- [x] GET `/api/v1/movies/{slug}`
- [x] GET `/api/v1/people/{slug}`
- [x] POST `/api/v1/generate`
- [x] GET `/api/v1/jobs/{id}`
- [x] Feature flags: `ai_description_generation`, `ai_bio_generation`
- [x] Test suites: Movies, People, Generate, MissingEntity, Admin flags, HATEOAS

### Documentation

- [x] Dwujęzyczne README i roadmapy (PL/EN)
- [x] `docs/openapi.yaml` + Postman/Insomnia collections
- [x] Przewodniki operacyjne (AI service modes, queue, feature flags)

### Repo Hygiene

- [x] Branch rules/opisy (`docs/GITHUB_PROJECTS_SETUP.md`)
- [x] CI pipelines (tests, security scans)
- [x] Pennant feature flags opisane i testowane

---

## 🔄 Workstream Pointers

Aktualne zadania i priorytety są śledzone w:

- `docs/issue/pl/TASKS.md` – backlog po polsku  
- `docs/issue/en/TASKS.md` – backlog w wersji angielskiej

Te pliki zawierają statusy (`PENDING`, `IN_PROGRESS`, `COMPLETED`), priorytety i szczegółowe opisy. Niniejsza checklist pełni funkcję szybkiego przeglądu zrealizowanych elementów oraz miejsc startowych dla osoby przeglądającej repozytorium.

---

## 📌 Traceability

- Endpoints: `api/routes/api.php`
- Controllers: `api/app/Http/Controllers/Api/*Controller.php`
- Tests: `api/tests/Feature/*`
- Docs: `docs/pl/*`, `docs/en/*`
- Postman: `docs/postman/moviemind-api.postman_collection.json`
- README: `/README.md`, `/README.pl.md`, `api/README.md`
