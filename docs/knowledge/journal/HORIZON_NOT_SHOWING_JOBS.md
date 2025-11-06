# 🔍 Problem: Horizon nie pokazuje jobów

**Data:** 2025-11-01  
**Status:** ✅ Zidentyfikowane

---

## 🎯 Problem

**Horizon dashboard (`http://localhost:8000/horizon/dashboard`) jest pusty, mimo że joby są w kolejce.**

---

## 🔍 Przyczyna

### **1. Queue Connection Mismatch**

- ✅ **Aplikacja używa:** `QUEUE_CONNECTION=database` (PostgreSQL)
- ❌ **Horizon pokazuje tylko:** Redis queue
- ❌ **Horizon container:** Nie jest uruchomiony

**Stan:**
```
Jobs w database: 23 ✅
Jobs w Redis: 0 ❌
Queue connection: database
```

**Horizon działa TYLKO z Redis!**

---

## ✅ Rozwiązania

### **Opcja 1: Uruchomić Queue Worker dla Database (Szybkie rozwiązanie)**

Horizon nie wspiera `database` driver, więc trzeba użyć `queue:work`:

```bash
# Uruchom worker w kontenerze php
docker-compose exec php php artisan queue:work --tries=3
```

**Lub jako daemon:**
```bash
# W tle
docker-compose exec -d php php artisan queue:work --tries=3 --timeout=120
```

---

### **Opcja 2: Zmienić na Redis + Uruchomić Horizon (Rekomendowane)**

**Krok 1: Zmienić queue connection na Redis**

W `.env` lub zmiennych środowiskowych:
```bash
QUEUE_CONNECTION=redis
```

**Krok 2: Uruchomić Horizon**

```bash
# Uruchom kontener horizon
docker-compose up -d horizon

# Sprawdź logi
docker-compose logs -f horizon
```

**Krok 3: Przetworzyć stare joby z database**

Jeśli chcesz przetworzyć 23 joby z database, możesz je przenieść do Redis lub po prostu poczekać na nowe joby (nowe trafią do Redis).

---

## 🔄 Różnice między Database a Redis Queue

| Aspekt | Database Queue | Redis Queue |
|--------|----------------|-------------|
| **Driver** | `database` | `redis` |
| **Gdzie są joby** | Tabela `jobs` w PostgreSQL | Redis keys `queues:*` |
| **Horizon support** | ❌ Nie | ✅ Tak |
| **Worker** | `php artisan queue:work` | `php artisan horizon` lub `queue:work` |
| **Performance** | Wolniejsze | Szybsze |
| **Setup** | Prostsze (już masz DB) | Wymaga Redis |

---

## 🛠️ Szybka Diagnoza

**Sprawdź aktualny stan:**

```bash
# 1. Jaki queue connection?
docker-compose exec php php artisan tinker --execute="echo config('queue.default');"

# 2. Ile jobów w database?
docker-compose exec php php artisan tinker --execute="echo DB::table('jobs')->count();"

# 3. Ile jobów w Redis?
docker-compose exec redis redis-cli LLEN "queues:default"

# 4. Czy Horizon działa?
docker-compose ps horizon
```

---

## 📊 Obecny Stan (2025-11-01)

```
✅ Queue connection: database
✅ Jobs w database: 23
❌ Jobs w Redis: 0
❌ Horizon container: Nie uruchomiony
❌ Horizon dashboard: Pusty (bo szuka w Redis, nie w database)
```

---

## ✅ Rekomendowane Działanie

**Dla szybkiego rozwiązania (przetworzyć obecne joby):**

```bash
# Uruchom queue worker dla database
docker-compose exec -d php php artisan queue:work database --tries=3 --timeout=120
```

**Dla długoterminowego rozwiązania (Horizon dashboard):**

1. Zmień `QUEUE_CONNECTION=redis` w `.env`
2. Uruchom `docker-compose up -d horizon`
3. Nowe joby będą widoczne w Horizon dashboard

---

**Ostatnia aktualizacja:** 2025-11-01

