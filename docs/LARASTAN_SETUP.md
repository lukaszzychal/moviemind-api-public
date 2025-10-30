# Instalacja Larastan (PHPStan dla Laravel)

## Wymagania

- PHP 8.2 lub wyższa
- Laravel 11+ lub 12+
- Composer

## Kroki instalacji

### 1. Zainstaluj PHPStan i Larastan

```bash
cd api  # lub twój katalog Laravel

composer require --dev phpstan/phpstan larastan/larastan phpstan/extension-installer
```

### 2. Zezwól na plugin extension-installer (jeśli wymagane)

```bash
composer config allow-plugins.phpstan/extension-installer true
```

### 3. Utwórz plik konfiguracyjny `phpstan.neon`

W katalogu głównym projektu (np. `api/phpstan.neon`):

```neon
parameters:
    level: 5
    paths:
        - app
        - database/seeders
    excludePaths:
        - vendor
        - storage
        - bootstrap/cache
    checkUninitializedProperties: false
```

### 4. Uruchom PHPStan

```bash
vendor/bin/phpstan analyse --memory-limit=2G
```

## Poziomy PHPStan

| Level | Opis | Użycie |
|-------|------|--------|
| 0 | Podstawowe sprawdzenia | Start dla każdego |
| 1-3 | Łagodne sprawdzenia | Większość kodu przechodzi |
| **5** | **Średnie sprawdzenia** | **✅ Zalecane** |
| 7 | Bardzo surowe | Enterprise |
| 9 | Maksymalna surowość | Rzadko osiągalne |

## Konfiguracja w CI/CD

### GitHub Actions

Dodaj do `.github/workflows/ci.yml`:

```yaml
- name: PHPStan static analysis
  working-directory: api
  run: |
    if [ -f vendor/bin/phpstan ]; then 
      vendor/bin/phpstan analyse --memory-limit=2G
    else 
      echo "PHPStan not installed"
    fi
```

## Rozwiązywanie problemów

### Problem: "extension.neon is missing"

**Rozwiązanie**: Zainstaluj `phpstan/extension-installer` - automatycznie ładuje rozszerzenia Larastan.

```bash
composer require --dev phpstan/extension-installer
```

### Problem: Zbyt dużo błędów na start

**Rozwiązanie**: Zacznij od niższego poziomu i stopniowo zwiększaj:

```neon
parameters:
    level: 3  # Zamiast 5
```

### Problem: Brak pamięci

**Rozwiązanie**: Zwiększ limit pamięci:

```bash
vendor/bin/phpstan analyse --memory-limit=4G
```

## Adnotacje PHPStan

Gdy trzeba zignorować konkretny błąd:

```php
// Pojedyncza linia
return $m[1] ?? null; // @phpstan-ignore-line

// Cały plik (na górze)
// @phpstan-ignore-file

// Konkretny błąd
// @phpstan-ignore-next-line no-return-type
```

## Co sprawdza Larastan?

Larastan rozszerza PHPStan o:

- ✅ Rozumienie Eloquent ORM
- ✅ Facades Laravel
- ✅ Service Container i dependency injection
- ✅ Collections Laravel
- ✅ Request validation
- ✅ Routes i route parameters
- ✅ Form requests

## Przykład użycia

```bash
# Pełna analiza
vendor/bin/phpstan analyse

# Z limitem pamięci
vendor/bin/phpstan analyse --memory-limit=2G

# Analiza konkretnego katalogu
vendor/bin/phpstan analyse app/Http/Controllers

# Wygeneruj baseline (ignoruj istniejące błędy)
vendor/bin/phpstan analyse --generate-baseline
```

## Integracja z IDE

### PhpStorm

1. Settings → Languages & Frameworks → PHP → Quality Tools → PHPStan
2. Ustaw ścieżkę do: `vendor/bin/phpstan`
3. Konfiguracja: `phpstan.neon`
4. Włącz automatyczne sprawdzanie

### VS Code

Zainstaluj rozszerzenie "PHPStan" - automatycznie wykrywa konfigurację.

## Przydatne linki

- [Larastan GitHub](https://github.com/larastan/larastan)
- [PHPStan Documentation](https://phpstan.org/)
- [PHPStan Levels](https://phpstan.org/user-guide/rule-levels)

## Status w tym projekcie

✅ **Larastan jest zainstalowany i skonfigurowany**
- Wersja: 3.7.2
- Poziom: 5
- Status: 0 błędów
- CI: Aktywny w GitHub Actions

