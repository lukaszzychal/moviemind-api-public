# 🔄 Restart kontenerów po naprawie duplikatów

## Problem

Po zmianach w kodzie, kontenery Docker muszą zostać zrestartowane, aby nowy kod został załadowany.

## Rozwiązanie

### Opcja 1: Restart kontenerów (szybkie)

```bash
# Restart tylko kontenerów PHP i Horizon (gdzie działają joby)
docker compose restart php horizon

# Poczekaj kilka sekund na restart
sleep 5

# Sprawdź czy działają
docker compose ps
```

### Opcja 2: Pełny restart (zalecane)

```bash
# Zatrzymaj wszystkie kontenery
docker compose down

# Uruchom ponownie
docker compose up -d

# Sprawdź logi
docker compose logs -f php horizon
```

### Opcja 3: Rebuild kontenerów (jeśli problemy)

```bash
# Zatrzymaj i usuń kontenery
docker compose down

# Rebuild i uruchom
docker compose up -d --build

# Sprawdź logi
docker compose logs -f php horizon
```

## Weryfikacja

Po restarcie sprawdź czy zmiany działają:

```bash
# 1. Wyczyść bazę (opcjonalnie)
docker compose exec php php artisan migrate:fresh

# 2. Wyczyść cache
docker compose exec php php artisan cache:clear
docker compose exec php php artisan config:clear

# 3. Test - pierwszy request
curl http://localhost:8000/api/v1/movies/the-matrix

# 4. Poczekaj na zakończenie joba (sprawdź logi)
docker compose logs -f horizon

# 5. Test - drugi request (powinien zwrócić ten sam film)
curl http://localhost:8000/api/v1/movies/the-matrix

# 6. Sprawdź czy jest tylko jeden film
curl http://localhost:8000/api/v1/movies?q=matrix
```

## Dlaczego restart jest potrzebny?

1. **OPcache** - PHP cache'uje skompilowany kod
2. **Autoloader** - Laravel ładuje klasy przy starcie
3. **Kontenery** - kod jest kopiowany do kontenera przy build

## Sprawdzenie czy nowy kod jest załadowany

```bash
# Sprawdź czy kontener używa nowego kodu
docker compose exec php php artisan tinker
>>> \App\Repositories\MovieRepository::class
>>> $repo = app(\App\Repositories\MovieRepository::class);
>>> $repo->findBySlugForJob('the-matrix');
```

## Troubleshooting

### Problem: Zmiany nie działają po restarcie

```bash
# 1. Sprawdź czy pliki są w kontenerze
docker compose exec php ls -la app/Repositories/MovieRepository.php

# 2. Sprawdź zawartość pliku
docker compose exec php cat app/Repositories/MovieRepository.php | grep -A 10 "findBySlugForJob"

# 3. Jeśli plik jest stary, zrób rebuild
docker compose down
docker compose up -d --build
```

### Problem: Cache przechowuje stare wyniki

```bash
# Wyczyść wszystkie cache
docker compose exec php php artisan cache:clear
docker compose exec php php artisan config:clear
docker compose exec php php artisan route:clear
docker compose exec php php artisan view:clear

# Wyczyść cache Redis
docker compose exec redis redis-cli FLUSHALL
```

### Problem: Horizon nie restartuje się

```bash
# Zatrzymaj Horizon
docker compose stop horizon

# Uruchom ponownie
docker compose up -d horizon

# Sprawdź logi
docker compose logs -f horizon
```

