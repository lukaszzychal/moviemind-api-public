# 🎬 MovieMind API

**API do metadanych filmów i seriali zasilane AI**

[![Licencja: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Wersja PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-ff2d20.svg)](https://laravel.com)
[![CI](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/ci.yml)
[![CodeQL](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/codeql.yml)
[![Code Security Scan](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/code-security-scan.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/code-security-scan.yml)
[![Docker Security Scan](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/docker-security-scan.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/docker-security-scan.yml)

> ℹ️ **English version:** [`README.md`](README.md)

## 🎯 Przegląd projektu

MovieMind API to **projekt portfolio/demo**, który demonstruje usługę REST do generowania i przechowywania unikalnych opisów filmów, seriali i aktorów przy użyciu technologii AI. W przeciwieństwie do klasycznych baz (IMDb, TMDb) MovieMind dostarcza oryginalne treści, obsługując wiele języków oraz różne style narracji.

**Uwaga:** To projekt portfolio z pełną funkcjonalnością do celów demonstracyjnych. Dla wdrożenia produkcyjnego mogą być wymagane licencje komercyjne (zobacz [Licencje API zewnętrznych](#-licencje-api-zewnętrznych) poniżej).

### 📸 Galeria

**Frontend (Demo publiczne):**
<div align="center">
  <img src="docs/img/Fronend/Zrzut%20ekranu%202026-03-21%20o%2017.34.48.png" width="49%" />
  <img src="docs/img/Fronend/Zrzut%20ekranu%202026-03-21%20o%2017.38.29.png" width="49%" />
</div>

**Panel Administracyjny (Filament):**
<div align="center">
  <img src="docs/img/Backend/Zrzut%20ekranu%202026-03-21%20o%2017.43.12.png" width="49%" />
  <img src="docs/img/Backend/Zrzut%20ekranu%202026-03-21%20o%2017.45.19.png" width="49%" />
</div>

## ✨ Kluczowe funkcje

- 🤖 **Generowanie treści przez AI**: Oryginalne opisy przy użyciu modeli OpenAI/LLM
- 🌍 **Wielojęzyczność**: Obsługa wielu lokalizacji
- 🎨 **Style kontekstowe**: Style opisów (modern, critical, humorous)
- ⚡ **Sprytne cache’owanie**: Redis ogranicza zbędne wywołania AI
- 🔄 **Przetwarzanie asynchroniczne**: Kolejki background do generowania treści
- 📊 **RESTful API**: Czyste, dobrze udokumentowane endpointy

## 🏗️ Architektura

### Stos technologiczny

| Komponent | Technologia | Cel |
|-----------|-------------|-----|
| **Backend** | Laravel 12 (PHP 8.3) | API (demo publiczne) |
| **Baza danych** | PostgreSQL | Persistencja danych |
| **Cache** | Redis | Optymalizacja wydajności |
| **Integracja AI** | OpenAI API | Generowanie treści |
| **System kolejek** | Laravel Horizon + Queues | Przetwarzanie asynchroniczne |
| **Dokumentacja** | OpenAPI/Swagger | Specyfikacja API |

### Schemat bazy danych

#### Kluczowe tabele

**Movies**
```sql
movies
├── id (PK)
├── title
├── release_year
├── director
├── genres (array)
└── default_description_id (FK)
```

**Movie Descriptions**
```sql
movie_descriptions
├── id (PK)
├── movie_id (FK)
├── locale (pl-PL, en-US)
├── text
├── context_tag (modern, critical, humorous)
├── origin (GENERATED/TRANSLATED)
├── ai_model (gpt-4o-mini)
└── created_at
```

**Actors & Bios**
```sql
actors
├── id (PK)
├── name
├── birth_date
├── birthplace
└── default_bio_id (FK)

actor_bios
├── id (PK)
├── actor_id (FK)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

**Jobs (Async Processing)**
```sql
jobs
├── id (PK)
├── entity_type (MOVIE, ACTOR)
├── entity_id
├── locale
├── status (PENDING, DONE, FAILED)
├── payload_json
└── created_at
```

## 🚀 Endpointy API

### Główne endpointy

| Metoda | Endpoint            | Opis                                                        |
| ------ | ------------------- | ----------------------------------------------------------- |
| `GET`  | `/v1/movies?q=`     | Wyszukiwanie filmów po tytule, roku, gatunku                |
| `GET`  | `/v1/movies/{slug}` | Szczegóły filmu + opis AI (kolejkuje generację gdy brak danych) |
| `POST` | `/v1/generate`      | Wyzwolenie nowej generacji AI                               |
| `GET`  | `/v1/jobs/{id}`     | Sprawdzenie statusu zadania                                |

### Przykład użycia

```bash
# Wyszukaj filmy
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies?q=matrix"

# Pobierz szczegóły filmu
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies/the-matrix"

# Wyzwól generowanie opisu
curl -X POST \
     -H "X-API-Key: <REPLACE_ME>" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "entity_id": 123, "locale": "pl-PL", "context_tag": "modern"}' \
     "https://api.moviemind.com/v1/generate"
```

## 🔄 Przepływ

### Happy Path

1. **Żądanie klienta**: `GET /v1/movies/the-matrix`
2. **Sprawdzenie bazy**: Czy opis już istnieje
3. **Generowanie AI** (jeśli potrzebne):
   - Tworzy rekord zadania ze statusem `PENDING`
   - Uruchamia worker przez Laravel Horizon
   - Worker wywołuje OpenAI z kontekstowym promptem
   - Wynik trafia do bazy, status zadania się aktualizuje
4. **Odpowiedź**: Zwracane są dane filmu z opisem AI
5. **Cache**: Kolejne zapytania trafiają do Redis

### Przykładowy prompt AI

```
Napisz zwięzły, unikalny opis filmu {title} z roku {year}.
Styl: {context_tag}.
Długość: 2–3 zdania, naturalny język, bez spoilera.
Język: {locale}.
Zwróć tylko czysty tekst.
```

## 🐳 Szybki start

### Wymagania

- Docker i Docker Compose
- Klucz API OpenAI

### Instalacja

1. **Klonowanie repozytorium**
   ```bash
   git clone https://github.com/lukaszzychal/moviemind-api-public.git
   cd moviemind-api-public
   ```

2. **Konfiguracja środowiska**
   ```bash
   # skopiuj szablon do katalogu aplikacji Laravel
   cp env/local.env.example api/.env
   # uzupełnij api/.env o klucz OpenAI
   ```

3. **Uruchomienie usług (Docker)**
   ```bash
   docker compose up -d --build
   ```

4. **Instalacja zależności backendu**
   ```bash
   docker compose exec php composer install
   ```

5. **Wygenerowanie klucza aplikacji**
   ```bash
   docker compose exec php php artisan key:generate
   ```

6. **Migracje bazy i dane demo**
   ```bash
   docker compose exec php php artisan migrate --seed
   ```

7. **Podgląd logów Horizon (kolejki działają w osobnym kontenerze)**
   ```bash
   docker compose logs -f horizon
   ```

### Konfiguracja Compose (`compose.yml`)

Szczegóły w `compose.yml` (PHP-FPM, Nginx, Postgres, Redis, Horizon).

## ☁️ Wdrożenie w Chmurze (Railway / Docker)

Projekt MovieMind API jest zoptymalizowany pod łatwe wdrożenia na platformach PaaS takich jak Railway lub własnych serwerach VPS opartych na Dockerze.

### Wdrożenie z Gotowych Obrazów (GHCR) - Zalecane 🚀
Dzięki GitHub Actions, obrazy dla środowisk Frontend i Backend budują się i tagują automatycznie przy wypychaniu kodu na główną gałąź.
Aby maksymalnie przyspieszyć wdrożenie i zaoszczędzić zasoby, wskaż źródło deploymentu jako "Docker Image" zamiast budować aplikację z kodu źródłowego:
- **Frontend**: `ghcr.io/twoj-uzytkownik/moviemind-api-public-frontend:latest`
- **Backend**: `ghcr.io/twoj-uzytkownik/moviemind-api-public-backend:latest`
*(W Railway po prostu wybierz opcję "Deploy from Docker Image" zamiast "Deploy from GitHub repo" i wklej powyższy, dostosowany URL).*

### Wdrożenie ze źródeł (Alternatywa)
Jeżeli wolisz budować środowisko z kodu w repozytorium, zastosuj poniższe reguły.

#### Frontend (Vue/Vite)
Frontend funkcjonuje jako aplikacja SPA (Single Page Application).
1. Utwórz nową usługę z repozytorium GitHub na platformie.
2. W ustawieniach wskaż **Katalog Główny (Root Directory)** na `/frontend`.
3. Platforma automatycznie wykryje i użyje dołączonego pliku `Dockerfile` do zbudowania i serwowania aplikacji przez Nginx.
4. W zmiennych środowiskowych zdefiniuj m.in. `VITE_API_URL`.

### Wdrożenie Backendu (Ukrycie Panelu Admina)
Aplikację Laravel możesz wdrożyć w sposób chroniący panel administracyjny Filament przed publicznym dostępem.
1. Utwórz nową usługę z repozytorium wskazując katalog `/api` jako główny.
2. **Instancja Publicznego API**:
   - Ustaw zmienną środowiskową `ADMIN_PANEL_ENABLED=false`.
   - Zintegrowany Middleware (`RestrictAdminPanel`) automatycznie zablokuje wszystkie próby wejścia na ścieżkę `/admin` (zwracając błąd 404 Not Found).
3. **Instancja Panelu Admina** (Opcjonalnie):
   - Stwórz osobną usługę opartą na tym samym kodzie.
   - Ustaw `ADMIN_PANEL_ENABLED=true` oraz podepnij do niej niejawną domenę.

## 📋 Przegląd funkcji

| Obszar | Publiczne demo (to repo) | Wersja komercyjna (prywatne) |
|--------|-------------------------|------------------------------|
| API | Endpointy REST dla filmów, osób, zadań async | Rozszerzone SLA, integracje partnerskie |
| Generowanie AI | `AI_SERVICE=mock` (deterministyczne demo) i `AI_SERVICE=real` z OpenAI | Multi-provider, kontrola kosztów, strażnicy halucynacji |
| Doświadczenie admina | Panel admin z flagami, CRUD, konta demo | Pełna konsola operacyjna z billingiem, analityką, audytem |
| Autoryzacja | Demo auth dla admina + otwarte API publiczne | Klucze na plan, OAuth/JWT, limity wg poziomów |
| Webhooki | Symulator endpointów + inspektor żądań | Produkcyjne procesory webhooków (Stripe/PayPal, partnerzy) |
| Monitoring | Dashboardy Telescope, przykładowe Grafana | Zaawansowane metryki, SLA, alerty on-call |
| Lokalizacja | Przykładowe treści wielojęzyczne + glosariusz | Pełna ścieżka tłumaczeń, prompty per locale |
| Dokumentacja | OpenAPI, notatki architektoniczne, przewodnik portfolio | Komercyjne runbooki, playbooki wdrożeniowe, dokumenty dla vendorów |

> 💡 Publiczne repo pokazuje kompetencje implementacyjne bez ujawniania wrażliwych integracji. Wersja prywatna zawiera poświadczenia, billing, compliance oraz integracje partnerów.

## 🔐 Autoryzacja i dostęp

W projekcie wykorzystywane są trzy typy autoryzacji, zależnie od kontekstu i endpointu.

### Podsumowanie typów autoryzacji

| Typ autoryzacji | Gdzie używany | Nagłówek / Metoda | Przykład |
|-----------------|---------------|-------------------|----------|
| **ApiKeyAuth** | `/api/v1/generate` i publiczne API | `X-API-Key` | `mm_abc123...` |
| **AdminToken** | `/api/v1/admin/*` | `X-Admin-Token` | Token z `.env` |
| **Basic Auth** | `/horizon` (produkcja) | HTTP Basic Auth | Username + Password |

### Szczegóły

1. **ApiKeyAuth (Public API)**
   - Używany do autoryzacji zapytań publicznych, np. generowania opisów.
   - Wymaga nagłówka `X-API-Key`.

2. **AdminToken (Admin API)**
   - Używany do zabezpieczenia endpointów administracyjnych.
   - Wymaga nagłówka `X-Admin-Token`.
   - Wartość tokena jest konfigurowana w pliku `.env`.

3. **Basic Auth (Horizon UI)**
   - Służy do zabezpieczenia panelu monitorowania kolejek Laravel Horizon.
   - **Ważne:** Używane tylko na środowisku produkcyjnym (wymuszane przez middleware).
   - W środowisku lokalnym (`local`) Basic Auth nie jest wymagane - możesz otworzyć `/horizon` bez logowania.

> 💡 **Rekomendacja:** Dokumentacja Swagger może nie zawierać informacji o Basic Auth dla Horizon, ponieważ jest to osobny interfejs UI, a nie endpoint API REST.

### Dostęp do wersji demo vs komercyjnej

- **Public demo:** endpointy API są otwarte lub używają kluczy demonstracyjnych. Panel admin korzysta z uproszczonej autoryzacji.
- **Wersja komercyjna:** może zawierać bardziej złożone mechanizmy, takie jak OAuth/JWT oraz limity zależne od subskrypcji.

Aby lokalnie przetestować logowanie, upewnij się, że posiadasz odpowiednie klucze w pliku `.env`.

### Usuwanie ujawnionych sekretów z historii Git

1. **Usuń sekret w bieżącej gałęzi** – usuń plik lub wrażliwe dane i wykonaj commit zabezpieczający (np. dodaj wpis do `.gitignore`).
2. **Przepisz historię repozytorium** – zastosuj `git filter-repo` (zalecane) albo `git filter-branch`/`BFG Repo-Cleaner`, aby usunąć sekret z wcześniejszych commitów. Przykład:
   ```bash
   git filter-repo --path sekrety.txt --invert-paths
   git push --force
   ```
3. **Zrotuj sekret** – potraktuj ujawnione hasła/klucze jako skompromitowane i wygeneruj nowe dane logowania.
4. **Poinformuj zespół** – współpracownicy muszą zaktualizować swoje klony (`git fetch --all`, `git reset --hard origin/<branch>` lub ponowne klonowanie).
5. **Włącz monitoring** – skonfiguruj skanowanie sekretów (np. GitHub Secret Scanning) i dodaj kontrolę w CI, która blokuje ponowne dodanie wrażliwych plików.

## 📚 Dokumentacja

- **Dokumentacja API**: dostępna pod `/api/doc` lokalnie
- **Specyfikacja OpenAPI**: `docs/openapi.yaml`
- **Diagramy architektury**: `docs/c4/`
- **GitHub Projects Setup**: [`docs/GITHUB_PROJECTS_SETUP.md`](docs/GITHUB_PROJECTS_SETUP.md) – przewodnik po zarządzaniu zadaniami
- **Portfolio Recommendations**: [`docs/PUBLIC_REPO_PORTFOLIO_RECOMMENDATIONS.md`](docs/PUBLIC_REPO_PORTFOLIO_RECOMMENDATIONS.md) – lista funkcji pod portfolio

## 🧪 Testowanie

```bash
# Pełen zestaw testów
docker compose exec php php artisan test

# Tylko testy feature
docker compose exec php php artisan test --testsuite=feature
```

## 🤖 Tryby AI

Aplikacja może pracować na deterministycznych danych demo lub wykonywać realne wywołania OpenAI:

- Ustaw `AI_SERVICE=mock` (domyślnie), aby korzystać z danych generowanych przez `MockGenerateMovieJob` / `MockGeneratePersonJob`.
- Ustaw `AI_SERVICE=real` oraz `OPENAI_API_KEY`, `OPENAI_MODEL` i opcjonalnie `OPENAI_URL`, aby uruchomić `RealGenerate*Job` korzystające z `OpenAiClientInterface`.

Po zmianie zmiennych środowiskowych wykonaj `php artisan config:clear` (lub zrestartuj kontenery), aby selector wczytał nowy tryb.

## 📈 Wydajność

- **Cache**: Redis dla często odczytywanych treści
- **Asynchroniczność**: generowanie AI nie blokuje odpowiedzi API
- **Optymalizacja bazy**: indeksy pod wyszukiwanie
- **Rate limiting**: ochrona przed nadużyciami

## 🤝 Współpraca

To publiczne repo demonstracyjne. Pełne funkcje komercyjne dostępne są w repo prywatnym.

### Proces deweloperski (Trunk-Based)

1. **Zsynchronizuj `main`** – regularnie pobieraj świeże zmiany i utrzymuj bazę releasowalną.
2. **Krótko żyjący branch (opcjonalnie)** – jeśli potrzebujesz, utwórz topic branch i utrzymuj go przez godziny, nie dni; alternatywnie pracuj bezpośrednio na `main` przy parach lub mob-programmingu.
3. **Wprowadź małe zmiany** – dziel duże feature'y na inkrementy chronione flagami funkcji lub configiem runtime.
4. **Uruchom pełne testy/CI** – lokalnie i w pipeline; merge jest możliwy tylko przy zielonym statusie.
5. **Szybki merge do `main`** – integruj bez czekania na długie PR; lekki review (np. pair review) lub auto-merge po pozytywnym CI.

## 📄 Licencja

Projekt objęty licencją MIT – szczegóły w pliku [LICENSE](LICENSE).

---

## ⚠️ Licencje API zewnętrznych

> **Nota prawna:** Powyższe informacje są aktualne na dzień powstania dokumentacji. Warunki licencyjne dostawców zewnętrznych mogą ulec zmianie. Zalecamy samodzielną weryfikację aktualnych warunków bezpośrednio na stronach dostawców API przed wdrożeniem produkcyjnym.

### TMDB (The Movie Database)

**Użycie portfolio/demo:**
- ✅ Użycie niekomercyjne dozwolone (z atrybucją)
- Wymagana atrybucja: logo TMDB + tekst + link

**Użycie produkcyjne:**
- ❌ **Wymagana licencja komercyjna**
- Kontakt: sales@themoviedb.org
- Szacunkowe koszty: ~$149/miesiąc (małe aplikacje) do $42,000/rok (enterprise)
- Szczegóły: [`docs/LEGAL_TMDB_LICENSE.md`](docs/LEGAL_TMDB_LICENSE.md)

### TVmaze

**Użycie portfolio i produkcyjne:**
- ✅ Użycie komercyjne dozwolone (darmowe, licencja CC BY-SA)
- Wymagana atrybucja: link do TVmaze
- Szczegóły: [`docs/LEGAL_TVMAZE_LICENSE.md`](docs/LEGAL_TVMAZE_LICENSE.md)

## 🔗 Powiązane projekty

- **Repo prywatne**: pełna wersja z billingiem, webhookami i panelem admin
- **Billing Provider Integration**: opcjonalna integracja z Stripe/PayPal dla produkcji
- **Strona dokumentacji**: rozbudowane materiały API

## 📞 Wsparcie

- **Issues**: [GitHub Issues](https://github.com/lukaszzychal/moviemind-api-public/issues)
- **Dyskusje**: [GitHub Discussions](https://github.com/lukaszzychal/moviemind-api-public/discussions) *(włącz w Settings → Features)*
- **E-mail**: lukasz.zychal.dev@gmail.com

## 🏆 Roadmap

- [ ] Panel admin do zarządzania treścią
- [ ] System webhooków w czasie rzeczywistym
- [ ] Zaawansowana analityka i metryki
- [ ] Wsparcie multi-tenant
- [ ] Wersjonowanie treści i testy A/B
- [ ] Integracje z popularnymi bazami filmowymi

---

**Stworzone z ❤️ przez [Łukasza Zychala](https://github.com/lukaszzychal)**

*To publiczne demo. Aby poznać funkcje produkcyjne, skontaktuj się w sprawie dostępu do repo prywatnego.*

