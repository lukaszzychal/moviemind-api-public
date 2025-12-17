# 🚀 Skrypt setup-local-testing.sh

Skrypt automatycznie przygotowujący środowisko Docker do testów lokalnych MovieMind API.

## 📋 Spis treści

- [Funkcjonalności](#funkcjonalności)
- [Wymagania](#wymagania)
- [Szybki start](#szybki-start)
- [Opcje](#opcje)
- [Przykłady użycia](#przykłady-użycia)
- [Tryby AI](#tryby-ai)
- [Rozwiązywanie problemów](#rozwiązywanie-problemów)

## ✨ Funkcjonalności

Skrypt automatycznie wykonuje:

1. ✅ **Sprawdza bezpieczeństwo** - weryfikuje czy działa w środowisku lokalnym
2. ✅ **Sprawdza Docker** - weryfikuje czy Docker jest zainstalowany i działa
3. ✅ **Uruchamia kontenery** - automatycznie uruchamia Docker Compose jeśli kontenery nie działają
4. ✅ **Konfiguruje aplikację** - instaluje zależności Composer i generuje klucz aplikacji
5. ✅ **Czyści bazę danych** - wykonuje `migrate:fresh` (pusta baza)
6. ✅ **Włącza flagi funkcji** - automatycznie włącza potrzebne flagi przez API:
   - `ai_description_generation`
   - `ai_bio_generation`
   - `tmdb_verification`
   - `debug_endpoints`

## 🔒 Bezpieczeństwo

**WAŻNE:** Skrypt działa **TYLKO** w środowisku lokalnym!

### Automatyczne sprawdzanie:

Skrypt automatycznie sprawdza przed uruchomieniem:

1. ✅ **APP_ENV** - musi być `local` lub `testing` w pliku `.env`
2. ✅ **API URL** - musi wskazywać na `localhost` lub `127.0.0.1`
3. ✅ **Hostname** - sprawdza czy nie wygląda na środowisko produkcyjne

### Co się stanie w produkcji:

Jeśli skrypt wykryje środowisko inne niż lokalne:
- ❌ **Zatrzyma się** przed wykonaniem jakichkolwiek operacji
- ⚠️ **Wyświetli ostrzeżenie** z wykrytymi problemami
- 🚫 **Nie wykona** żadnych zmian w bazie danych ani konfiguracji

### Wymuszenie uruchomienia (NIEZALECANE):

```bash
# TYLKO jeśli jesteś absolutnie pewien, że to środowisko lokalne
FORCE_LOCAL=true ./scripts/setup-local-testing.sh
```

**UWAGA:** Użycie `FORCE_LOCAL=true` w produkcji może:
- Wyczyścić bazę danych produkcyjną
- Zmienić konfigurację środowiska
- Spowodować przestoje

## 📦 Wymagania

- **Docker** i **Docker Compose** zainstalowane i działające
- Dostęp do portów: `8000` (API), `5433` (PostgreSQL), `6379` (Redis)
- (Opcjonalnie) Klucz OpenAI API dla trybu `real`

## 🚀 Szybki start

### Podstawowe użycie (tryb mock)

```bash
./scripts/setup-local-testing.sh
```

To uruchomi środowisko z trybem **mock** (deterministyczne dane, bez kosztów OpenAI).

### Użycie z trybem real (OpenAI)

```bash
# 1. Ustaw klucz OpenAI w api/.env
echo "OPENAI_API_KEY=sk-twoj-klucz" >> api/.env

# 2. Uruchom skrypt z trybem real
./scripts/setup-local-testing.sh --ai-service real
```

## ⚙️ Opcje

### Opcje wiersza poleceń

| Opcja | Opis |
|-------|------|
| `-h, --help` | Wyświetla pomoc |
| `--api-url URL` | URL bazy API (domyślnie: `http://localhost:8000`) |
| `--ai-service MODE` | Tryb AI: `mock` (domyślnie) lub `real` |
| `--no-start` | Nie uruchamia kontenerów (zakłada że już działają) |
| `--rebuild` | Rebuild kontenerów przed uruchomieniem |

### Zmienne środowiskowe

| Zmienna | Opis | Domyślna wartość |
|---------|------|------------------|
| `API_BASE_URL` | URL bazy API | `http://localhost:8000` |
| `ADMIN_AUTH` | Dane autoryzacji w formacie `user:password` | - |
| `DOCKER_COMPOSE_CMD` | Komenda docker compose | `docker compose` |
| `AI_SERVICE` | Tryb AI: `mock` lub `real` | `mock` |

## 📝 Przykłady użycia

### 1. Podstawowe użycie (mock)

```bash
./scripts/setup-local-testing.sh
```

### 2. Tryb real z OpenAI

```bash
# Upewnij się, że masz OPENAI_API_KEY w api/.env
./scripts/setup-local-testing.sh --ai-service real
```

### 3. Rebuild kontenerów

```bash
./scripts/setup-local-testing.sh --rebuild
```

### 4. Rebuild z trybem real

```bash
./scripts/setup-local-testing.sh --ai-service real --rebuild
```

### 5. Jeśli kontenery już działają

```bash
./scripts/setup-local-testing.sh --no-start
```

### 6. Z niestandardowym URL API

```bash
API_BASE_URL=http://localhost:8080 ./scripts/setup-local-testing.sh
```

### 7. Z autoryzacją admin

```bash
ADMIN_AUTH="admin:haslo" ./scripts/setup-local-testing.sh
```

### 8. Kombinacja opcji

```bash
./scripts/setup-local-testing.sh --ai-service real --rebuild
```

## 🤖 Tryby AI

### Tryb Mock (`--ai-service mock`)

- ✅ **Domyślny tryb** - nie wymaga klucza OpenAI
- ✅ **Deterministyczne dane** - zawsze te same wyniki
- ✅ **Bez kosztów** - nie wykonuje wywołań do OpenAI
- ✅ **Szybki** - idealny do testów i CI/CD
- ⚠️ **Ograniczone** - nie testuje prawdziwego AI

**Użycie:**
```bash
./scripts/setup-local-testing.sh --ai-service mock
# lub po prostu
./scripts/setup-local-testing.sh
```

### Tryb Real (`--ai-service real`)

- ✅ **Prawdziwe AI** - używa OpenAI API
- ✅ **Rzeczywiste wyniki** - testuje pełny flow
- ⚠️ **Wymaga klucza** - `OPENAI_API_KEY` musi być ustawiony
- ⚠️ **Kosztowny** - wykonuje prawdziwe wywołania API
- ⚠️ **Wolniejszy** - zależy od szybkości OpenAI API

**Użycie:**
```bash
# 1. Ustaw klucz w api/.env
echo "OPENAI_API_KEY=sk-twoj-klucz" >> api/.env

# 2. Uruchom z trybem real
./scripts/setup-local-testing.sh --ai-service real
```

**Gdzie znaleźć klucz OpenAI:**
- Zaloguj się na https://platform.openai.com/api-keys
- Utwórz nowy klucz API
- Skopiuj klucz do `api/.env`

## 🔧 Rozwiązywanie problemów

### Problem: Skrypt blokuje uruchomienie (środowisko nie lokalne)

Jeśli skrypt wykryje środowisko inne niż lokalne:

```bash
# Sprawdź APP_ENV w .env
grep APP_ENV api/.env

# Jeśli nie jest 'local', zmień na:
echo "APP_ENV=local" >> api/.env

# Sprawdź API_BASE_URL
echo $API_BASE_URL

# Powinien być: http://localhost:8000
```

**Jeśli jesteś pewien, że to środowisko lokalne:**
```bash
FORCE_LOCAL=true ./scripts/setup-local-testing.sh
```

### Problem: Docker nie działa

```bash
# Sprawdź czy Docker działa
docker info

# Jeśli nie działa, uruchom Docker Desktop
```

### Problem: Port 8000 zajęty

```bash
# Sprawdź co używa portu 8000
lsof -i :8000

# Zatrzymaj lokalny serwer Laravel
pkill -f "php artisan serve"

# Lub użyj innego portu w docker-compose.yml
```

### Problem: Kontenery nie startują

```bash
# Sprawdź logi
docker compose logs

# Rebuild od zera
docker compose down -v
./scripts/setup-local-testing.sh --rebuild
```

### Problem: Błąd "API nie odpowiada"

```bash
# Sprawdź czy kontenery działają
docker compose ps

# Sprawdź logi nginx
docker compose logs nginx

# Sprawdź logi php
docker compose logs php

# Poczekaj dłużej (czasami potrzeba więcej czasu)
sleep 10
curl http://localhost:8000/api/v1/health/openai
```

### Problem: Błąd autoryzacji przy włączaniu flag

```bash
# Sprawdź czy autoryzacja jest wymagana
curl http://localhost:8000/api/v1/admin/flags

# Jeśli wymagana, ustaw ADMIN_AUTH
ADMIN_AUTH="admin:haslo" ./scripts/setup-local-testing.sh

# Lub dodaj do api/.env
echo "ADMIN_BASIC_AUTH_PASSWORD=haslo" >> api/.env
```

### Problem: Tryb real nie działa

```bash
# Sprawdź czy klucz jest ustawiony
grep OPENAI_API_KEY api/.env

# Sprawdź czy klucz jest poprawny
docker compose exec php php artisan tinker
>>> env('OPENAI_API_KEY')

# Sprawdź logi horizon (kolejki)
docker compose logs horizon
```

### Problem: Baza danych nie czyści się

```bash
# Sprawdź połączenie z bazą
docker compose exec php php artisan migrate:status

# Ręczne czyszczenie
docker compose exec php php artisan migrate:fresh --force

# Sprawdź logi
docker compose logs db
```

## 📊 Co skrypt robi krok po kroku

1. **Sprawdza Docker** - weryfikuje instalację i działanie
2. **Sprawdza kontenery** - czy już działają
3. **Uruchamia kontenery** - jeśli nie działają (z rebuild jeśli potrzeba)
4. **Konfiguruje .env** - ustawia `AI_SERVICE` w pliku `.env`
5. **Instaluje zależności** - `composer install`
6. **Generuje klucz** - `php artisan key:generate`
7. **Czeka na API** - sprawdza czy API odpowiada (max 30 prób)
8. **Czyści bazę** - `php artisan migrate:fresh`
9. **Włącza flagi** - przez API endpoint `/api/v1/admin/flags/{name}`

## 🎯 Typowe scenariusze

### Scenariusz 1: Pierwsze uruchomienie

```bash
# 1. Skopiuj szablon .env (jeśli nie istnieje)
cp env/local.env.example api/.env

# 2. Uruchom skrypt
./scripts/setup-local-testing.sh

# 3. Sprawdź czy działa
curl http://localhost:8000/api/v1/health/openai
```

### Scenariusz 2: Test z prawdziwym AI

```bash
# 1. Dodaj klucz OpenAI
echo "OPENAI_API_KEY=sk-twoj-klucz" >> api/.env

# 2. Uruchom z trybem real
./scripts/setup-local-testing.sh --ai-service real

# 3. Przetestuj generowanie
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "entity_id": 1, "locale": "en-US"}'
```

### Scenariusz 3: Czysty restart

```bash
# Zatrzymaj wszystko
docker compose down -v

# Uruchom od nowa
./scripts/setup-local-testing.sh --rebuild
```

### Scenariusz 4: Zmiana trybu AI bez restartu

```bash
# Jeśli kontenery już działają, skrypt automatycznie:
# 1. Zaktualizuje .env
# 2. Zrestartuje kontenery php i horizon
./scripts/setup-local-testing.sh --ai-service real
```

## 📚 Dodatkowe informacje

### Przydatne komendy Docker

```bash
# Status kontenerów
docker compose ps

# Logi wszystkich serwisów
docker compose logs -f

# Logi konkretnego serwisu
docker compose logs -f php
docker compose logs -f nginx
docker compose logs -f horizon

# Wejście do kontenera
docker compose exec php bash

# Restart serwisu
docker compose restart php
docker compose restart horizon

# Zatrzymanie
docker compose down

# Zatrzymanie z usunięciem wolumenów (czysta baza)
docker compose down -v
```

### Sprawdzanie konfiguracji

```bash
# Sprawdź tryb AI
docker compose exec php php artisan tinker
>>> config('services.ai.service')

# Sprawdź flagi
curl http://localhost:8000/api/v1/admin/flags

# Sprawdź konfigurację debug (wymaga flagi debug_endpoints)
curl http://localhost:8000/api/v1/admin/debug/config
```

## 🆘 Wsparcie

Jeśli napotkasz problemy:

1. Sprawdź logi: `docker compose logs`
2. Sprawdź status: `docker compose ps`
3. Sprawdź dokumentację: `./scripts/setup-local-testing.sh --help`
4. Sprawdź główny README: `README.md`

## 📝 Notatki

- Skrypt automatycznie wykrywa czy kontenery już działają
- Jeśli kontenery działają, skrypt nie uruchamia ich ponownie (chyba że użyto `--rebuild`)
- Zmiana trybu AI automatycznie restartuje kontenery `php` i `horizon`
- Flagi funkcji są włączane przez API, więc wymagają działającego API
- Baza danych jest zawsze czyszczona (`migrate:fresh`)

---

**Wersja:** 1.0  
**Ostatnia aktualizacja:** 2025-01-15

