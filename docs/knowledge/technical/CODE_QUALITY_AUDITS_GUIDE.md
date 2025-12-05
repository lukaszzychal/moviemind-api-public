# Przewodnik Audytów Jakości Kodu

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Utworzenie kompleksowego przewodnika audytów jakości kodu, refaktoryzacji i redesignu aplikacji  
> **Kategoria:** technical

## 🎯 Cel

Ten dokument definiuje systematyczne podejście do audytów jakości kodu, refaktoryzacji i redesignu aplikacji MovieMind API. Zawiera zasady, procesy i workflow dla utrzymania wysokiej jakości kodu.

## 📋 Typy Audytów

### Wyrywkowe Audyty (Ad-Hoc)

**Kiedy przeprowadzać:**
- Podczas wykonywania zadań (gdy napotkamy problemy jakości kodu)
- Podczas code review
- Gdy zauważymy code smells lub naruszenia zasad
- Po napotkaniu problemów z testowaniem lub utrzymaniem

**Zakres:**
- Pliki dotknięte aktualnym zadaniem
- Powiązane pliki (jeśli problem jest widoczny)
- Konkretne problemy (code smells, duplikacja, naruszenia SOLID)

**Czas trwania:**
- 15-30 minut dla małych audytów
- 1-2 godziny dla większych audytów

**Proces:**
1. Zidentyfikuj problem jakości kodu
2. Ocenij rozmiar problemu (drobny/średni/duży)
3. Zastosuj odpowiednią strategię naprawy (patrz: Workflow Naprawy Problemów)
4. Udokumentuj znalezione problemy (jeśli wymagają osobnego zadania)

### Całościowe Audyty (Planowane)

**Kiedy przeprowadzać:**
- **Quarterly** (co kwartał) - podstawowe audyty jakości kodu
- **Semi-annually** (co pół roku) - szczegółowe audyty z pełną analizą
- **Before major releases** - przed większymi wydaniami
- **After major refactoring** - po większych refaktoryzacjach

**Zakres:**
- Cała aplikacja lub wybrane moduły
- Wszystkie aspekty jakości kodu (SOLID, DRY, code smells, testability, performance)
- Architektura i design patterns
- Test coverage i jakość testów

**Czas trwania:**
- Quarterly: 4-8 godzin
- Semi-annually: 1-2 dni
- Before major releases: 2-3 dni
- After major refactoring: 1 dzień

**Proces:**
1. Planowanie audytu (1-2 dni przed)
2. Przeprowadzenie audytu zgodnie z checklist
3. Dokumentacja znalezionych problemów
4. Priorytetyzacja problemów
5. Utworzenie zadań dla problemów wymagających naprawy
6. Raportowanie wyników

## 📊 Checklist Audytu Jakości Kodu

### SOLID Principles

- [ ] **Single Responsibility Principle (SRP)**
  - Każda klasa ma jedną odpowiedzialność
  - Brak "God Classes" (klas robiących zbyt wiele)
  - Metody są skupione na jednym zadaniu

- [ ] **Open/Closed Principle (OCP)**
  - Klasy są otwarte na rozszerzenia, zamknięte na modyfikacje
  - Używane są abstrakcje (interfaces, abstract classes)
  - Brak bezpośrednich modyfikacji istniejącego kodu przy dodawaniu funkcji

- [ ] **Liskov Substitution Principle (LSP)**
  - Podklasy mogą zastąpić klasy bazowe bez zmiany zachowania
  - Kontrakty interfejsów są przestrzegane
  - Brak naruszeń kontraktów w hierarchiach dziedziczenia

- [ ] **Interface Segregation Principle (ISP)**
  - Interfejsy są specyficzne, nie ogólne
  - Klasy nie implementują metod, których nie używają
  - Interfejsy są podzielone na mniejsze, bardziej specyficzne

- [ ] **Dependency Inversion Principle (DIP)**
  - Wysokopoziomowe moduły nie zależą od niskopoziomowych
  - Używane są abstrakcje (interfaces) zamiast konkretnych implementacji
  - Dependency Injection jest używane konsekwentnie

### Code Quality

- [ ] **DRY (Don't Repeat Yourself)**
  - Brak duplikacji kodu (sprawdzić czy duplikacja występuje w 3+ miejscach)
  - Wspólna logika jest wyekstrahowana do metod/klas
  - Nie ma nadmiernej abstrakcji (YAGNI)

- [ ] **Code Smells**
  - Brak "God Classes" (zbyt duże klasy)
  - Brak "Long Methods" (zbyt długie metody)
  - Brak "Long Parameter Lists" (używane są DTO/Request objects)
  - Brak "Feature Envy" (metody używają danych z innych klas)
  - Brak "Data Clumps" (używane są Value Objects)
  - Brak "Primitive Obsession" (używane są Value Objects zamiast prymitywów)
  - Brak "Shotgun Surgery" (jedna zmiana wymaga wielu małych zmian)
  - Brak "Divergent Change" (klasa zmienia się z wielu powodów)

- [ ] **Testability**
  - Kod jest łatwy do testowania
  - Używane są dependency injection
  - Brak tight coupling
  - Metody są izolowane i testowalne

- [ ] **Readability**
  - Kod jest czytelny i zrozumiały
  - Nazwy zmiennych/metod/klas są opisowe
  - Komentarze wyjaśniają "dlaczego", nie "co"
  - Formatowanie jest spójne (Pint)

- [ ] **Type Safety**
  - Wszystkie parametry i zwracane wartości mają type hints
  - Używane jest `declare(strict_types=1);`
  - Brak użycia `mixed` (gdzie to możliwe)
  - PHPStan level 5+ bez błędów

### Architecture

- [ ] **Separation of Concerns**
  - Controllers tylko routują requesty
  - Business logic w Services
  - Data access w Repositories
  - Brak logiki biznesowej w Models (poza accessorami/mutatorami)

- [ ] **Dependency Management**
  - Używane są interfaces zamiast konkretnych klas
  - Dependency Injection jest konsekwentne
  - Brak service location (poza Jobs, gdzie method injection)
  - Brak circular dependencies

- [ ] **Design Patterns**
  - Wzorce są używane odpowiednio (nie na siłę)
  - Repository Pattern dla data access
  - Service Layer dla business logic
  - Event-Driven dla asynchronicznych operacji
  - Factory/Builder gdy potrzebne

- [ ] **Performance Considerations**
  - N+1 queries są unikane (eager loading)
  - Cache jest używany odpowiednio
  - Query optimization (indeksy, where clauses)
  - Brak przedwczesnej optymalizacji

### Testing

- [ ] **Test Coverage**
  - Minimum 80% test coverage
  - Wszystkie nowe funkcje mają testy
  - Feature Tests dla API endpoints
  - Unit Tests dla business logic

- [ ] **Test Quality**
  - Testy są czytelne i zrozumiałe
  - Testy testują zachowanie, nie implementację (Chicago School)
  - Brak nadmiernych mocków (tylko external APIs)
  - Testy są szybkie i izolowane

- [ ] **TDD Compliance**
  - Nowe funkcje są tworzone z TDD (Red-Green-Refactor)
  - Testy są pisane przed implementacją
  - Wszystkie testy przechodzą

## 🔄 Workflow Naprawy Problemów

### Podczas Wykonywania Zadania

**1. Napotkanie problemu jakości kodu:**
   - Ocenij rozmiar problemu (drobny/średni/duży)
   - Sprawdź czy dotyczy aktualnego zadania
   - Zastosuj odpowiednią strategię (naprawa vs zadanie)

**2. Drobne problemy (naprawiać na bieżąco):**
   - Code smells w plikach dotkniętych aktualnym zadaniem
   - Drobne naruszenia SOLID w kontekście aktualnego zadania
   - Duplikacja kodu w plikach dotkniętych zadaniem
   - Brakujące type hints w nowym kodzie
   - Formatowanie (Pint powinien to naprawić automatycznie)
   - Drobne refaktoryzacje metod (extract method, rename)

   **Akcja:**
   - Naprawić natychmiast
   - Dodać do commita (jeśli dotyczy aktualnego zadania)
   - Udokumentować w commit message (np. "refactor: extract method for clarity")

**3. Średnie problemy (dodać do aktualnego zadania jeśli czas pozwala):**
   - Code smells w powiązanych plikach (nie dotkniętych bezpośrednio)
   - Refaktoryzacja małych metod/klas w kontekście zadania
   - Ujednolicenie podejścia w powiązanych plikach
   - Drobne naruszenia SOLID w powiązanych plikach

   **Akcja:**
   - Jeśli czas pozwala → naprawić w ramach zadania
   - Jeśli brak czasu → utworzyć zadanie z priorytetem 🟡 (medium)
   - Dodać do `docs/issue/pl/TASKS.md`

**4. Duże problemy (utworzyć nowe zadanie):**
   - Refaktoryzacja całych modułów
   - Redesign architektury
   - Duże naruszenia SOLID wymagające większych zmian
   - Code smells wymagające refaktoryzacji wielu plików
   - Problemy wydajnościowe wymagające analizy
   - Duplikacja kodu wymagająca większej refaktoryzacji

   **Akcja:**
   - Zawsze utworzyć nowe zadanie
   - Priorytet: 🟡 (średni) lub 🔴 (wysoki, jeśli blokuje)
   - Dodać do `docs/issue/pl/TASKS.md`
   - Opisać problem, lokalizację i proponowane rozwiązanie

### Przykłady Decyzji

**Przykład 1: Drobny problem**
- **Sytuacja:** Podczas dodawania nowej metody w `MovieService`, zauważono że metoda `generateSlug()` jest zbyt długa (50 linii)
- **Decyzja:** Naprawić natychmiast - wyekstrahować logikę do mniejszych metod
- **Akcja:** Refaktoryzacja w ramach aktualnego commita

**Przykład 2: Średni problem**
- **Sytuacja:** Podczas pracy nad `MovieController`, zauważono że `PersonController` ma podobną logikę (duplikacja)
- **Decyzja:** Jeśli czas pozwala → naprawić w ramach zadania, jeśli nie → utworzyć zadanie
- **Akcja:** Utworzenie zadania "Refactor: Extract common logic from PersonController and MovieController"

**Przykład 3: Duży problem**
- **Sytuacja:** Podczas audytu zauważono, że cały moduł `Jobs` ma problemy z dependency injection (service location)
- **Decyzja:** Utworzyć nowe zadanie
- **Akcja:** Utworzenie zadania "Refactor: Replace service location with method injection in Jobs" z priorytetem 🟡

## 📈 Metryki Jakości Kodu

### Kluczowe Metryki

- **PHPStan Level** - obecnie 5, cel: utrzymać lub zwiększyć
- **Test Coverage** - cel: minimum 80%
- **Code Smells** - liczba znalezionych code smells
- **SOLID Violations** - liczba naruszeń zasad SOLID
- **Duplication** - procent zduplikowanego kodu
- **Cyclomatic Complexity** - średnia złożoność cyklomatyczna metod

### Raportowanie

- Raport po każdym całościowym audycie
- Tracking trendów w czasie
- Porównanie z poprzednimi audytami
- Wizualizacja metryk (jeśli możliwe)

### Narzędzia do Metryk

- **PHPStan** - static analysis, poziom 5
- **PHPUnit** - test coverage
- **Laravel Pint** - code formatting
- **Manual review** - code smells, SOLID violations

## 🔗 Integracja z Istniejącymi Procesami

### Code Review

- Sprawdzanie zgodności z zasadami jakości kodu
- Wykrywanie code smells
- Weryfikacja SOLID principles
- Sugerowanie refaktoryzacji gdy potrzebne

### Pre-Commit

- **Pint** (formatowanie) - już istnieje
- **PHPStan** (static analysis) - już istnieje
- **Testy** - już istnieją
- **GitLeaks** (sekrety) - już istnieje

### CI/CD Pipeline

- Dodanie opcjonalnych checków jakości kodu
- Raportowanie metryk jakości
- Ostrzeżenia o code smells (nie blokujące)

## 📝 Template Raportu Audytu

```markdown
# Code Quality Audit Report - YYYY-MM-DD

## Executive Summary
- Audit Date: YYYY-MM-DD
- Scope: [Comprehensive/Partial]
- Issues Found: X (Critical: Y, High: Z, Medium: W, Low: V)

## Findings

### Critical (P0)
- [Issue 1]
  - Description: [Opis problemu]
  - Location: [Plik, linia]
  - Recommendation: [Rekomendacja naprawy]
  - Status: [Open/In Progress/Resolved]

### High (P1)
- [Issue 2]
  ...

### Medium (P2)
- [Issue 3]
  ...

### Low (P3)
- [Issue 4]
  ...

## SOLID Principles Review
- SRP: ✅/⚠️/❌ [Komentarz]
- OCP: ✅/⚠️/❌ [Komentarz]
- LSP: ✅/⚠️/❌ [Komentarz]
- ISP: ✅/⚠️/❌ [Komentarz]
- DIP: ✅/⚠️/❌ [Komentarz]

## Code Quality Metrics
- PHPStan Level: 5
- Test Coverage: X%
- Code Smells: X
- Duplication: X%
- Cyclomatic Complexity: X (average)

## Recommendations
1. [Recommendation 1]
2. [Recommendation 2]
3. [Recommendation 3]

## Action Items
- [ ] Task 1: [Opis zadania]
- [ ] Task 2: [Opis zadania]
- [ ] Task 3: [Opis zadania]

## Next Audit
- Scheduled: YYYY-MM-DD
- Type: [Quarterly/Semi-annually/Before Release]
```

## 🚀 Workflow/Pipeline dla Audytów

### Opcje Implementacji

**Opcja A: Osobny Workflow (Rekomendowane)**
- Utworzenie `.github/workflows/code-quality-audit.yml`
- Uruchamianie manualne (workflow_dispatch)
- Planowane uruchomienia (quarterly/semi-annually)
- Raportowanie metryk jakości kodu

**Zalety:**
- Separacja od innych workflow
- Łatwe uruchamianie manualne
- Jasne metryki i raporty
- Możliwość integracji z narzędziami zewnętrznymi

**Wady:**
- Dodatkowy workflow do utrzymania
- Wymaga konfiguracji

**Opcja B: Zintegrowany z Istniejącymi Workflow**
- Dodanie jobów do istniejących workflow (np. `ci.yml`)
- Automatyczne uruchamianie przy każdym PR

**Zalety:**
- Mniej plików do utrzymania
- Automatyczne uruchamianie

**Wady:**
- Może być mniej czytelne
- Może spowolnić CI pipeline

**Opcja C: Tylko Manualne Audyty**
- Dokumentacja + checklist
- Manualne przeprowadzanie audytów

**Zalety:**
- Prostota
- Elastyczność

**Wady:**
- Brak automatyzacji
- Możliwość pominięcia audytów

### Rekomendacja

**Opcja A (Osobny Workflow)** dla całościowych audytów + manualne wyrywkowe audyty.

**Uzasadnienie:**
- Całościowe audyty wymagają więcej czasu i nie powinny blokować CI
- Wyrywkowe audyty są ad-hoc i nie wymagają automatyzacji
- Osobny workflow pozwala na elastyczne planowanie
- Jasne raportowanie i metryki

## 📚 Powiązane Dokumenty

- [Code Writing Standards](../../.cursor/rules/coding-standards.mdc) - Zasady pisania kodu
- [Security Audits Guide](./APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) - Przewodnik audytów bezpieczeństwa
- [Refactoring Proposals](./REFACTORING_PROPOSAL.md) - Propozycje refaktoryzacji
- [Code Quality Tools](../reference/CODE_QUALITY_TOOLS.md) - Narzędzia jakości kodu
- [Testing Strategy](../reference/TESTING_STRATEGY.md) - Strategia testowania

## 🔄 Częstotliwość Audytów - Podsumowanie

### Wyrywkowe Audyty (Ad-Hoc)
- **Kiedy:** Podczas wykonywania zadań, code review, gdy napotkamy problemy
- **Czas:** 15-30 minut (małe), 1-2 godziny (większe)
- **Zakres:** Pliki dotknięte zadaniem, konkretne problemy

### Całościowe Audyty (Planowane)
- **Quarterly** (co kwartał): 4-8 godzin - podstawowe audyty
- **Semi-annually** (co pół roku): 1-2 dni - szczegółowe audyty
- **Before major releases**: 2-3 dni - przed większymi wydaniami
- **After major refactoring**: 1 dzień - po większych refaktoryzacjach

## ✅ Checklist Szybkiego Audytu (Wyrywkowego)

Podczas wykonywania zadania, sprawdź:

- [ ] Czy kod jest czytelny i zrozumiały?
- [ ] Czy nie ma oczywistych code smells (God Class, Long Method)?
- [ ] Czy są type hints i strict types?
- [ ] Czy nie ma duplikacji kodu w plikach dotkniętych zadaniem?
- [ ] Czy dependency injection jest używane poprawnie?
- [ ] Czy testy są napisane (jeśli to nowa funkcja)?
- [ ] Czy PHPStan nie zgłasza błędów?

---

**Ostatnia aktualizacja:** 2025-01-27

