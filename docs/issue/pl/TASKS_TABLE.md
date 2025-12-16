# 📊 Tabela Zadań - Według Priorytetów

> **Data aktualizacji:** 2025-12-16  
> **Status:** 🔄 Aktywna

---

## 🔴 Priorytet Wysoki

| ID | Tytuł | Status | Czas | Kategoria | Zależności |
|---|---|---|---|---|---|
| TASK-044 | Integracja TMDb API dla weryfikacji istnienia filmów | ✅ COMPLETED | 8-12h | MVP Faza 1 | Brak |
| TASK-050 | Dodanie Basic Auth dla endpointów admin | ✅ COMPLETED | 2-3h | MVP Faza 1 | Brak |
| TASK-048 | Kompleksowa dokumentacja bezpieczeństwa (OWASP) | ✅ COMPLETED | 4-6h | MVP Faza 1 | Brak |
| TASK-043 | Implementacja zasady wykrywania BREAKING CHANGE | ✅ COMPLETED | 2-3h | MVP Faza 1 | Brak |
| TASK-037 | Weryfikacja istnienia filmów/osób przed generowaniem AI | ✅ COMPLETED | 4-6h (F1) + 8-12h (F2) + 20-30h (F3) | MVP Faza 1 | Brak |
| TASK-038 | Weryfikacja zgodności danych AI z slugiem | ✅ COMPLETED | 3-4h (F1) + 6-8h (F2) | MVP Faza 1 | Brak |
| TASK-027 | Diagnostyka duplikacji eventów generowania | ✅ COMPLETED | 2h | MVP Faza 1 | Brak |
| TASK-023 | Integracja i naprawa połączenia z OpenAI | ✅ COMPLETED | 3h | MVP Faza 1 | Brak |
| TASK-012 | Lock + Multi-Description Handling przy generowaniu | ✅ COMPLETED | 4-5h | MVP Faza 1 | Brak |
| TASK-001 | Refaktoryzacja Kontrolerów API (SOLID) | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-000 | People - List Endpoint z Filtrowaniem po Role | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-021 | Naprawa duplikacji eventów przy generowaniu filmu | ✅ COMPLETED | 2h | MVP Faza 1 | Brak |

---

## 🟡 Priorytet Średni

| ID | Tytuł | Status | Czas | Kategoria | Zależności |
|---|---|---|---|---|---|
| TASK-013 | Konfiguracja dostępu do Horizon | ✅ COMPLETED | 1-2h | MVP Faza 1 | Brak |
| TASK-022 | Endpoint listy osób (List People) | ✅ COMPLETED | 2-3h | MVP Faza 2 | Brak |
| TASK-024 | Wdrożenie planu baseline locking | ✅ COMPLETED | 4h | MVP Faza 2 | TASK-012 ✅, TASK-023 ✅ |
| TASK-025 | Standaryzacja flag produktowych i developerskich | ✅ COMPLETED | 1h | MVP Faza 2 | Brak |
| TASK-026 | Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji | ✅ COMPLETED | 1-2h | MVP Faza 2 | Brak |
| TASK-011 | Stworzenie CI dla staging (GHCR) | ✅ COMPLETED | 3h | MVP Faza 3 | Brak |
| TASK-015 | Automatyczne testy Newman w CI | ⏳ PENDING | 2h | MVP Faza 3 | Brak |
| TASK-019 | Migracja produkcyjnego obrazu Docker na Distroless | ⏳ PENDING | 3-4h | MVP Faza 3 | Brak |
| TASK-020 | Sprawdzić zachowanie AI dla nieistniejących filmów/osób | ⏳ PENDING | 2h | MVP Faza 4 | Brak |
| TASK-028 | Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues | ⏳ PENDING | 0.5-1h | MVP Faza 4 | Brak |
| TASK-029 | Uporządkowanie testów według wzorca AAA lub GWT | ⏳ PENDING | 2-3h | MVP Faza 4 | Brak |
| TASK-031 | Kierunek rozwoju wersjonowania opisów AI | ⏳ PENDING | 1-2h | MVP Faza 5 | Powiązane z TASK-012, TASK-024 |
| TASK-032 | Automatyczne tworzenie obsady przy generowaniu filmu | ⏳ PENDING | 3h | MVP Faza 4 | TASK-022 ✅ |
| TASK-033 | Usunięcie modelu Actor i konsolidacja na Person | ⏳ PENDING | 2-3h | MVP Faza 4 | TASK-032, TASK-022 ✅ |
| TASK-034 | Tłumaczenie zasad Cursor (.mdc) i CLAUDE.md na angielski | ✅ COMPLETED | 2-3h | MVP Faza 2 | Brak |
| TASK-040 | Analiza formatu TOON vs JSON dla komunikacji z AI | ⏳ PENDING | 2-3h | MVP Faza 5 | Brak |
| TASK-041 | Dodanie seriali i programów telewizyjnych (DDD approach) | ⏳ PENDING | 30-40h | Pełna wersja | Brak |
| TASK-045 | Integracja TMDb API dla weryfikacji istnienia osób | ✅ COMPLETED | 6-8h (F1) + 3-4h (F2) | MVP Faza 1 | TASK-044 ✅ |
| TASK-046 | Integracja TMDb API dla weryfikacji istnienia seriali i TV Shows | ⏳ PENDING | 8-10h (F1) + 3-4h (F2) | Pełna wersja | TASK-041, TASK-044 ✅, TASK-045 ✅ |
| TASK-002 | Weryfikacja Queue Workers i Horizon | ✅ COMPLETED | 2-3h | MVP Faza 1 | Brak |
| TASK-003 | Implementacja Redis Caching dla Endpointów | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-005 | Weryfikacja i Aktualizacja OpenAPI Spec | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-007 | Feature Flags Hardening | ✅ COMPLETED | 2-3h | MVP Faza 1 | Brak |
| TASK-014 | Usprawnienie linków HATEOAS dla filmów | ✅ COMPLETED | 1-2h | MVP Faza 1 | Brak |
| TASK-016 | Auto-fix błędów PHPStan | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-017 | Rozszerzenie fixera PHPStan o dodatkowe strategie | ✅ COMPLETED | - | MVP Faza 1 | TASK-016 ✅ |
| TASK-021 | Refaktoryzacja FlagController | ✅ COMPLETED | 1h | MVP Faza 1 | Brak |
| TASK-023 | Naprawa niespójnego wyszukiwania (case-insensitive) | ✅ COMPLETED | 1-2h | MVP Faza 2 | TASK-022 ✅ |
| TASK-047 | Refaktoryzacja do wspólnego serwisu weryfikacji | ✅ COMPLETED | 4-6h | MVP Faza 1 | TASK-044 ✅, TASK-045 ✅ |
| TASK-049 | Weryfikacja naprawy problemu phpstan-fixer | ✅ COMPLETED | 4-6h | MVP Faza 1 | Brak |

---

## 🟢 Priorytet Niski

| ID | Tytuł | Status | Czas | Kategoria | Zależności |
|---|---|---|---|---|---|
| TASK-030 | Opracowanie dokumentu o technice testów „trzech linii" | ⏳ PENDING | 1-2h | MVP Faza 5 | TASK-029 |
| TASK-042 | Analiza możliwych rozszerzeń typów i rodzajów | ⏳ PENDING | 4-6h | Roadmap | Brak |
| TASK-004 | Aktualizacja README.md (Symfony → Laravel) | ✅ COMPLETED | - | MVP Faza 1 | Brak |
| TASK-006 | Ulepszenie Postman Collection | ✅ COMPLETED | 1-2h | MVP Faza 1 | Brak |

---

## 📊 Statystyki

### Według Priorytetu
- **🔴 Wysoki:** 12 zadań (wszystkie ✅ COMPLETED)
- **🟡 Średni:** 30 zadań (19 ✅ COMPLETED, 11 ⏳ PENDING)
- **🟢 Niski:** 4 zadania (2 ✅ COMPLETED, 2 ⏳ PENDING)

### Według Statusu
- **✅ COMPLETED:** 33 zadania
- **⏳ PENDING:** 13 zadań
- **🔄 IN_PROGRESS:** 0 zadań

### Według Kategorii
- **MVP Faza 1:** 20 zadań (wszystkie ✅ COMPLETED)
- **MVP Faza 2:** 5 zadań (wszystkie ✅ COMPLETED)
- **MVP Faza 3:** 3 zadania (0 ✅, 3 ⏳ PENDING)
- **MVP Faza 4:** 4 zadania (0 ✅, 4 ⏳ PENDING)
- **MVP Faza 5:** 2 zadania (0 ✅, 2 ⏳ PENDING)
- **Pełna wersja:** 2 zadania (0 ✅, 2 ⏳ PENDING)
- **Roadmap:** 1 zadanie (0 ✅, 1 ⏳ PENDING)

### Łączny Czas
- **✅ Ukończone:** ~150-200h
- **⏳ Pozostałe:** ~60-80h (MVP) + ~40-50h (Pełna wersja) = ~100-130h

---

## 🎯 Rekomendacje

### Następne kroki (MVP Faza 3-5):
1. **TASK-011** - CI dla staging (3h) - automatyzacja deploymentu
2. **TASK-015** - Testy Newman w CI (2h) - automatyczna weryfikacja API
3. **TASK-019** - Migracja Docker na Distroless (3-4h) - bezpieczeństwo
4. **TASK-032** - Automatyczne tworzenie obsady (3h) - odblokowuje TASK-033
5. **TASK-033** - Usunięcie modelu Actor (2-3h) - uporządkowanie kodu
6. **TASK-029** - Uporządkowanie testów (2-3h) - odblokowuje TASK-030
7. **TASK-031** - Dokumentacja wersjonowania (1-2h) - dokumentacja decyzji
8. **TASK-040** - Analiza TOON vs JSON (2-3h) - optymalizacja kosztów

**Łączny czas pozostałych zadań MVP:** ~20-25h

---

**Ostatnia aktualizacja:** 2025-12-16

