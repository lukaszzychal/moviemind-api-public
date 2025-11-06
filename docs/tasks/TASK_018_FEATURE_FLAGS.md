# TASK-018: Włączanie Feature Flags dla Real AI

## Problem
Próba użycia `php artisan pennant:feature --on` nie działa, ponieważ Laravel Pennant nie ma takiej opcji.

## Rozwiązanie

### Metoda 1: Użycie Tinker (Zalecane)

```bash
cd api
php artisan tinker
```

W tinkerze:
```php
Feature::activate('ai_description_generation');
Feature::activate('ai_bio_generation');
```

Lub w jednej linii:
```bash
php artisan tinker --execute="Feature::activate('ai_description_generation'); Feature::activate('ai_bio_generation');"
```

### Metoda 2: Użycie API Endpoint

```bash
# Włącz ai_description_generation
curl -X POST http://localhost:8000/api/v1/admin/flags/ai_description_generation \
  -H "Content-Type: application/json" \
  -d '{"state": "on"}'

# Włącz ai_bio_generation
curl -X POST http://localhost:8000/api/v1/admin/flags/ai_bio_generation \
  -H "Content-Type: application/json" \
  -d '{"state": "on"}'
```

### Metoda 3: Sprawdzenie statusu flag

```bash
# Przez API
curl http://localhost:8000/api/v1/admin/flags

# Przez tinker
php artisan tinker --execute="Feature::active('ai_description_generation') ? 'ON' : 'OFF'"
```

## Sprawdzenie czy flagi działają

```bash
# Lista wszystkich flag
curl http://localhost:8000/api/v1/admin/flags

# Przykładowa odpowiedź:
# {
#   "data": [
#     {
#       "name": "ai_description_generation",
#       "active": true,
#       "description": "Enables AI-generated movie/series descriptions."
#     },
#     ...
#   ]
# }
```

## Uwagi

1. Flagi są przechowywane w bazie danych (tabela `features`)
2. Po `migrate:fresh` flagi są resetowane - trzeba je włączyć ponownie
3. Flagi można włączać/wyłączać przez API lub tinker

