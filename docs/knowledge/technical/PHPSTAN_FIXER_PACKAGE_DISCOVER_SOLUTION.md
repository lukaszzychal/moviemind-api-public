# Rozwiązanie problemu phpstan-fixer z Laravel package:discover

> **Creation Date:** 2025-12-14  
> **Context:** Analiza i rozwiązanie problemu `Call to a member function make() on null` podczas `package:discover`  
> **Category:** technical

## 🎯 Problem

Błąd `Call to a member function make() on null` występuje podczas:
- `php artisan package:discover` (runtime)
- Inicjalizacji Laravel w testach (testy Feature)

### Przyczyna

`PackageDiscoverCommand` rozszerza `Command`, który wymaga kontenera Laravel (`$this->laravel->make()`), ale podczas inicjalizacji kontener może nie być w pełni gotowy.

**Stack trace:**
```
Command::run() (linia 175)
  → $this->laravel->make(Factory::class, ...)
  → $this->laravel jest null
```

## ✅ Rozwiązanie 1: Bezpośredni builder manifestu (workaround)

Utworzono `scripts/build-package-manifest.php`, który buduje manifest bez wymagania kontenera Laravel.

**Zalety:**
- ✅ Działa dla `composer install/update`
- ✅ Nie wymaga kontenera Laravel
- ✅ Prosty i niezależny

**Wady:**
- ❌ Nie rozwiązuje problemu w testach (błąd występuje podczas inicjalizacji Laravel)
- ❌ Wymaga utrzymania dodatkowego skryptu

## 🔧 Rozwiązanie 2: Lazy loading manifestu (proponowane dla Laravel)

Zmodyfikować `PackageManifest::getManifest()` aby nie wywoływał `build()` automatycznie, tylko zwracał pustą tablicę jeśli manifest nie istnieje.

**Implementacja:**
```php
protected function getManifest()
{
    if (! is_null($this->manifest)) {
        return $this->manifest;
    }

    if (! is_file($this->manifestPath)) {
        // Don't build automatically - return empty array
        // Manifest will be built when package:discover is explicitly called
        return $this->manifest = [];
    }

    return $this->manifest = is_file($this->manifestPath) ?
        $this->files->getRequire($this->manifestPath) : [];
}
```

**Zalety:**
- ✅ Nie wymaga kontenera podczas inicjalizacji
- ✅ Manifest jest budowany tylko gdy jest potrzebny
- ✅ Rozwiązuje problem w testach

**Wady:**
- ❌ Wymaga zmiany w Laravel framework
- ❌ Może wpłynąć na inne części systemu

## 🎯 Rozwiązanie 3: Sprawdzenie kontenera w Command (proponowane dla Laravel)

Zmodyfikować `Command::run()` aby sprawdzał, czy kontener jest dostępny, zanim spróbuje go użyć.

**Implementacja:**
```php
public function run(InputInterface $input, OutputInterface $output): int
{
    if ($this->laravel === null) {
        // Container not ready - use simple output
        $this->output = $output instanceof OutputStyle ? $output : new OutputStyle($input, $output);
        $this->components = new Factory($this->output);
    } else {
        $this->output = $output instanceof OutputStyle ? $output : $this->laravel->make(
            OutputStyle::class, ['input' => $input, 'output' => $output]
        );
        $this->components = $this->laravel->make(Factory::class, ['output' => $this->output]);
    }

    // ... rest of the method
}
```

**Zalety:**
- ✅ Rozwiązuje problem bez zmiany logiki manifestu
- ✅ Kompatybilne wstecz
- ✅ Nie wpływa na inne części systemu

**Wady:**
- ❌ Wymaga zmiany w Laravel framework
- ❌ Może wymagać dodatkowych zmian w innych miejscach

## 📋 Rozwiązanie 4: Dla biblioteki phpstan-fixer

Biblioteka `phpstan-fixer` nie może bezpośrednio naprawić tego problemu, ponieważ jest to problem w Laravel framework. Jednak biblioteka może:

1. **Upewnić się, że `dont-discover` jest poprawnie skonfigurowane:**
   - ✅ Już naprawione w v1.2.2 (`"dont-discover": []`)

2. **Dodać dokumentację:**
   - Opisać problem i workaround
   - Zasugerować użycie bezpośredniego buildera manifestu

3. **Zasugerować zmiany w Laravel:**
   - Zgłosić issue w Laravel framework
   - Zaproponować rozwiązanie 2 lub 3

## 🔗 Related Documents

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [Issue #63](https://github.com/lukaszzychal/phpstan-fixer/issues/63) - dont-discover should be array
- [TASK-049](../issue/pl/TASKS.md#task-049) - Weryfikacja naprawy problemu

## 📌 Notes

- Problem występuje zarówno w runtime, jak i w testach
- Workaround działa dla `composer install/update`, ale nie dla testów
- Rozwiązanie wymaga zmiany w Laravel framework lub alternatywnego podejścia

---

**Last updated:** 2025-12-14

