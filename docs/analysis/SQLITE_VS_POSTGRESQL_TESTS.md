# SQLite vs PostgreSQL dla testów – stan obecny

## Obecne rozwiązanie (od 2025)

**Wszystkie testy używają PostgreSQL.**

- **Główny job testowy w CI** oraz **testy lokalne** (przez Docker) działają na PostgreSQL.
- SQLite nie jest już używany w testach.
- Jedna baza (PostgreSQL) dla testów i produkcji – brak gałęzi kodu i migracji zależnych od SQLite.

Szczegóły:
- Konfiguracja: [docs/knowledge/reference/TESTING_DATABASE.md](../knowledge/reference/TESTING_DATABASE.md)
- Strategia: [docs/knowledge/reference/TESTING_STRATEGY.md](../knowledge/reference/TESTING_STRATEGY.md)
- CI/QA: [docs/qa/POSTGRESQL_TESTING.md](../qa/POSTGRESQL_TESTING.md)

---

*Poniżej zachowano historyczną analizę porównawczą SQLite vs PostgreSQL. Nie opisuje już bieżącej konfiguracji testów.*

---

## Historyczna analiza (przed migracją na PostgreSQL)

### SQLite (dawniej używany w testach)

- Szybkość, prostota, zero konfiguracji w CI.
- Ograniczenia: brak partial unique index, inne funkcje dat, brak ILIKE itd.

### PostgreSQL (obecnie używany wszędzie)

- Zgodność z produkcją, pełne funkcje SQL, te same constraints.
- Wymaga: Docker lokalnie, service container w CI.

**Ostatnia aktualizacja:** 2025 – migracja testów na PostgreSQL zakończona.
