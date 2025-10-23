# MovieMind API - Kompletna Specyfikacja Projektu / Complete Project Specification

> **📝 Note / Uwaga**: This document is bilingual (Polish/English) to support both local and international development teams.  
> **📝 Uwaga**: Ten dokument jest dwujęzyczny (Polski/Angielski) aby wspierać zarówno lokalne jak i międzynarodowe zespoły deweloperskie.

## 🎬 Przegląd Projektu / Project Overview

**MovieMind API** to AI-powered Film & Series Metadata API, które generuje i przechowuje unikalne opisy filmów, seriali i aktorów wykorzystując sztuczną inteligencję, cache i automatyczny wybór najlepszych wersji treści.

**MovieMind API** is an AI-powered Film & Series Metadata API that generates and stores unique descriptions for movies, series, and actors using artificial intelligence, caching, and automatic selection of the best content versions.

### 🎯 Główne Cele / Main Goals
- Generowanie unikalnych opisów filmów, seriali i aktorów / Generating unique descriptions for movies, series, and actors
- Wykorzystanie AI (OpenAI/LLM) do tworzenia treści / Using AI (OpenAI/LLM) for content creation
- Zapewnienie unikalności (brak kopiowania z IMDb, TMDb) / Ensuring uniqueness (no copying from IMDb, TMDb)
- Obsługa cache, wielojęzyczności i tagowania stylu / Supporting cache, multilingualism, and style tagging
- Udostępnienie danych przez REST API / Providing data through REST API

---

## 💡 Strategia Produktowa / Product Strategy

### MVP vs PoC
**MVP (Minimum Viable Product)** - pierwsza działająca wersja z minimalnym zakresem funkcji, możliwa do wystawienia na RapidAPI.

**MVP (Minimum Viable Product)** - first working version with minimal feature scope, ready for deployment on RapidAPI.

**PoC** = tylko dowód koncepcji (bez systemu, bazy, API)  
**MVP** = prawdziwe API, cache, storage i klucz API

**PoC** = proof of concept only (without system, database, API)  
**MVP** = real API, cache, storage, and API key

### Strategia Dual-Repository / Dual-Repository Strategy

| Wariant / Variant | Cel / Goal | Zakres kodu / Code Scope | Dostępność / Availability |
|-------------------|------------|-------------------------|---------------------------|
| **Publiczne repo / Public repo** | Portfolio, umiejętności / Portfolio, skills | Okrojony kod (bez API-keys, promptów) / Trimmed code (without API-keys, prompts) | Open Source |
| **Prywatne repo / Private repo** | Realny produkt / Real product | Pełna wersja, CI/CD, API keys / Full version, CI/CD, API keys | Zamknięte / Closed |

#### Zalety tego podejścia / Benefits of this approach:
- **Wizerunkowo / Image**: Pokazuje architekturę, strukturę, czysty kod / Shows architecture, structure, clean code
- **Bezpieczeństwo / Security**: Klucze API, prompty, "tajemnica handlowa" w repo prywatnym / API keys, prompts, "trade secrets" in private repo
- **Elastyczność / Flexibility**: Możliwość rozwoju w SaaS/startup bez przepisywania / Ability to develop into SaaS/startup without rewriting

### 🏗️ Implementacja Strategii Dual-Repository / Dual-Repository Strategy Implementation

#### Workflow: Publiczne → Prywatne (Rekomendowane) / Workflow: Public → Private (Recommended)

**Krok 1: Publiczne repo (portfolio) / Step 1: Public repo (portfolio)**
- Załóż `moviemind-api-public` (GitHub → Public) / Create `moviemind-api-public` (GitHub → Public)
- Dodaj: LICENSE (MIT/CC-BY-NC), .env.example, .gitignore, .gitleaks.toml / Add: LICENSE (MIT/CC-BY-NC), .env.example, .gitignore, .gitleaks.toml
- Minimalny kod MVP (mock AI, brak sekretów) / Minimal MVP code (mock AI, no secrets)
- Ustaw jako Template Repository (Settings → General → Template repository) / Set as Template Repository (Settings → General → Template repository)
- Włącz: Dependabot, Secret scanning, Branch protection, Gitleaks workflow / Enable: Dependabot, Secret scanning, Branch protection, Gitleaks workflow

**Krok 2: Prywatne repo jako kopii (bez fork) / Step 2: Private repo as copy (without fork)**
```bash
# Opcja A: Use this template (GitHub UI) / Option A: Use this template (GitHub UI)
# Na stronie publicznego repo → "Use this template" → utwórz Private repo / On public repo page → "Use this template" → create Private repo

# Opcja B: Mirror push (pełna historia) / Option B: Mirror push (full history)
git clone --bare https://github.com/<you>/moviemind-api-public.git
cd moviemind-api-public.git
git push --mirror git@github.com:<you>/moviemind-api-pro.git
cd ..
rm -rf moviemind-api-public.git
```

**Krok 3: Konfiguracja prywatnego repo / Step 3: Private repo configuration**
- Dodaj sekrety w GitHub Actions (Settings → Secrets) / Add secrets in GitHub Actions (Settings → Secrets)
- Zmień licencję na własną komercyjną / Change license to custom commercial
- Dodaj katalogi: `src/Billing/`, `src/Webhooks/`, `src/Admin/` / Add directories: `src/Billing/`, `src/Webhooks/`, `src/Admin/`
- Dodaj realne prompty AI i logikę biznesową / Add real AI prompts and business logic

#### Podział Zawartości / Content Division

| Element | Wersja Publiczna / Public Version | Wersja Prywatna / Private Version |
|---------|-----------------------------------|-----------------------------------|
| **Backend** | Symfony (MVP) | Symfony + AI Workers |
| **AI generacja / AI generation** | stub/mock | pełny prompt i model / full prompt and model |
| **Cache + DB** | ✅ | ✅ |
| **Rate Limit, Billing** | ❌ | ✅ |
| **Webhooki, Jobs, Admin Panel** | ❌ | ✅ |
| **Licencja / License** | MIT lub CC-BY-NC | własna komercyjna / custom commercial |

#### Struktura Repozytoriów / Repository Structure

**Publiczne repo (`moviemind-api-public`) / Public repo:**
```
├── README.md (opis projektu, architektury / project description, architecture)
├── src/ (tylko podstawowe endpointy / only basic endpoints)
├── docker-compose.yml
├── docs/ (C4, openapi.yaml)
├── .env.example (bez kluczy / without keys)
├── .gitignore
├── .gitleaks.toml
└── LICENSE (MIT lub CC BY-NC)
```

**Prywatne repo (`moviemind-api-private`) / Private repo:**
```
├── all public files
├── .env.production
├── src/AI/ (prompt templates, heurystyka / prompt templates, heuristics)
├── src/Webhooks/
├── src/Billing/
├── src/Admin/
├── tests/integration/
└── LICENSE (custom commercial)
```

#### Synchronizacja (jednokierunkowa) / Synchronization (one-way)
```bash
# W prywatnym repo - dodaj upstream / In private repo - add upstream
git remote add upstream https://github.com/<you>/moviemind-api-public.git
git fetch upstream
git merge upstream/main    # wciągnij poprawki z publicznego / pull fixes from public
```

⚠️ **Nigdy nie pushuj z prywatnego do publicznego** - unikniesz przypadkowego wyniesienia sekretnych zmian.

⚠️ **Never push from private to public** - avoid accidental exposure of secret changes.

---

## 🧩 Zakres MVP / MVP Scope

### 🔹 Funkcjonalności API / API Functionality

| Endpoint | Metoda / Method | Opis / Description |
|----------|-----------------|-------------------|
| `/v1/movies?q=` | GET | Wyszukaj filmy (tytuł, rok, gatunek) / Search movies (title, year, genre) |
| `/v1/movies/{id}` | GET | Szczegóły filmu + opis (AI lub cache) / Movie details + description (AI or cache) |
| `/v1/actors/{id}` | GET | Dane aktora + biografia / Actor data + biography |
| `/v1/generate` | POST | Wymuś nowe wygenerowanie opisu/biografii / Force new description/biography generation |
| `/v1/jobs/{id}` | GET | Status generacji (PENDING, DONE, FAILED) / Generation status (PENDING, DONE, FAILED) |

### 🔹 Technologie MVP / MVP Technologies

| Warstwa / Layer | Technologia / Technology | Uzasadnienie / Justification |
|-----------------|-------------------------|-------------------------------|
| **Backend API** | Symfony 7 (PHP 8.3) | Znany stack, szybkie MVP / Known stack, fast MVP |
| **AI Integration** | HTTP Client (OpenAI API) | Prosty interfejs, 1 endpoint / Simple interface, 1 endpoint |
| **Baza danych / Database** | PostgreSQL | Dane filmów/aktorów / Movie/actor data |
| **Cache** | Redis | Szybkie odpowiedzi, unikanie ponownego generowania / Fast responses, avoid re-generation |
| **Kolejki / Queues** | Symfony Messenger | Async generacja, nie blokuje API / Async generation, doesn't block API |
| **API Docs** | NelmioApiDoc / OpenAPI | Publikacja na RapidAPI / Publication on RapidAPI |
| **Auth** | Klucz API (X-API-Key) | Prosty plan darmowy/płatny / Simple free/paid plan |

### 🔹 Struktura Danych

#### Tabela: movies
| Pole | Typ | Opis |
|------|-----|------|
| id | int | PK |
| title | varchar | Tytuł |
| release_year | smallint | Rok produkcji |
| director | varchar | Reżyser |
| genres | text[] | Gatunki |
| default_description_id | int | Referencja do opisu |

#### Tabela: movie_descriptions
| Pole | Typ | Opis |
|------|-----|------|
| id | int | PK |
| movie_id | int FK | - |
| locale | varchar(10) | np. pl-PL, en-US |
| text | text | Treść opisu |
| context_tag | varchar(64) | np. modern, critical |
| origin | varchar(32) | GENERATED / TRANSLATED |
| ai_model | varchar(64) | np. gpt-4o-mini |
| created_at | timestamp | - |

#### Tabela: actors
| Pole | Typ |
|------|-----|
| id | int |
| name | varchar |
| birth_date | date |
| birthplace | varchar |
| default_bio_id | int |

#### Tabela: actor_bios
| Pole | Typ |
|------|-----|
| id | int |
| actor_id | int |
| locale | varchar(10) |
| text | text |
| context_tag | varchar(64) |
| origin | varchar(32) |
| ai_model | varchar(64) |
| created_at | timestamp |

#### Tabela: jobs
| Pole | Typ |
|------|-----|
| id | int |
| entity_type | varchar(16) (MOVIE, ACTOR) |
| entity_id | int |
| locale | varchar(10) |
| status | varchar(16) (PENDING, DONE, FAILED) |
| payload_json | jsonb |
| created_at | timestamp |

### 🔹 Przepływ Działania (Happy Path)

1. **Klient**: `GET /v1/movies/123`
2. **System**: Sprawdza w DB, czy istnieje opis
3. **Jeśli brak**: Tworzy rekord w jobs i uruchamia worker
4. **Worker**: Wywołuje OpenAI API z promptem
5. **Zapis**: Wynik do movie_descriptions, jobs.status = DONE
6. **Klient**: `GET /v1/jobs/{id}` → otrzymuje wynik
7. **Cache**: Następne zapytania trafiają w cache/DB

### 🔹 Przykładowy Prompt (PL)
```
Napisz zwięzły, unikalny opis filmu {title} z roku {year}.
Styl: {context_tag}.
Długość: 2–3 zdania, naturalny język, bez spoilera.
Język: {locale}.
Zwróć tylko czysty tekst.
```

### 🔹 Środowisko Uruchomieniowe

```yaml
version: "3.9"
services:
  api:
    build: .
    ports: ["8000:80"]
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      OPENAI_API_KEY: sk-xxxx
      APP_ENV: dev
    depends_on: [db, redis]
  db:
    image: postgres:15
    environment:
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
      POSTGRES_DB: moviemind
  redis:
    image: redis:7
```

### 🔹 Zakres MVP (co NIE wchodzi)
- ❌ UI (panel admin)
- ❌ Webhooki
- ❌ System planów/billingów
- ❌ Multiuser auth
- ❌ Monitoring i metryki
- ❌ AI porównywania wersji
- ❌ Tłumaczenia automatyczne

---

## 🌍 Wielojęzyczność (i18n/l10n)

### Strategia Lokalizacji

#### Język Kanoniczny
- Dla każdej encji przechowuj wersję kanoniczną (np. en-US)
- Wersje w innych językach to warianty lokalizacyjne

#### Dwa Tryby Pozyskania Treści
1. **Generation-first**: Opisy/biografie - tworz od zera w docelowym języku
2. **Translate-then-adapt**: Krótkie streszczenia - tłumacz z kanonu, potem adaptuj

#### Glosariusz "No-Translate"
- Nazwy firm, wytwórni, kanałów, nazwisk, brandów → domyślnie nie tłumacz
- Pola: term, locale, policy (NO_TRANSLATE/TRANSLITERATE/TRANSLATE)

#### Fallback i Negocjacja Języka
```
Accept-Language: pl-PL,pl;q=0.9,en;q=0.8
```
- API wybiera najlepszy wariant
- Jeśli brak → fallback do kanonu + async generacja/tłumaczenie

### Rozszerzona Struktura Danych

#### Encje Główne
- `movies(id, source_of_truth_locale, ...)`
- `people(id, source_of_truth_locale, ...)`

#### Warianty Lokalizacyjne
- `movie_locales(id, movie_id, locale, title_localized, tagline, aka_titles[], release_dates_by_country jsonb, distributors[], rating_certifications[], keywords[])`
- `person_locales(id, person_id, locale, name_localized, aliases[])`

#### Treści Generowane/Tłumaczone
- `movie_descriptions(id, movie_id, locale, text, context_tag, origin, ai_model, quality_score, plagiarism_score, selected_default boolean, created_at)`
- `person_bios(id, person_id, locale, text, context_tag, origin, ai_model, ...)`

#### Glosariusz
- `glossary_terms(id, term, locale, policy, notes, examples[])`

#### Zadania i Kontrola Jakości
- `localization_jobs(id, entity_type, entity_id, locale, mode, status, cost_tokens, latency_ms, created_at)`
- `content_evaluations(id, entity_type, entity_id, locale, version_id, readability_score, toxicity_score, hallucination_risk, human_vote_updown)`

### Przykładowe Endpointy z i18n

```bash
GET /movies/{id}?locale=pl-PL
→ { id, title, locale="pl-PL", description_default, aka_titles, release_dates_by_country, ... }

GET /movies/{id}/descriptions?locale=pl-PL
→ [ {version_id, text, context_tag, origin, quality_score, selected_default}, ... ]

POST /movies/{id}/generate
Body: { locale:"de-DE", context_tag:"critical", audience:"cinephile", grounded:true }
→ 202 Accepted { job_id }

GET /search?q=Cidade%20de%20Deus&locale=en-US
→ wynik z dopasowaniem aliasów/aka_titles i transliteracji
```

---

## 🚀 Dodatkowe Funkcje i Rozszerzenia

### 1. Kontekst i Styl Treści
- **Style Packs**: modern, critical, journalistic, playful, noir, scholarly
- **Audience Packs**: family-friendly, cinephile, teen, casual viewer
- **Endpoint**: `POST /movies/{id}/generate?locale=pl-PL&style=critical&audience=cinephile`

### 2. Jakość i Anty-Plagiat
- **Similarity Gate**: Embeddingi per wersja; odrzucaj zbyt podobne (>0.85 cosine)
- **Hallucination Guard**: Porównanie faktów z metadanymi
- **Watermark/Fingerprint**: `hash(prompt+output+model+locale)` → `/compliance/fingerprint`

### 3. Dane Wydawnicze per Region
- `release_dates_by_country` (kino, streaming, DVD)
- `rating_certifications` (PG-13, 12+, BBFC, FSK)
- `distributors` per kraj
- `Box office` per territory
- `Runtime cuts` (różne wersje montażowe)
- `Streaming availability` (linki deep-link)

### 4. Enricher "Kinoznawczy"
- **Motywy/Tematy**: "cyberpunk", "coming-of-age", "anti-hero"
- **Porównania**: "Jeśli lubisz X, spodoba Ci się Y" (similarity na embeddingach)
- **Kontekst kulturowy**: wpływy gatunkowe, styl operatora, muzyki, nagrody

### 5. Wyszukiwanie Wielojęzyczne
- Wielojęzyczne embeddingi
- `GET /search?q=киану ривз&locale=pl-PL` zwraca Keanu Reeves mimo innego alfabetu
- Normalizacja aliasów i transliteracja

### 6. Użytkownik Wybiera "Domyślną" Wersję
- `POST /movies/{id}/descriptions/{versionId}/select-default?locale=de-DE`
- **Głosowanie**: `POST /content/{versionId}/vote` → sygnał dla auto-selekcji

### 7. Tryb "Grounded Generation"
- Generacja tylko na podstawie zatwierdzonych faktów
- Pole `sources[]` (TMDB id, oficjalna strona)

### 8. Moderacja i Filtry
- **Toxicity/NSFW guard** per locale
- **Style constraints** (Family-safe → wyklucz wulgaryzmy, spoilery)

### 9. Analityka i Koszty
- Metryki per endpoint: tokens_used, avg_latency, cache_hit_rate
- Billing hooks pod plany RapidAPI

### 10. Webhooki i Asynchroniczność
- `POST /generate` → 202 Accepted + job_id
- Webhook: `/webhooks/generation-completed`
- Batch: `POST /batch/generate?ids[]=...&locales[]=....`

### 11. Edycje Ręczne i Lock
- Panel admina: edytowanie opisu + `locked=true`

### 12. Multimedia Lokalizowane
- Plakaty, trailery, napisy — warianty per region/język
- `image_locales` z alt_text w danym języku

### 13. "Knowledge Graph" i Mapowanie ID
- `external_ids`: TMDB, TVMaze, IMDb, Wikidata, OFDb, Filmweb
- `aka_titles` i aliases powiązane z regionami

### 14. Rekomendacje i Listy Redakcyjne
- "Najlepsze cyberpunkowe filmy lat 90." per locale
- "Podobne do X" (wektorowo + reguły gatunkowe)

---

## 🏗️ Architektura C4

### Poziom 1: Context Diagram
- **Użytkownik (Developer)** → korzysta z API przez RapidAPI
- **MovieMind API** → komunikuje się z:
  - AI Engine (LLM) - generowanie opisów
  - External Metadata Provider (TMDB, TVMaze, OMDB)
  - Database + Cache Layer (PostgreSQL + Redis)
  - Storage (S3) - zrzuty JSON, logi promptów

### Poziom 2: Container Diagram

| Kontener | Technologia | Odpowiedzialność |
|----------|-------------|------------------|
| **API Gateway** | Symfony API Platform | REST/GraphQL endpointy |
| **AI Service** | PHP microservice + OpenAI SDK | Generuje opisy, biografie, tagi |
| **Metadata Fetcher** | PHP Worker | Pobiera dane z TMDB/TVMaze |
| **Database** | PostgreSQL | Treści, metadane, wersje, tagi |
| **Cache** | Redis | Cache odpowiedzi API i AI |
| **Task Queue** | Redis Queue | Kolejkuje generowanie opisów |
| **Admin Panel** | React + API | Zarządzanie danymi, modelami |

### Poziom 3: Component Diagram

#### API Layer
- `GET /movies/{id}`
- `GET /movies/search?q=matrix`
- `GET /actors/{id}`
- `POST /generate/movie/{id}`
- `GET /movies/{id}/versions`

#### Domain Components
- **MovieService**: `fetchOrGenerate(id, contextTag)`, `compareVersions()`
- **ActorService**: `fetchBiography()`
- **AIContentGenerator**: generuje treść na podstawie promptów
- **VersionComparator**: analizuje różne wersje treści
- **CacheManager**: cache dla odpowiedzi API
- **RateLimiter**: kontroluje plany (free, pro, enterprise)

---

## 💰 Monetyzacja (RapidAPI) / Monetization (RapidAPI)

| Plan | Limit | Features |
|------|-------|----------|
| **Free** | 100 zapytań/miesiąc / 100 requests/month | Dostęp tylko do danych w bazie (bez generowania) / Access only to database data (without generation) |
| **Pro** | 10 000 zapytań/miesiąc / 10,000 requests/month | Możliwość regeneracji opisów AI i wyboru kontekstu / Ability to regenerate AI descriptions and choose context |
| **Enterprise** | Nielimitowany / Unlimited | API + dedykowane modele AI + webhooki / API + dedicated AI models + webhooks |

---

## 🔐 Bezpieczeństwo i Legalność / Security and Legal

### Zasady Bezpieczeństwa / Security Principles
- Każdy opis generowany od podstaw przez AI → brak plagiatu / Every description generated from scratch by AI → no plagiarism
- AI watermarking / fingerprint hash (sha256 prompt + output)
- Endpoint `/compliance/check` zwracający hash generacji / Endpoint `/compliance/check` returning generation hash
- Polityka: "Generated content is AI-authored and unique to each request" / Policy: "Generated content is AI-authored and unique to each request"

### Pipeline Jakości (per wersja językowa) / Quality Pipeline (per language version)
1. **Linting lokalizacyjny / Localization linting**: glosariusz (NO_TRANSLATE), transliteracja / glossary (NO_TRANSLATE), transliteration
2. **Fakty / Facts**: walidacja z metadanymi (rok, obsada, role) / validation with metadata (year, cast, roles)
3. **Czytelność / Readability**: Flesch/FK (próg per audience) / Flesch/FK (threshold per audience)
4. **Podobieństwo/unikalność / Similarity/uniqueness**: embedding cosine vs. istniejące wersje / embedding cosine vs. existing versions
5. **Toxicity/NSFW**: filtr per locale / filter per locale
6. **Hallucination score**: penalizuj treść przeczącą metadanym / penalize content contradicting metadata
7. **Auto-select**: wybierz selected_default na podstawie quality_score + feedback / select selected_default based on quality_score + feedback

### 🔒 Zarządzanie Kluczami API i Sekretami / API Keys and Secrets Management

#### Zasada Ogólna / General Principle
❌ **Nigdy nie commituj prawdziwych kluczy API** (OpenAI, RapidAPI, SMTP, RabbitMQ) / **Never commit real API keys** (OpenAI, RapidAPI, SMTP, RabbitMQ)  
✅ **Używaj .env tylko lokalnie/na serwerze** / **Use .env only locally/on server**  
✅ **Commituj wyłącznie .env.example** (placeholdery) / **Commit only .env.example** (placeholders)

#### Struktura Plików Środowiskowych

**`.env.example` (commitowany):**
```bash
# =========================================
# MovieMind API — Example Environment File
# =========================================
# Skopiuj ten plik do `.env` i uzupełnij własne dane.

APP_ENV=dev
APP_DEBUG=1
APP_MODE=mock   # demo | mock | prod

# Database
DATABASE_URL=postgresql://moviemind:moviemind@db:5432/moviemind

# Cache
REDIS_URL=redis://redis:6379/0

# AI Integrations (OpenAI or other)
OPENAI_API_KEY=sk-REPLACE_ME
OPENAI_MODEL=gpt-4o-mini

# RapidAPI
RAPIDAPI_KEY=your-rapidapi-key-here
RAPIDAPI_PLAN=FREE

# SMTP (Mailhog / sandbox)
SMTP_DSN=smtp://user:pass@mailhog:1025

# RabbitMQ (async queue)
RABBITMQ_DSN=amqp://guest:guest@rabbitmq:5672/%2f

# API Settings
API_RATE_LIMIT=60/minute
DEFAULT_LANGUAGE=pl-PL

# Security
JWT_SECRET=change-me-in-prod
ADMIN_DEFAULT_USER=admin
ADMIN_DEFAULT_PASS=admin123
```

**`.gitignore` (commitowany):**
```gitignore
# Environment files
.env
.env.local
.env.prod
.env.production

# Secrets / Keys
*.pem
*.key
*.crt
secrets/
jwt/
```

#### Tryby Środowiskowe

| Tryb | Opis | Klucze wymagane |
|------|------|-----------------|
| **mock** | demo, fake data | żadnych |
| **dev** | lokalne testy | tylko sandbox keys |
| **prod** | produkcja | prawdziwe klucze API |

#### Bezpieczne Przechowywanie Kluczy

**W środowiskach CI/CD:**
- **GitHub Actions** → Settings → Secrets → Actions
- **Railway/Render/AWS/GCP** → Environment Variables
- **Docker Secrets** → dla kontenerów produkcyjnych

**Lokalnie:**
- `.env` tylko na maszynie deweloperskiej
- Nigdy w historii Git (nawet usunięty commit można odzyskać)

#### Automatyczne Skanowanie Sekretów

**Gitleaks Configuration (`.gitleaks.toml`):**
```toml
title = "MovieMind API Secret Scanner"

[[rules]]
id = "openai-api-key"
description = "OpenAI API Key (sk-...)"
regex = '''sk-[a-zA-Z0-9]{20,}'''
tags = ["openai", "apikey"]

[[rules]]
id = "rapidapi-key"
description = "RapidAPI Key"
regex = '''[A-Za-z0-9]{32,}'''
tags = ["rapidapi", "apikey"]

[[rules]]
id = "jwt-secret"
description = "JWT Secret"
regex = '''(JWT_SECRET|jwt_secret|secret_key)\s*=\s*["']?[A-Za-z0-9\-_]{10,}["']?'''
tags = ["jwt", "secret"]
```

**Pre-commit Hook:**
```bash
#!/usr/bin/env bash
echo "🔎 Running Gitleaks..."
if ! command -v gitleaks >/dev/null 2>&1; then
  echo "⚠️  Gitleaks not found, skipping. Install with: brew install gitleaks"
  exit 0
fi

gitleaks protect --staged
status=$?
if [ $status -ne 0 ]; then
  echo "❌ Gitleaks found potential secrets. Commit aborted."
  exit $status
fi
echo "✅ Gitleaks OK"
```

#### Security Checklist (przed publikacją)

**Brak sekretów w repo:**
- [ ] `.env`, `.env.*` są w `.gitignore`
- [ ] `gitleaks detect --source .` przechodzi na czysto
- [ ] Brak kluczy w README, issue, commitach i historii

**Pliki środowiskowe:**
- [ ] Jest tylko `.env.example` z placeholderami
- [ ] README jasno opisuje kopiowanie do `.env` i tryb `APP_MODE=mock`

**Konfiguracja CI/CD:**
- [ ] Wszystkie klucze w GitHub Actions → Secrets
- [ ] Workflow nie wypisuje zmiennych środowisk w logach
- [ ] Artefakty buildów nie zawierają `.env`

**Endpointy i nagłówki:**
- [ ] Publiczne API wymaga `X-API-Key` (lub Bearer JWT dla admina)
- [ ] Odrzucasz żądania bez TLS
- [ ] Włączone nagłówki bezpieczeństwa

**Rate limiting & brute-force:**
- [ ] Rate limit per IP / plan działa
- [ ] Logowanie admin → limit prób + captcha

**Dzienniki i telemetry:**
- [ ] Logi nie zawierają tokenów, promptów, danych osobowych
- [ ] Maskujesz w logach wartości: `Authorization`, `X-API-Key`, `password`

**AI & prompty:**
- [ ] Prompt templates w publicznym repo są mock/okrojone
- [ ] Wersja prywatna trzyma realne prompty poza repo
- [ ] Włączony "content filter" / walidacja wejścia

**Zależności i obrazy:**
- [ ] `composer audit` / `npm audit` / skan obrazów
- [ ] Używasz pinowanych wersji i regularnych update'ów
- [ ] Docker: non-root user, READONLY filesystem, minimalne base image

---

## 📅 Roadmap

| Etap | Zadanie | Opis |
|------|---------|------|
| 1 | **MVP API** | `/movies/search`, `/movies/{id}`, `/actors/{id}` + cache |
| 2 | **AI Generator** | Integracja OpenAI, generowanie opisów |
| 3 | **Versioning** | Porównywanie wersji, ranking jakości |
| 4 | **Admin Panel** | Zarządzanie danymi i treściami |
| 5 | **RapidAPI Publish** | Utworzenie dokumentacji i planów |
| 6 | **Monitoring i scoring** | AI feedback loop i wersjonowanie treści |

---

## 🗄️ Technologie

| Warstwa | Propozycje |
|---------|------------|
| **Backend API** | Symfony 7.1 lub FastAPI (Python) |
| **AI Engine** | OpenAI GPT-4o / Claude / local Ollama |
| **DB** | PostgreSQL (dane + JSONB dla metadanych) |
| **Cache** | Redis |
| **Queue** | RabbitMQ |
| **Hosting** | Render / Railway / Vercel / AWS |
| **RapidAPI integration** | OpenAPI spec + dokumentacja JSON (Swagger) |
| **Monitoring** | Sentry + Prometheus/Grafana |

---

## 📊 Dodatkowe Dane do Przechowywania

### Wartościowe "Kolumny Bonusowe"
- `awards[]` (nominacje/wygrane, rok, kategoria, organizacja)
- `crew_roles[]` (dokładne role: director, cinematographer, composer)
- `shooting_locations[]` (kraj/miasto, studio, plener)
- `soundtrack[]` (kompozytor, utwory, label)
- `festival_premieres[]` (Cannes, Sundance, data)
- `censorship_notes[]` (cięcia, powody, kraj)
- `trivia[]` i `goofs[]` (z tagiem "spoiler")
- `influences[]` / `influenced_by[]` (powiązania między dziełami)
- `box_office` (budżet, domestic, international, currency, źródło)
- `availability[]` (platforma, kraj, od–do, link)
- `keywords[]` i `themes[]` (standaryzowane słowniki)
- `age_recommendation` per locale (opisowe, nie tylko rating literowy)
- `content_warnings` (przemoc, wulgaryzmy — przydatne rodzinom/edukacji)

---

## ⚖️ Strategia Licencjonowania

### Scenariusz A: Portfolio (tylko do wglądu)

**Licencja**: "No License" lub Creative Commons BY-NC (non-commercial)

**Zalety**: Maksymalna kontrola, nikt nie zarobi na Twoim kodzie  
**Wada**: Nie jest formalnie "open source" (nie pozwala legalnie forkować publicznie)

**Przykład LICENSE.txt:**
```
This project is for demonstration and educational purposes only.
You may not use, copy, modify, merge, publish, distribute, sublicense, or sell copies of the software without explicit permission from the author.
© 2025 Łukasz Zychal
```

### Scenariusz B: Open Source w Portfolio

**Licencja**: MIT lub Apache 2.0

| Licencja | Dla kogo dobra | Cechy |
|----------|----------------|-------|
| **MIT** | Dev-portfolio, edukacyjne repo | Bardzo prosta, pozwala używać kodu z zachowaniem copyright notice |
| **Apache 2.0** | Projekty z API / SaaS / integracje | Zawiera ochronę patentową, formalniejsza, dobra przy AI integracjach |

**Przykład MIT License:**
```
MIT License

Copyright (c) 2025 Łukasz Zychal

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, subject to inclusion of this copyright notice.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
```

### Scenariusz C: Komercyjny SaaS (RapidAPI / płatne API)

**Strategia dual-license:**
- **Public repo**: MIT / CC-BY-NC (non-commercial)
- **Private repo**: własna licencja komercyjna (np. "MovieMind Commercial License 1.0")

**To standardowy wzorzec:**
- ➡️ **Open Core** dla społeczności (marketing i portfolio)
- ➡️ **Closed Extensions** dla klientów (płatny RapidAPI plan, webhooki, billing, panel)

### 🧠 Rekomendacja dla MovieMind API

| Element | Wersja Publiczna | Wersja Prywatna |
|---------|------------------|-----------------|
| **Backend** | Symfony (MVP) | Symfony + AI Workers |
| **AI generacja** | stub/mock | pełny prompt i model |
| **Cache + DB** | ✅ | ✅ |
| **Rate Limit, Billing** | ❌ | ✅ |
| **Webhooki, Jobs, Admin Panel** | ❌ | ✅ |
| **Licencja** | MIT lub CC-BY-NC | własna ("MovieMind Commercial License") |

### 🔐 Dodatkowe Wskazówki

**W publicznym repo:**
- Szablon ENV bez kluczy: `OPENAI_API_KEY=<REPLACE_ME>`
- `.gitignore` z wykluczeniem: `.env`, `.env.local`, `.env.production`
- **Nigdy nie publikuj kluczy OpenAI/RapidAPI** – nawet w PoC

**W prywatnym repo:**
- Pełne klucze API w `.env` lub Vault
- Realne prompty AI i logika biznesowa
- Własna licencja komercyjna

---

## 🎯 Minimalne Decyzje na Start / Minimal Decisions to Start

- **Kanon / Canon**: en-US
- **Locale MVP**: en-US, pl-PL, de-DE
- **Długie teksty / Long texts**: generation-first
- **Krótkie / Short**: translate-then-adapt
- **Glosariusz / Glossary**: NO_TRANSLATE dla firm, wytwórni, marek, nazwisk, nagród / NO_TRANSLATE for companies, studios, brands, names, awards
- **Embeddingi / Embeddings**: jeden model multilingual / one multilingual model
- **Fallback**: jeśli locale brak — zwróć kanon + pending_generation=true / if locale missing — return canon + pending_generation=true

---

## 📘 MVP Output (Finalny Rezultat) / MVP Output (Final Result)

- 📁 repo moviemind-api
- ⚙️ działający docker-compose up / working docker-compose up
- 🧠 endpointy /v1/... działające w REST / /v1/... endpoints working in REST
- 💾 dane w PostgreSQL / data in PostgreSQL
- ⚡ async generacja opisów przez AI (Symfony Messenger) / async description generation by AI (Symfony Messenger)
- 🧱 prosty README.md i OpenAPI YAML / simple README.md and OpenAPI YAML

---

**Ten dokument stanowi kompletną specyfikację projektu MovieMind API, łącząc wszystkie aspekty: od MVP przez wielojęzyczność po zaawansowane funkcje i strategię biznesową.**

**This document constitutes a complete specification of the MovieMind API project, combining all aspects: from MVP through multilingualism to advanced features and business strategy.**
