# 🔒 Redis Persistence i Bezpieczeństwo Jobów

**Data:** 2025-11-01

---

## ❓ Pytanie

**"Horizon używa Redis, a Redis chyba nie jest trwałą pamięcią? Co się stanie w razie awarii? Czy joby ulegną usunięciu?"**

---

## ✅ Odpowiedź: Redis JEST trwałą pamięcią!

### **1. Redis AOF (Append Only File)** ✅ Włączone

**Konfiguracja:** `compose.yml`

```yaml
redis:
  command: ["redis-server", "--appendonly", "yes"]  # ← AOF włączony!
  volumes:
    - redis_data:/data  # ← Trwały volume!
```

**Co to oznacza:**
- ✅ Wszystkie operacje zapisu są **logowane do pliku** (`appendonly.aof`)
- ✅ W razie restartu Redis **odtwarza dane** z AOF
- ✅ **Trwała pamięć** - nie tracisz danych

**Jak działa:**
```
Redis zapisuje każdą operację:
LPUSH queues:default job-data
→ Zapisuje do appendonly.aof
→ W razie restartu odtwarza z AOF
```

---

### **2. Redis Volume (Trwały Storage)** ✅ Skonfigurowany

**Volume:**
```yaml
volumes:
  redis_data:  # ← Docker volume (trwały na dysku hosta)
```

**Gdzie są dane:**
- Docker volume: `moviemind-api-public_redis_data`
- Lokalizacja: `/var/lib/docker/volumes/...` (na hoście)
- **Przetrwa restart kontenera** ✅

**Co się dzieje przy restart:**
```
1. Kontener Redis restartuje
2. Redis ładuje dane z /data/appendonly.aof
3. Wszystkie joby są przywrócone ✅
```

---

### **3. Redis RDB Snapshots** ✅ Włączone

**Konfiguracja:**
```bash
save 3600 1 300 100 60 10000
```

**Co to oznacza:**
- Snapshots są tworzone automatycznie:
  - Co 3600 sekund (1h) jeśli >= 1 zmiana
  - Co 300 sekund (5min) jeśli >= 100 zmian
  - Co 60 sekund jeśli >= 10000 zmian

**Backup:**
- Oprócz AOF, Redis tworzy snapshots (`dump.rdb`)
- **Podwójna ochrona** danych ✅

---

## 🔄 Co się stanie w różnych scenariuszach?

### **Scenariusz 1: Restart kontenera Redis**

```
1. Kontener restartuje
2. Redis ładuje dane z appendonly.aof
3. Wszystkie pending joby są przywrócone ✅
4. Horizon kontynuuje przetwarzanie
```

**Efekt:** ✅ **Bezpieczne** - joby nie są tracone

---

### **Scenariusz 2: Crash Redis (process crash)**

```
1. Redis proces crashuje
2. Docker restartuje kontener
3. Redis ładuje z AOF (ostatnie operacje przed crash)
4. Możliwa minimalna strata (kilka ostatnich sekund)
```

**Efekt:** ⚠️ **Prawie bezpieczne** - możliwa strata ostatnich sekund

---

### **Scenariusz 3: Awaria dysku/volume**

```
1. Volume zostaje usunięty lub uszkodzony
2. Redis traci wszystkie dane
3. Pending joby są utracone ❌
```

**Efekt:** ❌ **Ryzykowne** - ale to skrajny przypadek

**Ochrona:**
- ✅ Backup volume
- ✅ Monitoring
- ✅ Failed jobs w PostgreSQL (backup)

---

### **Scenariusz 4: Restart całego Dockera**

```
1. Docker restartuje
2. Redis kontener uruchamia się
3. Redis ładuje dane z AOF/volume
4. Wszystkie joby przywrócone ✅
```

**Efekt:** ✅ **Bezpieczne**

---

## 🛡️ Dodatkowe mechanizmy bezpieczeństwa

### **1. Failed Jobs w PostgreSQL** ✅

**Konfiguracja:** `api/config/queue.php`

```php
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'sqlite'),
    'table' => 'failed_jobs',
],
```

**Co to daje:**
- ❌ Failed joby są zapisywane w PostgreSQL
- ✅ **Backup** dla failed jobów
- ✅ Można je retry'ować później

---

### **2. Job Retry Mechanism** ✅

**Konfiguracja w Jobach:**

```php
public int $tries = 3;  // 3 próby przed failed
```

**Co to daje:**
- ✅ Job retry'uje automatycznie (3 razy)
- ✅ Tylko po 3 nieudanych próbach → `failed_jobs`
- ✅ Większość problemów jest automatycznie rozwiązana

---

### **3. Cache dla Job Status** ⚠️ (Temporary)

**Lokalizacja:** `api/app/Jobs/*GenerateMovieJob.php`

```php
Cache::put($this->cacheKey(), [
    'job_id' => $this->jobId,
    'status' => 'DONE',
    // ...
], now()->addMinutes(15));  // ← Cache (temporary)
```

**Co to oznacza:**
- ⚠️ Cache może zniknąć (15 min TTL)
- ✅ Ale dane są w PostgreSQL (movies, people)
- ✅ Cache jest tylko dla statusu, nie dla danych

---

### **4. Database Queue jako Backup** ✅

**Alternatywa:**

Laravel wspiera `database` queue driver:

```php
QUEUE_CONNECTION=database  // Joby w PostgreSQL (nie Redis)
```

**Zalety:**
- ✅ **100% trwałe** (PostgreSQL)
- ✅ Backup automatyczny
- ⚠️ Wolniejsze niż Redis

**Użycie:**
- Production: `redis` (szybkie + AOF)
- Backup option: `database` (wolniejsze, ale pewniejsze)

---

## 📊 Porównanie Persistence

| Mechanizm | Trwałość | Szybkość | Backup |
|-----------|----------|----------|--------|
| **Redis AOF** | ✅ Tak | ✅ Szybkie | ✅ Tak |
| **Redis Volume** | ✅ Tak | ✅ Szybkie | ✅ Tak |
| **PostgreSQL** | ✅ Tak | ⚠️ Wolniejsze | ✅ Tak |
| **Cache (Redis)** | ⚠️ TTL | ✅ Szybkie | ❌ Nie |

---

## ✅ Zalecenia dla Production

### **1. Redis Persistence (Obecna konfiguracja)** ✅

**Włączone:**
- ✅ AOF (`--appendonly yes`)
- ✅ RDB snapshots (`save` config)
- ✅ Docker volume (`redis_data`)

**Status:** ✅ **Bezpieczne**

---

### **2. Dodatkowe zabezpieczenia (Opcjonalne)**

**A. Backup Redis:**

```bash
# Backup AOF
docker-compose exec redis redis-cli BGSAVE
docker cp moviemind-redis:/data/appendonly.aof ./backups/

# Backup volume
docker run --rm -v moviemind-api-public_redis_data:/data -v $(pwd)/backups:/backup alpine tar czf /backup/redis-backup.tar.gz /data
```

**B. Monitorowanie:**

```bash
# Sprawdź czy AOF działa
docker-compose exec redis redis-cli CONFIG GET appendonly

# Sprawdź rozmiar AOF
docker-compose exec redis ls -lh /data/
```

**C. Replication (Production):**

```yaml
# Redis Sentinel lub Cluster dla HA
# Więcej: https://redis.io/topics/sentinel
```

---

## 🎯 Podsumowanie

### **✅ Redis JEST trwałą pamięcią:**

1. ✅ **AOF włączony** - wszystkie operacje są logowane
2. ✅ **Volume trwały** - dane na dysku hosta
3. ✅ **RDB snapshots** - automatyczne backup'y
4. ✅ **Failed jobs** w PostgreSQL - backup dla failed
5. ✅ **Retry mechanism** - automatyczne ponowienia

### **⚠️ Ryzyka:**

1. ⚠️ **Awaria dysku** - utrata wszystkich danych (skrajny przypadek)
2. ⚠️ **Crash Redis** - możliwa strata ostatnich sekund
3. ⚠️ **Cache TTL** - status jobów może zniknąć (ale dane w DB)

### **✅ Obecna konfiguracja:**

**Status:** ✅ **Bezpieczna dla developmentu**  
**Production:** ✅ **Wystarczająca** (można dodać backup'i)

---

## 📝 Co zrobić w Production?

### **Minimum (Obecne):**
- ✅ Redis AOF (`--appendonly yes`)
- ✅ Docker volume
- ✅ Failed jobs w PostgreSQL

### **Rekomendowane:**
- ✅ **Regularne backup'y** Redis volume
- ✅ **Monitoring** Redis health
- ✅ **Alerts** dla Redis crashes
- ✅ **Redis Replication** (dla HA)

---

**Ostatnia aktualizacja:** 2025-11-01

