# MovieMind API — Checklist (Docs, API, README)

Tags: @docs @api @README.md

## Status Summary

- Codebase: Laravel API present under `api/` with v1 endpoints and tests
- Docs: PL/EN specs split; payload examples added; Postman collection added
- README: Present, but stack mentions Symfony (needs alignment with Laravel)

---

## ✅ Done (based on current branch)

### API
- [x] GET `/api/v1/movies` — list movies
- [x] GET `/api/v1/movies/{id}` — movie details
- [x] GET `/api/v1/people/{id}` — person details (actor/director/etc.) [new]
- [x] POST `/api/v1/generate` — accepts `entity_type: MOVIE | PERSON`, returns mock `job_id`
- [x] GET `/api/v1/jobs/{id}` — job status (stub/mocked)
- [x] Feature flag check for generation (`ai_description_generation` via Pennant)
- [x] Feature tests: Movies, Actors, Generate, People

### Documentation
- [x] Language split: `docs/pl/*`, `docs/en/*`
- [x] Updated endpoints table (PL/EN) incl. `/people/{id}` and PERSON generation
- [x] Added detailed request/response payloads (PL/EN)
- [x] Added Postman collection: `docs/postman/moviemind-api.postman_collection.json`

### Repo Hygiene
- [x] Branch protection rules docs (PL/EN)
- [x] Roadmaps present (PL/EN)

---

## 🔧 Partial / To Verify
- [ ] Queue workers/Horizon configured and running (jobs currently mocked in controller)
- [ ] Redis cache used in endpoints (docs mention cache; confirm usage in code)
- [ ] Pennant feature flags environments/config consistency
- [ ] CI (GitHub Actions) for tests and gitleaks/security-audit

---

## ⛔ Not Implemented Yet (per docs/roadmap)
- [ ] Real AI integration (OpenAI) and job dispatching pipeline
- [ ] Webhooks (billing/notifications)
- [ ] Billing/rate limiting plans (RapidAPI integration)
- [ ] Admin UI (Nova/Breeze) and management endpoints
- [ ] OpenAPI spec file (`docs/openapi.yaml`) and publishing
- [ ] Analytics/monitoring dashboards

---

## 🎯 Next Steps (in order)

1) Replace mock generation with queued jobs
- [ ] Create `GenerateDescriptionJob` and dispatch in `GenerateController`
- [ ] Implement job handler to write to `movie_descriptions` / `person_bios`
- [ ] Add basic quality/plagiarism fields per schema (optional at first)

2) Wire Redis + caching
- [ ] Introduce response caching on GET movie/person show
- [ ] Cache invalidation on new generation completion

3) OpenAPI and developer docs
- [ ] Author `docs/openapi.yaml` for core endpoints (+ examples)
- [ ] Link OpenAPI in root `README.md` and `api/README.md`

4) CI & Security
- [ ] Add GitHub Actions: phpunit, gitleaks, composer audit
- [ ] Enforce branch protection with required checks

5) README alignment
- [ ] Update root `README.md` tech stack from Symfony→Laravel (current api is Laravel)
- [ ] Add local run instructions for Laravel app (`api/`), Horizon, Redis, Postgres

6) Postman collection polish
- [ ] Add example responses and tests per request
- [ ] Add environment templates for local/staging

7) Feature flags hardening
- [ ] Centralize flags config and add docs (`config/pennant.php`/Pennant)
- [ ] Add admin endpoints or UI to toggle flags (guarded)

8) Optional: People domain enrichment
- [ ] Extend `/people/{id}` to include roles (ACTOR, DIRECTOR, WRITER) and credits
- [ ] Add search endpoint for people `/v1/people?q=`

---

## 📌 Traceability
- Endpoints: `api/routes/api.php`
- Controllers: `api/app/Http/Controllers/Api/*Controller.php`
- Tests: `api/tests/Feature/*`
- Docs: `docs/pl/*`, `docs/en/*`
- Postman: `docs/postman/moviemind-api.postman_collection.json`
- README: `/README.md`, `api/README.md`
