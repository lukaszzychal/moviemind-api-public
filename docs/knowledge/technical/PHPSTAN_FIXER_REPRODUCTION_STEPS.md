# Steps to Reproduce: Laravel package:discover Error with phpstan-fixer

> **Creation Date:** 2025-12-14  
> **Context:** Instructions to reproduce the `Call to a member function make() on null` error  
> **Category:** technical

## 🎯 Prerequisites

- PHP 8.2+ or 8.3+
- Composer
- Laravel 12.x
- `lukaszzychal/phpstan-fixer` v1.2.2+

## 📋 Steps to Reproduce

### Step 1: Create a new Laravel project

```bash
composer create-project laravel/laravel:^12.0 test-phpstan-fixer
cd test-phpstan-fixer
```

### Step 2: Install phpstan-fixer

```bash
composer require --dev lukaszzychal/phpstan-fixer:^1.2
```

### Step 3: Add phpstan-fixer to dont-discover (if not already set)

Edit `composer.json`:

```json
{
  "extra": {
    "laravel": {
      "dont-discover": [
        "lukaszzychal/phpstan-fixer"
      ]
    }
  }
}
```

### Step 4: Run composer update

```bash
composer update
```

**Expected Error:**
```
Error: Call to a member function make() on null
at vendor/laravel/framework/src/Illuminate/Console/Command.php:175
```

### Step 5: Try to run package:discover manually

```bash
php artisan package:discover
```

**Expected Error:**
```
Error: Call to a member function make() on null
at vendor/laravel/framework/src/Illuminate/Console/Command.php:175
```

### Step 6: Run Feature Tests

Create a simple Feature test:

```bash
php artisan make:test ExampleTest
```

Edit `tests/Feature/ExampleTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
```

Run the test:

```bash
php artisan test
```

**Expected Error:**
```
Error: Call to a member function make() on null
at vendor/laravel/framework/src/Illuminate/Console/Command.php:175
```

## 🔍 Additional Information

### Environment

- **PHP Version:** 8.2.29 or 8.3.28
- **Laravel Version:** 12.36.1
- **phpstan-fixer Version:** 1.2.2
- **OS:** macOS, Linux, or Windows

### Error Details

**Stack Trace:**
```
Command::run() (vendor/laravel/framework/src/Illuminate/Console/Command.php:175)
  → $this->laravel->make(Factory::class, ['output' => $this->output])
  → $this->laravel is null
```

**Root Cause:**
- `PackageDiscoverCommand` extends `Command`
- `Command::run()` requires Laravel container (`$this->laravel->make()`)
- During Laravel initialization, container may not be ready
- `PackageDiscoverCommand` is registered via `#[AsCommand]` attribute
- When Laravel tries to load it via `ContainerCommandLoader`, container is null

### Workarounds

1. **Build manifest directly (for composer install/update):**
   - Use `scripts/build-package-manifest.php` to build manifest without container
   - See: `docs/knowledge/technical/PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md`

2. **Remove phpstan-fixer from require-dev (temporary):**
   ```bash
   composer remove lukaszzychal/phpstan-fixer --dev
   ```

## 📊 Verification

To verify the issue is present:

1. Check if `bootstrap/cache/packages.php` exists
2. If it doesn't exist, Laravel will try to build it during initialization
3. This triggers `PackageDiscoverCommand`, which requires container
4. Container is not ready → error occurs

## 🔗 Related Issues

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [Issue #63](https://github.com/lukaszzychal/phpstan-fixer/issues/63) - dont-discover should be array

---

**Last updated:** 2025-12-14

