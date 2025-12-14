# Propozycja dokumentacji dla biblioteki phpstan-fixer

> **Creation Date:** 2025-12-14  
> **Context:** Propozycja dodania dokumentacji z workaround do repozytorium phpstan-fixer  
> **Category:** technical

## 🎯 Cel

Dodać dokumentację w repozytorium `lukaszzychal/phpstan-fixer` opisującą problem z `package:discover` i proponowane workaround.

## 📝 Co oznacza "dodać dokumentację w bibliotece"

**"Dodać dokumentację w bibliotece"** oznacza:
- Dodać sekcję w README.md repozytorium `phpstan-fixer` opisującą problem
- Lub stworzyć osobny plik (np. `TROUBLESHOOTING.md`, `LARAVEL.md`) z dokumentacją
- Dodać przykłady workaround dla użytkowników

**Status:** ✅ Zaktualizowano issue #60 z propozycjami rozwiązań. Dokumentacja może być dodana przez:
1. Stworzenie PR z sekcją Troubleshooting w README.md
2. Lub zgłoszenie issue z propozycją dokumentacji

## 💡 Proponowana zawartość

### Opcja 1: Sekcja w README.md

Dodać sekcję "Troubleshooting" lub "Known Issues" w README.md:

```markdown
## Troubleshooting

### Laravel package:discover Error

**Problem:** Błąd `Call to a member function make() on null` podczas `package:discover` w Laravel.

**Przyczyna:** Problem występuje, gdy Laravel próbuje uruchomić `package:discover` przed pełną inicjalizacją kontenera.

**Workaround:**

1. **Użyj bezpośredniego buildera manifestu:**

Utwórz plik `scripts/build-package-manifest.php`:

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

2. **Zaktualizuj `composer.json`:**

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

**Alternatywnie:** Użyj wrapper script dla `package:discover`:

```php
#!/usr/bin/env php
<?php
// scripts/package-discover-wrapper.php
$builderScript = __DIR__ . '/build-package-manifest.php';
exec("php {$builderScript} 2>&1", $output, $returnCode);
exit($returnCode);
```

**Długoterminowe rozwiązanie:** Zgłoszono issue w Laravel framework. Zobacz [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) dla szczegółów.
```

### Opcja 2: Osobny plik TROUBLESHOOTING.md

Stworzyć plik `TROUBLESHOOTING.md` w głównym katalogu repozytorium z pełną dokumentacją problemu i rozwiązań.

### Opcja 3: Osobny plik LARAVEL.md

Stworzyć plik `LARAVEL.md` z dokumentacją specyficzną dla Laravel.

## 🔧 Jak to zrobić

### Metoda 1: Bezpośrednia edycja (jeśli masz dostęp)

1. Sklonuj repozytorium `phpstan-fixer`
2. Dodaj sekcję do README.md lub stwórz nowy plik
3. Zatwierdź zmiany i stwórz PR

### Metoda 2: Pull Request

1. Sforkuj repozytorium `phpstan-fixer`
2. Dodaj dokumentację
3. Stwórz PR z propozycją dodania dokumentacji

### Metoda 3: Issue z propozycją

1. Zgłoś issue w repozytorium `phpstan-fixer` z propozycją dokumentacji
2. Dołącz gotową zawartość dokumentacji
3. Poproś maintainera o dodanie

## 📋 Rekomendacja

**Najlepsze podejście:**
1. ✅ Stworzyć PR z dokumentacją (najszybsze i najbardziej profesjonalne)
2. ⏳ Zgłosić issue z propozycją, jeśli nie masz dostępu do tworzenia PR

## 🔗 Related Documents

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [PHPStan Fixer Library Solution Proposal](./PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md) - Propozycje dla biblioteki
- [PHPStan Fixer Laravel Issue Proposal](./PHPSTAN_FIXER_LARAVEL_ISSUE_PROPOSAL.md) - Propozycja dla Laravel

## 📌 Notes

- Dokumentacja powinna być jasna i łatwa do zrozumienia
- Powinna zawierać przykłady kodu
- Powinna wskazywać na długoterminowe rozwiązanie (issue w Laravel)

---

**Last updated:** 2025-12-14

