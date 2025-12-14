# Propozycja rozwiązania dla biblioteki phpstan-fixer

> **Creation Date:** 2025-12-14  
> **Context:** Propozycja rozwiązania problemu `package:discover` dla biblioteki phpstan-fixer  
> **Category:** technical

## 🎯 Problem

Błąd `Call to a member function make() on null` występuje podczas `package:discover` w Laravel, mimo że `dont-discover` jest poprawnie skonfigurowane.

## 🔍 Analiza

Problem nie jest związany z `dont-discover` (jest poprawne w v1.2.2), ale z mechanizmem inicjalizacji kontenera Laravel podczas `package:discover`.

**Root cause:**
- `PackageDiscoverCommand` rozszerza `Command`, który wymaga kontenera Laravel
- Podczas wywołania komendy kontener może nie być w pełni gotowy
- `Command::run()` próbuje użyć `$this->laravel->make()`, ale `$this->laravel` jest `null`

## 💡 Propozycje rozwiązań dla biblioteki

### Rozwiązanie 1: Dokumentacja i workaround (Najprostsze)

**Działania:**
1. Dodać sekcję w README opisującą problem
2. Zasugerować użycie bezpośredniego buildera manifestu
3. Dodać przykład workaround

**Zalety:**
- ✅ Nie wymaga zmian w kodzie
- ✅ Szybkie do wdrożenia
- ✅ Pomaga użytkownikom

**Wady:**
- ❌ Nie rozwiązuje problemu, tylko go dokumentuje

### Rozwiązanie 2: Zgłoszenie issue w Laravel (Rekomendowane)

**Działania:**
1. Zgłosić issue w Laravel framework z opisem problemu
2. Zaproponować rozwiązanie (sprawdzenie kontenera w `Command::run()`)
3. Dodać link do issue w dokumentacji biblioteki

**Proponowane rozwiązanie dla Laravel:**
```php
// W Illuminate\Console\Command::run()
public function run(InputInterface $input, OutputInterface $output): int
{
    if ($this->laravel === null) {
        // Container not ready - use simple output without container
        $this->output = $output instanceof OutputStyle 
            ? $output 
            : new OutputStyle($input, $output);
        $this->components = new Factory($this->output);
    } else {
        $this->output = $output instanceof OutputStyle 
            ? $output 
            : $this->laravel->make(
                OutputStyle::class, 
                ['input' => $input, 'output' => $output]
            );
        $this->components = $this->laravel->make(
            Factory::class, 
            ['output' => $this->output]
        );
    }
    
    // ... rest of the method
}
```

**Zalety:**
- ✅ Rozwiązuje problem u źródła
- ✅ Pomaga wszystkim użytkownikom Laravel
- ✅ Nie wymaga zmian w bibliotece

**Wady:**
- ❌ Wymaga czasu na wdrożenie w Laravel
- ❌ Może nie zostać zaakceptowane

### Rozwiązanie 3: Composer script (Alternatywne)

**Działania:**
1. Dodać Composer script do budowania manifestu
2. Użyć go w `post-autoload-dump` zamiast `package:discover`
3. Dodać dokumentację

**Przykład:**
```json
{
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php -r \"require 'vendor/autoload.php'; \\$manifest = new \\Illuminate\\Foundation\\PackageManifest(new \\Illuminate\\Filesystem\\Filesystem, __DIR__, __DIR__.'/bootstrap/cache/packages.php'); \\$manifest->build();\""
    ]
  }
}
```

**Zalety:**
- ✅ Nie wymaga kontenera Laravel
- ✅ Działa dla `composer install/update`
- ✅ Może być dodane do dokumentacji

**Wady:**
- ❌ Nie rozwiązuje problemu w testach
- ❌ Wymaga ręcznej konfiguracji przez użytkowników

## 📋 Rekomendacja

**Najlepsze podejście:**
1. ✅ **Rozwiązanie 1** - Dodać dokumentację z workaround (szybkie)
2. ✅ **Rozwiązanie 2** - Zgłosić issue w Laravel z propozycją rozwiązania (długoterminowe)
3. ⏳ **Rozwiązanie 3** - Rozważyć jako alternatywę, jeśli Laravel nie zaakceptuje rozwiązania

## 🔗 Related Documents

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [Issue #63](https://github.com/lukaszzychal/phpstan-fixer/issues/63) - dont-discover should be array
- [TASK-049](../issue/pl/TASKS.md#task-049) - Weryfikacja naprawy problemu
- [PHPStan Fixer Package Discover Solution](./PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md) - Szczegółowa analiza

## 📌 Notes

- Problem występuje zarówno w runtime, jak i w testach
- `dont-discover` jest poprawnie skonfigurowane w v1.2.2
- Rozwiązanie wymaga zmiany w Laravel framework lub alternatywnego podejścia

---

**Last updated:** 2025-12-14

