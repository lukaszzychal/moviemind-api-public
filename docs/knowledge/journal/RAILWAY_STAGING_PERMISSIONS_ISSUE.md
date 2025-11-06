# Problem z Uprawnieniami Storage na Railway Staging

> **Data utworzenia:** 2025-11-06  
> **Kontekst:** Błąd 500 na endpoint `/up` w staging environment Railway  
> **Kategoria:** journal  
> **Status:** 🔄 W trakcie rozwiązania

## 🎯 Problem

Endpoint `/up` zwraca błąd 500 z komunikatem:
```
file_put_contents(/var/www/html/storage/framework/views/...): 
Failed to open stream: Permission denied
```

## 🔍 Analiza

### Obserwacje:
- ✅ Endpoint `/api/v1/movies` działa poprawnie (200 OK)
- ❌ Endpoint `/up` zwraca 500 (Permission denied)
- ❌ Problem dotyczy katalogu `storage/framework/views/`

### Przyczyna:
Katalog `storage/framework/views/` nie ma odpowiednich uprawnień do zapisu przez użytkownika `app` (non-root user w kontenerze).

## ✅ Rozwiązanie

### 1. Dodano tworzenie katalogów w entrypoint.sh

Dodano sekcję w `docker/php/entrypoint.sh` przed cache'owaniem konfiguracji:

```bash
# Ensure storage directories exist and have correct permissions
echo "📁 Ensuring storage directories exist..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
mkdir -p bootstrap/cache
chown -R app:app storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
echo "✅ Storage directories ready"
```

### 2. Weryfikacja w Dockerfile

Dockerfile już zawiera:
- Tworzenie katalogów storage w build time
- Ustawianie uprawnień `chmod -R 775 storage`
- Ustawianie właściciela `chown -R app:app`

### 3. Weryfikacja w start.sh

`start.sh` również tworzy katalogi i ustawia uprawnienia przed uruchomieniem Supervisor.

## 🔄 Workflow Naprawy

1. **Build time (Dockerfile):**
   - Tworzy katalogi storage
   - Ustawia uprawnienia 775
   - Ustawia właściciela app:app

2. **Runtime (entrypoint.sh):**
   - **NOWE:** Tworzy katalogi jeśli nie istnieją
   - **NOWE:** Ustawia uprawnienia przed cache'owaniem
   - Uruchamia migracje
   - Cache'uje konfigurację

3. **Runtime (start.sh):**
   - Tworzy katalogi jeśli nie istnieją
   - Ustawia uprawnienia
   - Uruchamia Supervisor

## 📋 Testowanie

Po wdrożeniu poprawki:

```bash
# Test healthcheck
curl https://peaceful-education-staging.up.railway.app/up
# Oczekiwany wynik: {"status":"ok"} lub podobny (200 OK)

# Test API endpoint
curl https://peaceful-education-staging.up.railway.app/api/v1/movies
# Oczekiwany wynik: {"data":[]} (200 OK)
```

## 🔗 Powiązane Dokumenty

- [Deployment Setup](../reference/DEPLOYMENT_SETUP.md) - Dokumentacja deploymentu
- [Docker Optimization](../reference/DOCKER_OPTIMIZATION.md) - Optymalizacje Docker
- [Entrypoint Script](../../../docker/php/entrypoint.sh) - Skrypt entrypoint

## 📌 Notatki

- Problem występuje tylko na staging (Railway)
- Lokalnie działa poprawnie (prawdopodobnie inny user/permissions)
- Rozwiązanie: Dodanie tworzenia katalogów w entrypoint.sh przed cache'owaniem

---

**Ostatnia aktualizacja:** 2025-11-06

