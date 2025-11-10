# AI Generation Baseline Locking – Follow-up Plan

> **Data utworzenia:** 2025-11-10  
> **Kontekst:** Wyłączenie blokad baseline (TASK-012) pod nową flagą `ai_generation_baseline_locking` i przywrócenie stabilnej wersji API.  
> **Kategoria:** technical

## 🎯 Cel

Utrzymać stabilny, produkcyjny przebieg kolejek (bez locków) i jednocześnie przygotować kontrolowany rollout usprawnień baseline’owych z TASK-012.

## 📋 Kolejne kroki

1. **Walidacja flagi w środowiskach**
   - Dodać wpis do checklisty deploymentowej informujący, że `ai_generation_baseline_locking` musi pozostać `off` do czasu zamknięcia działań poniżej.
   - Przygotować zmianę w panelu admin (lub `.env`) umożliwiającą łatwe przełączanie flagi tylko na stagingu.

2. **Dokończenie logiki baseline’owej pod flagą**
   - Pokryć testami scenariusze dla `RealGenerate*Job` z flagą `on` (łączność z OpenAI mockiem, kontrola `locale/context_tag`).
   - Doprecyzować obsługę `baselineDescriptionId`/`baselineBioId` w akcjach i odpowiedziach API (czy chcemy je eksponować, gdy flaga `off`?).
   - Zweryfikować cache invalidation dla różnych slugów (oryginalny vs. promowany).

3. **Obserwacja w Horizon**
   - Po włączeniu flagi na stagingu porównać liczbę jobów i payloady w Horizon z wariantem `off`.
   - Dodać metrykę logującą, czy job pracował w trybie baseline lock (`feature active`) oraz jaki był wynik (update/append).

4. **Decyzja roll-outowa**
   - Jeśli testy i staging OK: przygotować plan wdrożenia (stopniowe włączanie flagi + monitoring).
   - Jeśli pojawią się regresje: rozważyć alternatywną implementację (np. przechowywanie nowych wariantów w osobnych polach zamiast aktualizacji baseline).

## 🔗 Powiązane Dokumenty

- [TASK-012 dokumentacja](../issue/pl/TASKS.md)
- `config/pennant.php` – definicja flag
- `app/Jobs/MockGenerate*Job.php`, `RealGenerate*Job.php` – aktualny kod jobów

## 📌 Notatki

- Obecna implementacja flagi utrzymuje dotychczasową funkcjonalność (append opisu/bio) przy `off`.
- Po włączeniu flagi baseline jest aktualizowany in-place; korzystamy z locków z TASK-012.
- Testy jednostkowe pokrywają oba tryby (`Feature::activate('ai_generation_baseline_locking')`).

---

**Ostatnia aktualizacja:** 2025-11-10

