# Sekcja Troubleshooting dla README.md phpstan-fixer

> **Creation Date:** 2025-12-14  
> **Context:** Gotowa sekcja Troubleshooting do dodania do README.md w repozytorium phpstan-fixer  
> **Category:** technical

## 📝 Instrukcje

Ta sekcja powinna być dodana do README.md w repozytorium `lukaszzychal/phpstan-fixer` **przed** sekcją "Development".

## 📋 Zawartość sekcji

```markdown
## Troubleshooting

### Laravel package:discover Error

**Problem:** Error `Call to a member function make() on null` occurs during `package:discover` in Laravel.

**Cause:** This happens when Laravel tries to run `package:discover` before the container is fully initialized. The issue is not related to `dont-discover` configuration (which is correctly set in v1.2.2+), but rather to how Laravel's `PackageDiscoverCommand` initializes the container.

**Workaround:**

Use a direct manifest builder that doesn't require the Laravel container. Create `scripts/build-package-manifest.php`:

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$basePath = __DIR__ . '/..';
$vendorPath = $basePath . '/vendor';
$manifestPath = $basePath . '/bootstrap/cache/packages.php';

// Ensure bootstrap/cache directory exists
$cacheDir = dirname($manifestPath);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Load Composer's installed.json
$installedJsonPath = $vendorPath . '/composer/installed.json';
if (!file_exists($installedJsonPath)) {
    file_put_contents($manifestPath, "<?php return [];\n");
    exit(0);
}

$installed = json_decode(file_get_contents($installedJsonPath), true);
$packages = $installed['packages'] ?? $installed;

// Get packages to ignore from composer.json
$composerJsonPath = $basePath . '/composer.json';
$ignore = [];
if (file_exists($composerJsonPath)) {
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    $ignore = $composerJson['extra']['laravel']['dont-discover'] ?? [];
}

$ignoreAll = in_array('*', $ignore);

// Build manifest
$manifest = [];
foreach ($packages as $package) {
    $packageName = $package['name'];
    $configuration = $package['extra']['laravel'] ?? [];
    
    if (isset($configuration['dont-discover'])) {
        $packageDontDiscover = $configuration['dont-discover'];
        if (is_array($packageDontDiscover)) {
            $ignore = array_merge($ignore, $packageDontDiscover);
        }
    }
    
    if ($ignoreAll || in_array($packageName, $ignore, true)) {
        continue;
    }
    
    if (!empty($configuration)) {
        $manifest[$packageName] = $configuration;
    }
}

// Write manifest
$manifestContent = "<?php return " . var_export($manifest, true) . ";\n";
file_put_contents($manifestPath, $manifestContent);
```

Then update your `composer.json`:

```json
{
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php scripts/build-package-manifest.php"
    ]
  }
}
```

**Long-term Solution:** This issue has been reported to Laravel framework. See [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) for details and proposed solutions.
```

## 🔧 Jak dodać

### Opcja 1: Pull Request (Rekomendowane)

1. Sforkuj repozytorium `phpstan-fixer`
2. Dodaj sekcję "Troubleshooting" przed sekcją "Development" w README.md
3. Stwórz PR z opisem: "docs: add Laravel package:discover troubleshooting section"

### Opcja 2: Bezpośrednia edycja (jeśli masz dostęp)

1. Edytuj README.md w repozytorium
2. Dodaj sekcję "Troubleshooting" przed sekcją "Development"
3. Zatwierdź zmiany

### Opcja 3: Issue z propozycją

1. Zgłoś issue w repozytorium z propozycją dokumentacji
2. Dołącz gotową zawartość sekcji
3. Poproś maintainera o dodanie

## 🔗 Related Documents

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [PHPStan Fixer Library Solution Proposal](./PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md) - Propozycje dla biblioteki
- [PHPStan Fixer Documentation Proposal](./PHPSTAN_FIXER_DOCUMENTATION_PROPOSAL.md) - Szczegółowa propozycja

---

**Last updated:** 2025-12-14

