# MovieMind – Internationalization (i18n) Strategy

> **Status:** Proposal / Decision Document  
> **Date:** 2026-03-08  
> **Languages in scope:** English (EN – default), Polish (PL), German (DE)

---

## 1. Overview

This document analyses where and how to implement multi-language support across the MovieMind stack.
The application consists of two layers:

| Layer | Technology |
|---|---|
| **Frontend** | Vue 3 (SPA, Vite) |
| **Backend** | Laravel 11 (REST API) |

Content that can be translated falls into two categories:

| Category | Examples | Primary owner |
|---|---|---|
| **Static UI strings** | Button labels, form placeholders, error messages, navigation items | Frontend |
| **Dynamic content** | Movie titles, descriptions, actor bios, genre names stored in the database | Backend (database) |

---

## 2. Decision Axis: Frontend vs. Backend Translations

### 2.1 Frontend-only i18n (Vue i18n)

UI strings live in JSON locale files delivered with the SPA bundle. The backend always responds in English (or the stored language). The browser/client selects the UI language independently.

```
/frontend/src/locales/
  en.json
  pl.json
  de.json
```

**Pros:**
- ✅ **Zero backend changes** – API responses remain language-agnostic
- ✅ **Instant language switch** – no new HTTP request, purely client-side
- ✅ **Simpler deployment** – locale bundles ship with the frontend build
- ✅ **Offline capability** – works after initial page load without re-fetching data
- ✅ **Well-established tooling** – `vue-i18n` v9 is the de-facto standard, actively maintained
- ✅ **Separation of concerns** – UI text is a frontend problem; can be updated by non-engineers via JSON files
- ✅ **Lower server load** – no per-request locale resolution logic

**Cons:**
- ❌ **Bundle size grows** – each locale file adds ~5–30 KB (mitigated by lazy loading)
- ❌ **Dynamic database content remains in its stored language** – movie overview text is whatever TMDb returned (usually English), regardless of user locale
- ❌ **SEO per-locale** is harder in SPA mode (requires SSR or pre-rendering)
- ❌ **No server-side rendered translated content** for crawlers without additional setup

---

### 2.2 Backend-only i18n (Laravel Localization)

The API accepts an `Accept-Language` header (or `?lang=pl` query param). Laravel resolves the locale, queries locale-specific content from the DB, and returns translated strings in JSON responses.

```http
GET /api/v1/movies?q=inception
Accept-Language: pl
```

**Pros:**
- ✅ **Dynamic content can be translated** – movie descriptions, genre labels from DB can be locale-aware
- ✅ **SEO-friendly** – server renders the correct locale for crawlers (if using SSR or pre-rendering)
- ✅ **Single source of truth** – locale logic centralized in one place
- ✅ **API can serve mobile apps / 3rd party clients** with proper locale support out of the box

**Cons:**
- ❌ **Every language switch requires a new API call** – higher latency, more server load
- ❌ **Backend complexity increases** – every route/controller must handle locale negotiation
- ❌ **Database schema must support multi-language content** – additional tables or JSON columns required (e.g., `movie_descriptions` already partially handles this, but UI strings need additional consideration)
- ❌ **Static UI strings (buttons, labels) don't belong in the DB** – mixing concerns
- ❌ **Laravel's built-in i18n** (`lang/` files) is limited and not designed for dynamic content at scale

---

### 2.3 Hybrid Approach (Recommended ✅)

Split responsibilities by content type:

| Content | Where translated | Tool |
|---|---|---|
| UI strings (buttons, labels, messages) | **Frontend** | `vue-i18n` v9 |
| Dynamic DB content (movie descriptions, titles) | **Backend** (existing `descriptions` table) | `Accept-Language` header + eager-loaded locale relation |
| API error messages | **Backend** | Laravel `lang/` files, keyed by locale |

```
Frontend: locale → picks from en.json / pl.json / de.json
Backend:  Accept-Language header → queries locale-specific description from DB
```

---

## 3. Factors Influencing the Decision

| Factor | Influence |
|---|---|
| **Target audience** | Multi-national → all three languages add real value |
| **Content type split** | Static UI strings → frontend; DB content (overviews) → backend |
| **Current DB schema** | `movie_descriptions`, `tv_series_descriptions` etc. already store multilingual text per `locale` column — backend i18n for content is *already half-done* |
| **SEO requirements** | If public search indexing matters → add SSR (Nuxt 3) or static pre-rendering; otherwise frontend i18n is sufficient |
| **Team size** | Small team → frontend-only is faster to ship and maintain |
| **Bundle size** | Lazy-loaded locale chunks keep initial bundle small |
| **API consumers** | Currently only the Vue SPA → no urgent need for backend locale negotiation |

---

## 4. Current State Assessment

### What already exists

- DB schema stores descriptions per locale (`locale` column in `movie_descriptions` etc.)  
- `defaultDescription` relationship already filters by locale (verify `MovieDescription` model)
- Backend returns English content by default from TMDb

### What is missing

- No `vue-i18n` installed in the frontend
- No `en.json` / `pl.json` / `de.json` locale files
- No language switcher component in the UI
- No `Accept-Language` propagation from frontend to API client
- No Laravel `lang/` files for API error message translations

---

## 5. Recommended Implementation Plan

### Phase 1 – Frontend i18n (UI strings only) — Quick win

1. Install `vue-i18n` v9
   ```bash
   npm install vue-i18n@9
   ```
2. Create locale files:
   ```
   frontend/src/locales/en.json
   frontend/src/locales/pl.json
   frontend/src/locales/de.json
   ```
3. Register plugin in `main.js`
4. Replace all hardcoded UI strings in `.vue` files with `$t('key')` calls
5. Add a `LanguageSwitcher.vue` component (dropdown: EN / PL / DE)
6. Persist chosen locale in `localStorage`

### Phase 2 – Backend locale forwarding (Dynamic content)

1. Frontend: send user locale in every API request header:
   ```js
   // client.js
   headers: { 'Accept-Language': userLocale }
   ```
2. Backend (`api/app/Http/Middleware/`): add `SetLocale` middleware that reads `Accept-Language` and calls `App::setLocale()`
3. Update `defaultDescription` relationship to filter by `App::getLocale()` (fallback to `'en'`)
4. Update `defaultBio` relationship similarly for `Person`

### Phase 3 – API error message translations

1. Create `api/lang/pl/` and `api/lang/de/` directories
2. Translate validation and HTTP error messages
3. Return locale-aware error responses

---

## 6. Key Files to Modify

### Frontend

| File | Change |
|---|---|
| `frontend/src/main.js` | Register `vue-i18n` plugin |
| `frontend/src/api/client.js` | Add `Accept-Language` header |
| `frontend/src/views/Search.vue` | Replace strings with `$t()` |
| `frontend/src/views/Generate.vue` | Replace strings with `$t()` |
| `frontend/src/components/ui/` | Replace strings with `$t()` |
| `frontend/src/locales/en.json` | **[NEW]** English locale file |
| `frontend/src/locales/pl.json` | **[NEW]** Polish locale file |
| `frontend/src/locales/de.json` | **[NEW]** German locale file |
| `frontend/src/components/LanguageSwitcher.vue` | **[NEW]** Language dropdown |

### Backend

| File | Change |
|---|---|
| `api/app/Http/Middleware/SetLocale.php` | **[NEW]** Read `Accept-Language`, call `App::setLocale()` |
| `api/app/Models/Movie.php` | `defaultDescription` → locale-aware |
| `api/app/Models/TvSeries.php` | `defaultDescription` → locale-aware |
| `api/app/Models/TvShow.php` | `defaultDescription` → locale-aware |
| `api/app/Models/Person.php` | `defaultBio` → locale-aware |
| `api/lang/pl/*.php` | **[NEW]** Polish translations for errors/validation |
| `api/lang/de/*.php` | **[NEW]** German translations for errors/validation |

---

## 7. Conclusion

> **Adopt the Hybrid approach.**

- **Phase 1 (frontend i18n)** should be implemented first — it covers all static UI strings with minimum risk and zero backend changes.
- **Phase 2 (locale-forwarding for DB content)** is partially already done via the existing `descriptions` table structure — it needs middleware wiring and relationship updates.
- **Avoid a backend-only approach** for static UI text — it adds unnecessary complexity and latency for content that doesn't require server-side resolution.

The existing database schema is already multi-language-ready; the main work is frontend scaffolding and middleware wiring.
