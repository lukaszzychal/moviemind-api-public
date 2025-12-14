# Propozycja rozwiązania dla Laravel Framework

> **Creation Date:** 2025-12-14  
> **Context:** Propozycja rozwiązania problemu `package:discover` dla Laravel framework  
> **Category:** technical

## 🎯 Problem

Błąd `Call to a member function make() on null` występuje podczas `package:discover` w Laravel, gdy kontener nie jest jeszcze gotowy.

**Stack trace:**
```
Command::run() (linia 175)
  → $this->laravel->make(Factory::class, ...)
  → $this->laravel jest null
```

## 🔍 Przyczyna

`PackageDiscoverCommand` rozszerza `Command`, który wymaga kontenera Laravel (`$this->laravel->make()`), ale podczas wywołania komendy kontener może nie być w pełni gotowy.

## 💡 Proponowane rozwiązanie

Zmodyfikować `Command::run()` aby sprawdzał, czy kontener jest dostępny, zanim spróbuje go użyć.

### Implementacja

**Plik:** `vendor/laravel/framework/src/Illuminate/Console/Command.php`

**Zmiana w metodzie `run()`:**

```php
#[\Override]
public function run(InputInterface $input, OutputInterface $output): int
{
    // Check if container is available before using it
    if ($this->laravel === null) {
        // Container not ready - use simple output without container
        $this->output = $output instanceof OutputStyle 
            ? $output 
            : new OutputStyle($input, $output);
        $this->components = new Factory($this->output);
    } else {
        // Container is available - use it as before
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

    $this->configurePrompts($input);

    try {
        return parent::run(
            $this->input = $input, $this->output
        );
    } finally {
        $this->untrap();
    }
}
```

### Zalety

- ✅ Rozwiązuje problem u źródła
- ✅ Kompatybilne wstecz (nie zmienia zachowania gdy kontener jest dostępny)
- ✅ Nie wpływa na inne części systemu
- ✅ Proste i czytelne

### Testy

**Scenariusz 1: Kontener dostępny (normalne użycie)**
- Komenda działa jak dotychczas
- Używa kontenera do tworzenia `OutputStyle` i `Factory`

**Scenariusz 2: Kontener niedostępny (podczas inicjalizacji)**
- Komenda używa prostego outputu bez kontenera
- Nie powoduje błędu `Call to a member function make() on null`

## 🔗 Related Documents

- [Issue #60](https://github.com/lukaszzychal/phpstan-fixer/issues/60) - Laravel package:discover error
- [PHPStan Fixer Package Discover Solution](./PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md) - Szczegółowa analiza
- [PHPStan Fixer Library Solution Proposal](./PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md) - Propozycje dla biblioteki

## 📌 Notes

- Rozwiązanie wymaga zmiany w Laravel framework
- Może być zgłoszone jako pull request do Laravel
- Alternatywnie, może być wdrożone jako patch w projekcie użytkownika

---

**Last updated:** 2025-12-14

