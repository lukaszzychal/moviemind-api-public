# MovieMind API - Kompletna Specyfikacja i Plan Działania

> **📝 Uwaga**: Ten dokument łączy oryginalny wątek ChatGPT z kompleksowymi specyfikacjami i planami działania dla projektu MovieMind API.

## 🎬 Przegląd Projektu

**MovieMind API** to AI-powered Film & Series Metadata API, które generuje i przechowuje unikalne opisy filmów, seriali i aktorów wykorzystując sztuczną inteligencję, cache i automatyczny wybór najlepszych wersji treści.

### 🎯 Główne Cele

**Cel projektu:**
Udostępnić API, które:
- generuje i przechowuje unikalne opisy filmów, seriali i aktorów
- wykorzystuje AI (np. ChatGPT / LLM API) do tworzenia treści
- dba o unikalność (żadnego kopiowania z IMDb, TMDb itp.)
- umożliwia cache, wielojęzyczność i tagowanie stylu opisu
- pozwala klientom pobierać dane przez REST API

### 💡 Rodzaj Produktu

**MVP (Minimum Viable Product)** – pierwsza działająca wersja z minimalnym zakresem funkcji, możliwa do wystawienia na RapidAPI.

**Nie PoC, bo:**
- PoC = tylko dowód, że da się generować tekst AI (bez systemu, bazy, API)
- MVP = ma już prawdziwe API, cache, minimalny storage i klucz API

---

## 🏗️ Strategia Dual-Repository

### 🧩 Podejście Dual-Repository

| Aspekt | Repozytorium Publiczne | Repozytorium Prywatne |
| ----------------- | --------------------------------------------- | --------------------------------------------- |
| **Cel** | Portfolio, demonstracja umiejętności | Produkcja, komercyjny produkt |
| **Zawartość** | Okrojony kod, mock AI, dokumentacja | Pełny kod, realne AI, billing, webhooki |
| **Bezpieczeństwo** | Brak kluczy API, przykładowe dane | Prawdziwe klucze, dane produkcyjne |
| **Licencja** | MIT / CC-BY-NC | Własna komercyjna |
| **Timeline** | 6 tygodni (MVP) | 8-12 tygodni (pełny produkt) |

### ✅ Dlaczego to dobre rozwiązanie

**🔹 1. Wizerunkowo**
Publiczne repo pokazuje:
- Podejście do architektury (DDD, CQRS, C4)
- Strukturę projektu (Docker, README, tests, configi)
- Czysty kod (Laravel / FastAPI / Python / SQL / YAML)
- Dobre praktyki: separacja domen, ENV, clean prompt design

**🔹 2. Bezpieczniej i bardziej elastycznie**
Prywatne repo może zawierać:
- klucze API (OpenAI, RapidAPI, SMTP, RabbitMQ) — w .env lub Vault
- pełny workflow (CI/CD, webhooki)
- analizy wydajności, monitoringi, testy integracyjne
- AI logic (prompt templates, selection heuristics) — Twoja "tajemnica handlowa"

### 📁 Podział w Praktyce

**Repo publiczne (`moviemind-api-public`):**
```
├── README.md
├── src/
├── docker-compose.yml
├── docs/
├── .env.example
├── .gitignore
├── .gitleaks.toml
└── LICENSE
```

**Repo prywatne (`moviemind-api-private`):**
```
├── all public files
├── .env.production
├── src/AI/
├── src/Webhooks/
├── src/Billing/
├── src/Admin/
├── tests/integration/
└── LICENSE (commercial)
```

---

## 🧩 Zakres MVP

### 🔹 1. Zakres Funkcjonalny

**Użytkownik (klient API) może:**
| Endpoint | Opis |
| --- | --- |
| `GET /v1/movies?q=` | wyszukać filmy (tytuł, rok, gatunek) |
| `GET /v1/movies/{id}` | pobrać szczegóły filmu + opis (AI lub cache) |
| `GET /v1/people/{id}` | pobrać dane osoby (aktor, reżyser itd.) + biografię |
| `GET /v1/actors/{id}` | alias dla wybranych osób typu aktor (kompatybilność) |
| `POST /v1/generate` | wymusić generację: `entity_type` = `MOVIE` lub `PERSON` |
| `GET /v1/jobs/{id}` | sprawdzić status generacji (PENDING, DONE, FAILED) |

**System (wewnętrznie):**
- zapisuje dane w PostgreSQL (movies, actors, descriptions, bios, jobs)
- jeśli danych nie ma → generuje przez AI (np. OpenAI API)
- wynik trzyma w DB i cache (Redis)
- przy kolejnym zapytaniu używa cache (nie pyta AI)
- każde wygenerowanie zapisuje z kontekstem (modern, critical, humorous, …)

### 🔹 2. Technologie MVP

#### 🏗️ Architektura Laravel

| Kontener | Technologia | Odpowiedzialność |
| ---------- | ------------- | ------------------ |
| **Laravel API** | PHP 8.3 + Laravel 11 | REST endpointy (filmy, aktorzy, AI generacja) |
| **Admin Panel** | Laravel (Nova/Breeze) | Zarządzanie danymi, modelami AI, monitoring |
| **AI Service** | OpenAI SDK (PHP) | Generuje opisy, biografie, tagi kontekstowe w Laravel Jobs |
| **Database** | PostgreSQL | Treści, metadane, wersje, tagi, ratingi jakości |
| **Cache** | Redis | Cache odpowiedzi API i wyników AI |
| **Task Queue** | Laravel Horizon | Kolejki generacji i async processing |

#### ⚡ `/src-fastapi/` — lekki, publiczny, skalowalny API Core

Technologia: Python + FastAPI + Celery + RabbitMQ + Redis
Cel: API-as-a-Service (publiczne endpointy, AI generacja, async jobs)

### 🔹 3. Struktura Danych

#### Tabela: movies
| Pole | Typ | Opis |
| ------ | ----- | ------ |
| id | int | PK |
| title | varchar | Tytuł |
| release_year | smallint | Rok produkcji |
| director | varchar | Reżyser |
| genres | text[] | Gatunki |
| default_description_id | int | referencja do opisu |

#### Tabela: movie_descriptions
| Pole | Typ | Opis |
| ------ | ----- | ------ |
| id | int | PK |
| movie_id | int FK | - |
| locale | varchar(10) | np. pl-PL, en-US |
| text | text | treść opisu |
| context_tag | varchar(64) | np. modern, critical |
| origin | varchar(32) | GENERATED / TRANSLATED |
| ai_model | varchar(64) | np. gpt-4o-mini |
| created_at | timestamp | - |

#### Tabela: actors
| Pole | Typ |
| ------ | ----- |
| id | int |
| name | varchar |
| birth_date | date |
| birthplace | varchar |
| default_bio_id | int |

#### Tabela: actor_bios
| Pole | Typ |
| ------ | ----- |
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
| ------ | ----- |
| id | int |
| entity_type | varchar(16) (MOVIE, ACTOR) |
| entity_id | int |
| locale | varchar(10) |
| status | varchar(16) (PENDING, DONE, FAILED) |
| payload_json | jsonb |
| created_at | timestamp |

### 🔹 4. MVP – Przepływ Działania (Happy Path)

1️⃣ Klient: `GET /v1/movies/123` → sprawdzenie DB (movie_descriptions)

2️⃣ Brak? → zapis jobs i uruchomienie workera

3️⃣ Worker (AI) → generuje opis, zapisuje do `movie_descriptions`, `jobs.status = DONE`

4️⃣ Klient: `GET /v1/jobs/{id}` → status i wynik

5️⃣ Kolejne zapytania trafiają w cache/DB

### 🔹 5. Przykładowy Prompt (PL)
```
„Napisz zwięzły, unikalny opis filmu {title} z roku {year}.
Styl: {context_tag}.
Długość: 2–3 zdania, naturalny język, bez spoilera.
Język: {locale}.
Zwróć tylko czysty tekst."
```

### 🔹 6. Środowisko Uruchomieniowe
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
  redis:
    image: redis:7
```

### 🔹 7. Zakres MVP (co NIE wchodzi)
🚫 brak UI (panel admin)
🚫 brak webhooków
🚫 brak billingów i planów
🚫 brak multiuser auth
🚫 brak monitoringów/metryk
🚫 brak AI porównywania wersji (tylko generacja i zapis)
🚫 brak tłumaczeń automatycznych

### 💰 Po co MVP?
- Weryfikacja zapotrzebowania (RapidAPI)
- Pomiar kosztów generacji i cache
- Fundament pod wersję PRO (webhooki, rate limit, billing)

### 📘 MVP Output
- repo moviemind-api
- docker-compose up
- endpointy /v1/... w REST
- dane w PostgreSQL
- async generacja opisów (Laravel Jobs)
- README.md i OpenAPI YAML

---

## 📋 Plan Działania - 10 Faz

### Faza 1: Setup i Struktura (Tydzień 1)
- Publiczne repo, security features, template repo
- Struktura projektu (docs, scripts, api/, tests, docker)

### Faza 2: Infrastruktura i Docker (Tydzień 2)
- Mock environment dla public repo (Laravel + Redis + Postgres)
- Production env dla prywatnego (sekrety z ENV)

### Faza 3: Mock API Endpoints (Tydzień 3)
- Podstawowe endpointy filmów/aktorów, job status

### Faza 4: Mock AI Integration (Tydzień 4)
- `MockAIService`, generacja przykładowych treści

### Faza 5: Real AI Integration (Tydzień 5-6)
- Integracja z OpenAI, realne prompty i modele

### Faza 6: Caching i Performance (Tydzień 7)
- Redis Cache dla opisów

### Faza 7: Multilingual Support (Tydzień 8)
- Obsługa wielu lokalizacji, generacja/tłumaczenie

### Faza 8: Testy i QA (Tydzień 9)
- Testy jednostkowe, integracyjne, metryki jakości

### Faza 9: Dokumentacja i API Docs (Tydzień 10)
- OpenAPI, README, przykłady użycia

### Faza 10: RapidAPI i Launch (Tydzień 11-12)
- Webhooki, plany, publikacja

---

## 🌳 Git Trunk Flow

### 🇵🇱 Strategia Zarządzania Kodem
Używamy Git Trunk Flow jako głównej strategii zarządzania kodem dla MovieMind API.

### ✅ Zalety Trunk Flow
- Prostszy workflow — jeden główny branch (main)
- Szybsze integracje — częste mergowanie do main
- Mniej konfliktów — krótsze feature branche
- Lepsze CI/CD — każdy commit na main może być deployowany
- Feature flags — kontrola funkcji bez branchy
- Rollback — łatwy rollback przez feature flags

### 🔄 Workflow
1. Feature branch — `feature/ai-description-generation`
2. Pull Request — code review i testy
3. Merge do main — po zatwierdzeniu
4. Deploy — automatyczny deploy z feature flags
5. Feature flag — kontrola włączenia funkcji

---

## 🎛️ Feature Flags

### 🇵🇱 Strategia Kontroli Funkcji
Używamy oficjalnej integracji Laravel Feature Flags (`laravel/feature-flags`) zamiast własnej implementacji.

### ✅ Zalety oficjalnej integracji Laravel
- Oficjalne wsparcie
- Prostota
- Bezpieczeństwo
- Integracja
- Więcej funkcji out-of-the-box
- Utrzymywane przez zespół Laravel

### 🎛️ Typy Feature Flags
1. Boolean flags — włącz/wyłącz funkcje
2. Percentage flags — gradual rollout (0-100%)
3. User-based flags — dla konkretnych użytkowników
4. Environment flags — różne ustawienia per środowisko

### ⚙️ Konfiguracja Feature Flags (przykład)
```php
<?php
return [
    'ai_description_generation' => true,
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25
    ],
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50
    ],
    'style_packs' => false
];
```

---

## 🔐 Bezpieczeństwo i Zarządzanie Kluczami

### 🔒 Zasada Ogólna
❌ Nigdy nie commituj prawdziwych kluczy API (OpenAI, RapidAPI, SMTP, RabbitMQ)
✅ Używaj `.env` tylko lokalnie/na serwerze
✅ Commituj wyłącznie `.env.example` (placeholdery)

### 📄 Struktura Plików Środowiskowych
`/.env.example` (commitowany) — z placeholderami i opisami

---

## 💰 Monetyzacja (RapidAPI)

| Plan | Limit | Funkcje |
| ------ | ------- | ---------- |
| Free | 100 zapytań/miesiąc | Dostęp tylko do danych w bazie (bez generowania) |
| Pro | 10 000 zapytań/miesiąc | Regeneracja opisów AI i wybór kontekstu |
| Enterprise | Nielimitowany | API + dedykowane modele AI + webhooki |

---

## ⚖️ Strategia Licencjonowania

### Scenariusz A: Portfolio (tylko do wglądu)
Licencja: "No License" lub Creative Commons BY-NC (non-commercial)

### Scenariusz B: Open Source w Portfolio
Licencja: MIT lub Apache 2.0

### Scenariusz C: Komercyjny SaaS (RapidAPI / płatne API)
Strategia dual-license:
- Public repo: MIT / CC-BY-NC (non-commercial)
- Private repo: własna licencja komercyjna

---

## 🎯 Podsumowanie Strategii

### 📊 Porównanie Repozytoriów
| Aspekt | Publiczne | Prywatne |
| ----------------- | ------------------- | ------------------- |
| **Kod** | Mock services, przykładowe dane | Prawdziwe AI, produkcyjne dane |
| **Bezpieczeństwo** | Brak kluczy API | Prawdziwe klucze, webhooki |
| **Testy** | Unit, mock | Integracyjne, E2E |
| **Dokumentacja** | Portfolio, architektura | API docs, deployment |
| **Licencja** | MIT | Komercyjna |

### 🚀 Następne Kroki
1. Tydzień 1-2: Setup repozytoriów i infrastruktury
2. Tydzień 3-4: Mock API w publicznym repo
3. Tydzień 5-6: Prawdziwe AI w prywatnym repo
4. Tydzień 7-8: Cache i wielojęzyczność
5. Tydzień 9-10: Testy i dokumentacja
6. Tydzień 11-12: RapidAPI i launch

### 💡 Kluczowe Zasady
- Bezpieczeństwo: Nigdy nie commituj prawdziwych kluczy API
- Separacja: Publiczne repo = portfolio, Prywatne repo = produkt
- Jakość: Wysokie standardy kodu w obu repozytoriach
- Dokumentacja: Kompletna dokumentacja dla każdego komponentu

---

*Dokument utworzony: 2025-01-27*


