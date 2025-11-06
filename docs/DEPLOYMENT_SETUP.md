# Deployment Setup - Production Entrypoint

## 🚀 Overview

Production entrypoint script (`docker/php/entrypoint.sh`) automatycznie wykonuje wymagane akcje setupowe przed uruchomieniem aplikacji, zapewniając bezpieczeństwo danych w bazie danych.

## ✅ Wykonywane Akcje

### 1. **Oczekiwanie na połączenie z bazą danych**
- Czeka maksymalnie 30 sekund na dostępność bazy danych
- Sprawdza połączenie używając PDO
- Jeśli baza nie jest dostępna, kontener kończy działanie z błędem

### 2. **Weryfikacja APP_KEY**
- Sprawdza czy `APP_KEY` jest ustawiony
- Jeśli nie, generuje nowy klucz (tylko jeśli nie istnieje)
- Wymagane dla bezpieczeństwa Laravel

### 3. **Cache'owanie konfiguracji (tylko production)**
- `config:cache` - cache konfiguracji
- `route:cache` - cache routingu
- `view:cache` - cache widoków
- **Pomijane** dla `APP_ENV=local` lub `APP_ENV=dev`

### 4. **Migracje bazy danych (bezpieczne)**
- Uruchamia `php artisan migrate --force`
- **Bezpieczne dla production**: uruchamia tylko pending migrations
- **Nie nadpisuje danych**: nie używa `migrate:fresh` ani `migrate:reset`
- Można wyłączyć ustawiając `RUN_MIGRATIONS=false`
- Jeśli migracje już są aktualne, pokazuje status

### 5. **Optymalizacja aplikacji (tylko production)**
- `php artisan optimize` - optymalizuje autoloader i cache
- **Pomijane** dla `APP_ENV=local` lub `APP_ENV=dev`

## 🔒 Bezpieczeństwo Danych

### Migracje są bezpieczne, ponieważ:
1. ✅ Używają tylko `migrate` (nie `migrate:fresh`)
2. ✅ Laravel automatycznie wykrywa pending migrations
3. ✅ Nie modyfikują istniejących danych
4. ✅ Można wyłączyć przez `RUN_MIGRATIONS=false`

### Przykład bezpiecznej migracji:
```php
// Ta migracja jest bezpieczna - tylko dodaje kolumnę
Schema::table('movies', function (Blueprint $table) {
    $table->string('new_column')->nullable();
});
```

### Przykład NIEBEZPIECZNEJ migracji (nie używaj w production):
```php
// ❌ NIEBEZPIECZNE - usuwa wszystkie dane!
Schema::dropIfExists('movies');
```

## 🔧 Zmienne Środowiskowe

| Zmienna | Domyślna | Opis |
|---------|----------|------|
| `APP_ENV` | `production` | Środowisko aplikacji |
| `APP_DEBUG` | `0` | Tryb debugowania |
| `RUN_MIGRATIONS` | `true` | Czy uruchomić migracje |
| `DB_HOST` | - | Host bazy danych |
| `DB_PORT` | - | Port bazy danych |
| `DB_DATABASE` | - | Nazwa bazy danych |
| `DB_USERNAME` | - | Użytkownik bazy danych |
| `DB_PASSWORD` | - | Hasło bazy danych |
| `APP_KEY` | - | Klucz aplikacji Laravel |

## 📋 Przykłady Użycia

### Standardowe uruchomienie (z migracjami):
```bash
docker run -e APP_ENV=production \
  -e DB_HOST=db \
  -e DB_DATABASE=moviemind \
  moviemind-api:latest
```

### Bez migracji (jeśli uruchamiasz je ręcznie):
```bash
docker run -e RUN_MIGRATIONS=false \
  moviemind-api:latest
```

### Development (bez cache):
```bash
docker run -e APP_ENV=local \
  -e APP_DEBUG=1 \
  moviemind-api:latest
```

## 🐛 Debugowanie

### Sprawdzenie logów entrypoint:
```bash
docker logs <container_name> | grep "Entrypoint"
```

### Sprawdzenie statusu migracji:
```bash
docker exec <container_name> php artisan migrate:status
```

### Ręczne uruchomienie migracji:
```bash
docker exec <container_name> php artisan migrate --force
```

## ⚠️ Ważne Uwagi

1. **Migracje są uruchamiane automatycznie** przy starcie kontenera
2. **Dane nie są tracone** - używane są tylko pending migrations
3. **Cache jest tworzony tylko dla production** - local/dev używa live reload
4. **Baza danych musi być dostępna** - kontener czeka maksymalnie 30 sekund

## 🔄 Workflow

```
1. Kontener startuje
   ↓
2. start.sh konfiguruje Nginx
   ↓
3. entrypoint.sh wykonuje setup:
   - Czeka na bazę danych
   - Sprawdza APP_KEY
   - Cache'uje konfigurację (production)
   - Uruchamia migracje (bezpieczne)
   - Optymalizuje aplikację (production)
   ↓
4. Uruchamia Supervisor (PHP-FPM + Nginx)
```

## 📚 Zobacz też

- [Docker Optimization](./DOCKER_OPTIMIZATION.md) - optymalizacje Dockerfile
- [README](../README.md) - główna dokumentacja projektu

