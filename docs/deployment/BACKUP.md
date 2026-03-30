# Backup and Restore

> **For:** DevOps, SRE  
> **Related:** [DEPLOYMENT.md](../technical/DEPLOYMENT.md#backup--recovery)

---

## 1. Database (PostgreSQL)

### Manual backup

**Local / default compose:**
```bash
docker compose exec db pg_dump -U moviemind moviemind > backup-$(date +%Y%m%d-%H%M).sql
```

**Staging:**
```bash
docker compose -f compose.staging.yml exec db pg_dump -U moviemind moviemind_staging > backup-staging-$(date +%Y%m%d-%H%M).sql
```

**Production (service name may vary if using external DB):**
```bash
docker compose -f compose.production.yml exec db pg_dump -U $DB_USERNAME $DB_DATABASE > backup-prod-$(date +%Y%m%d-%H%M).sql
```

### Restore

```bash
# Stop app (optional, to avoid writes during restore)
docker compose -f compose.production.yml stop php

# Restore
docker compose -f compose.production.yml exec -T db psql -U $DB_USERNAME -d $DB_DATABASE < backup-prod-YYYYMMDD-HHMM.sql

# Start app
docker compose -f compose.production.yml start php
```

### Recommendations

- Schedule daily backups (cron or CI).
- Retain at least 7–30 days; keep off-host or in object storage.
- Periodically test restore on a non-production copy (RTO/RPO: see [DEPLOYMENT.md](../technical/DEPLOYMENT.md)).

### Automatyczne backupy (cron)

Skrypty w katalogu `scripts/` pozwalają zautomatyzować backup i test odzysku.

**Wymagania:** `docker compose`, działający stack (staging lub production). Zmienne `DB_USERNAME` i `DB_DATABASE` muszą być ustawione w `api/.env` lub w środowisku (np. w crontab).

#### Skrypt 1: Backup bazy — `scripts/backup-db.sh`

Tworzy kopię bazy (pg_dump), kompresuje ją (gzip) i usuwa backupy starsze niż podana liczba dni.

**Wywołanie:**
```bash
./scripts/backup-db.sh <compose-file> <katalog-backupów> [retencja-dni]
```

**Staging** (np. codziennie o 2:00, backupy w `./backups-staging`, retencja 14 dni):
```bash
# Upewnij się, że stack działa: docker compose -f compose.staging.yml up -d
cd /ścieżka/do/moviemind-api-public
chmod +x scripts/backup-db.sh
./scripts/backup-db.sh compose.staging.yml ./backups-staging 14
```

**Production** (np. codziennie o 2:00, backupy w `/var/backups/moviemind`, retencja 30 dni):
```bash
cd /ścieżka/do/moviemind-api-public
./scripts/backup-db.sh compose.production.yml /var/backups/moviemind 30
```

**Cron — staging:**
```cron
0 2 * * * cd /ścieżka/do/moviemind-api-public && ./scripts/backup-db.sh compose.staging.yml /var/backups/moviemind-staging 14
```

**Cron — production:**
```cron
0 2 * * * cd /ścieżka/do/moviemind-api-public && ./scripts/backup-db.sh compose.production.yml /var/backups/moviemind 30
```

Katalog na backupy zostanie utworzony, jeśli nie istnieje. Pliki mają postać `backup-YYYYMMDD-HHMMSS.sql.gz`.

#### Skrypt 2: Test odzysku — `scripts/verify-restore-db.sh`

Przywraca ostatnią (lub wskazaną) kopię do tymczasowej bazy `moviemind_restore_verify`, weryfikuje ją (liczba tabel, zapytanie do `movies`) i usuwa tę bazę.

**Wywołanie:**
```bash
./scripts/verify-restore-db.sh <compose-file> <katalog-backupów> [ścieżka-do-pliku-backupu]
```

**Staging** (test na ostatniej kopii z `./backups-staging`):
```bash
cd /ścieżka/do/moviemind-api-public
chmod +x scripts/verify-restore-db.sh
./scripts/verify-restore-db.sh compose.staging.yml ./backups-staging
```

**Production** (np. raz w tygodniu, niedziela 4:00):
```bash
./scripts/verify-restore-db.sh compose.production.yml /var/backups/moviemind
```

**Cron — test odzysku production (raz w tygodniu):**
```cron
0 4 * * 0 cd /ścieżka/do/moviemind-api-public && ./scripts/verify-restore-db.sh compose.production.yml /var/backups/moviemind
```

#### Uwagi

- Przed ustawieniem crona uruchom ręcznie oba skrypty z odpowiedniego katalogu projektu i upewnij się, że stack (staging/production) działa.
- W crontab katalog roboczy musi być ustawiony na główny katalog repo (jak w przykładach powyżej przez `cd ... &&`).
- W przypadku **zewnętrznej bazy** (RDS, managed PostgreSQL) backup/restore często robi dostawca chmury; wtedy użyj jego harmonogramu, a skrypt weryfikacji możesz zaadaptować (np. łączenie się do bazy weryfikacyjnej po restore wykonanym innym kanałem).

---

## 2. Redis (cache/sessions)

- Redis is used for cache, sessions, and queues. Data is **not** critical for long-term recovery; it can be rebuilt.
- Enable **AOF** or **RDB** in Redis if you need to reduce loss on restart (already used in compose: `--appendonly yes`).
- No application-level backup script required for Redis for disaster recovery.

---

## 3. Application storage (optional)

- `api/storage/logs` – optional to back up; usually short retention.
- `api/storage/app` – any user-uploaded or generated files; back up if you store important assets there.
- For containerized deploy, ensure persistent volumes for `storage` if needed; document restore from volume snapshot if used.
