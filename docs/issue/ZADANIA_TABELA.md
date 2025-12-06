# 📊 Lista Zadań według Priorytetów i Kolejności

**Data aktualizacji:** 2025-12-06  
**Źródło:** `docs/issue/pl/TASKS.md`

---

## 🔴 Wysoki Priorytet (Krytyczne)

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności                | Notatki                     |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|---------------------------|-----------------------------|
| 1  | TASK-043    | ⏳ PENDING  | Implementacja zasady wykrywania BREAKING CHANGE                                | 2-3h           | Brak                      | Bezpieczeństwo zmian        |
| 2  | TASK-037    | ⏳ PENDING  | Weryfikacja istnienia filmów/osób przed generowaniem AI (Faza 2-3)             | 8-12h (F2)     | Faza 1 ✅                 | Faza 1 ukończona           |
| 3  | TASK-038    | ⏳ PENDING  | Weryfikacja zgodności danych AI z slugiem (Faza 2)                             | 6-8h           | Faza 1 ✅                 | Faza 1 ukończona           |

---

## 🟡 Średni Priorytet (Ważne)

### Faza 1: Funkcjonalne usprawnienia

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności              | Notatki                     |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|-------------------------|-----------------------------|
| 1  | TASK-013    | ⏳ PENDING  | Konfiguracja dostępu do Horizon                                                 | 1-2h           | Brak                    | Bezpieczeństwo              |
| 2  | TASK-022    | ⏳ PENDING  | Endpoint listy osób (List People)                                               | 2-3h           | Brak                    | Parzystość API              |
| 3  | TASK-024    | ⏳ PENDING  | Wdrożenie planu baseline locking                                                | 4h             | TASK-012 ✅, TASK-023 ✅ | Stabilizacja generowania    |
| 4  | TASK-025    | ⏳ PENDING  | Standaryzacja flag produktowych i developerskich                                | 1h             | Brak                    | Uporządkowanie zarządzania  |
| 5  | TASK-026    | ⏳ PENDING  | Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji                   | 1-2h           | Brak                    | Poprawa UX                  |

### Faza 2: Infrastruktura i CI/CD

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności | Notatki                    |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|------------|----------------------------|
| 6  | TASK-011    | ⏳ PENDING  | Stworzenie CI dla staging (GHCR)                                                | 3h             | Brak       | Automatyzacja deploymentu  |
| 7  | TASK-015    | ⏳ PENDING  | Automatyczne testy Newman w CI                                                  | 2h             | Brak       | Automatyczna weryfikacja   |
| 8  | TASK-019    | ⏳ PENDING  | Migracja produkcyjnego obrazu Docker na Distroless                              | 3-4h           | Brak       | Bezpieczeństwo             |

### Faza 3: Refaktoryzacja i czyszczenie

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności                | Notatki                        |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|---------------------------|--------------------------------|
| 9  | TASK-032    | ⏳ PENDING  | Automatyczne tworzenie obsady przy generowaniu filmu                            | 3h             | TASK-022 (rozważyć)       | Uzupełnia dane filmów          |
| 10 | TASK-033    | ⏳ PENDING  | Usunięcie modelu Actor i konsolidacja na Person                                 | 2-3h           | TASK-032, TASK-022        | Eliminacja legacy              |
| 11 | TASK-028    | ⏳ PENDING  | Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues                   | 0.5-1h         | Brak                      | Usprawnienie workflow           |
| 12 | TASK-029    | ⏳ PENDING  | Uporządkowanie testów według wzorca AAA lub GWT                                 | 2-3h           | Brak                      | Standaryzacja testów            |
| 13 | TASK-018    | ⏳ PENDING  | Wydzielenie PhpstanFixer jako paczki Composer                                   | 3-4h           | TASK-017 ✅               | Reużywalność                   |

### Faza 4: Dokumentacja i analiza

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności     | Notatki                    |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|----------------|----------------------------|
| 14 | TASK-031    | ⏳ PENDING  | Kierunek rozwoju wersjonowania opisów AI                                        | 1-2h           | TASK-012, TASK-024 | Dokumentacja decyzji      |
| 15 | TASK-040    | ⏳ PENDING  | Analiza formatu TOON vs JSON dla komunikacji z AI                               | 2-3h           | Brak           | Optymalizacja kosztów      |
| 16 | TASK-020    | ⏳ PENDING  | Sprawdzić zachowanie AI dla nieistniejących filmów/osób                         | 2h             | Brak           | Weryfikacja zachowania      |
| 17 | TASK-041    | ⏳ PENDING  | Dodanie seriali i programów telewizyjnych (DDD approach)                        | 30-40h         | Brak           | Duże zadanie, DDD approach  |
| 18 | TASK-046    | ⏳ PENDING  | Integracja TMDb API dla seriali i TV Shows (wymaga TASK-041)                    | 8-10h (F1)     | TASK-041      | Wymaga dodania seriali      |

---

## 🟢 Niski Priorytet (Roadmap)

| #  | ID          | Status     | Tytuł                                                                           | Szacowany czas | Zależności | Notatki                        |
|----|-------------|------------|---------------------------------------------------------------------------------|----------------|------------|--------------------------------|
| 1  | TASK-008    | ⏳ PENDING  | Webhooks System (Roadmap)                                                       | 8-10h          | Brak       | Billing/notifications          |
| 2  | TASK-009    | ⏳ PENDING  | Admin UI (Roadmap)                                                              | 15-20h         | Brak       | Nova/Breeze/Filament           |
| 3  | TASK-010    | ⏳ PENDING  | Analytics/Monitoring Dashboards (Roadmap)                                       | 10-12h         | Brak       | Queue jobs, metrics            |
| 4  | TASK-030    | ⏳ PENDING  | Opracowanie dokumentu o technice testów „trzech linii"                          | 1-2h           | TASK-029   | Wspiera TASK-029               |
| 5  | TASK-042    | ⏳ PENDING  | Analiza możliwych rozszerzeń typów i rodzajów                                   | 4-6h           | Brak       | Dokumentacja analityczna       |

---

## 📊 Podsumowanie Statystyk

### Status

- **🔄 W trakcie:** 0 zadań
- **⏳ Oczekujące:** 27 zadań
- **✅ Zakończone:** 21 zadań (w tym TASK-048)

### Priorytety

- **🔴 Wysoki:** 3 zadania (TASK-048 ✅, TASK-043, TASK-037, TASK-038)
- **🟡 Średni:** 18 zadań
- **🟢 Niski:** 5 zadań

### Szacowany czas realizacji

- **🔴 Wysoki:** ~16-23h (bez TASK-048)
- **🟡 Średni:** ~80-95h
- **🟢 Niski:** ~38-50h
- **Łącznie:** ~134-168h (bez TASK-048)

---

## 📝 Legenda

- **Status:**
  - 🔄 IN_PROGRESS - Zadanie w trakcie realizacji
  - ⏳ PENDING - Zadanie oczekujące na rozpoczęcie
  - ✅ COMPLETED - Zadanie zakończone (nie pokazane w tabeli)

- **Priorytety:**
  - 🔴 Wysoki - Krytyczne dla stabilności/bezpieczeństwa
  - 🟡 Średni - Ważne, ale nie blokujące
  - 🟢 Niski - Roadmap, opcjonalne

- **Zależności:**
  - ✅ - Zadanie zakończone
  - TASK-XXX - Wymaga ukończenia innego zadania

---

## 🎯 Rekomendowana Kolejność Wykonania (MVP)

### Najpierw (🔴 Wysoki Priorytet)

1. **TASK-043** - BREAKING CHANGE detection (zabezpiecza przyszłe zmiany)
2. **TASK-037** (F2-3) - Weryfikacja przed AI (krytyczne dla jakości)
3. **TASK-038** (F2) - Weryfikacja zgodności danych (krytyczne dla jakości)

### Następnie (🟡 Średni - Faza 1)

1. **TASK-013** - Konfiguracja Horizon (bezpieczeństwo)
2. **TASK-022** - Lista osób (parzystość API)
3. **TASK-024** - Baseline locking (stabilizacja)
4. **TASK-025** - Standaryzacja flag (uproszczenie)

### Później (🟡 Średni - Fazy 2-4)

1. Infrastruktura i CI/CD (TASK-011, TASK-015, TASK-019)
2. Refaktoryzacja (TASK-032, TASK-033, TASK-028, TASK-029, TASK-018)
3. Dokumentacja (TASK-031, TASK-040, TASK-020)

### Na końcu (🟢 Niski)

1. Zadania z roadmap (TASK-008, TASK-009, TASK-010, TASK-030, TASK-042)

---

**Uwaga:** Tabela zawiera tylko zadania aktywne (PENDING lub IN_PROGRESS).

Zadania zakończone (✅ COMPLETED) nie zostały uwzględnione w tabeli.
