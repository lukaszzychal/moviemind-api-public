---
name: php-pre-commit
description: Przypomina o uruchomieniu narzędzi jakości przed commitem w projekcie Laravel/PHP. Use when the user is about to commit PHP changes or asks about pre-commit checks.
---
# PHP Pre-Commit Checklist

Przed commitem uruchom:

1. **Laravel Pint** – formatowanie
   ```bash
   docker compose exec php vendor/bin/pint
   ```

2. **PHPStan** – analiza statyczna
   ```bash
   docker compose exec php vendor/bin/phpstan analyse --memory-limit=2G
   ```

3. **PHPUnit** – testy
   ```bash
   docker compose exec php php artisan test
   ```

4. **GitLeaks** – wykrywanie sekretów
   ```bash
   gitleaks protect --source . --verbose --no-banner
   ```

5. **Composer Audit** – luki bezpieczeństwa
   ```bash
   docker compose exec php composer audit
   ```

Bezpośrednio w katalogu `api/` można użyć `vendor/bin/pint`, `php artisan test` itd. bez prefiksu `docker compose exec php`.
