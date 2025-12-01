# Priorytetyzacja Zadań dla MVP i POC

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Rekomendacje kolejności realizacji zadań dla MVP (Minimum Viable Product) i POC (Proof of Concept)  
> **Kategoria:** technical

## 🎯 Definicje

### MVP (Minimum Viable Product)
**Cel:** Pierwsza działająca wersja API gotowa do demonstracji i podstawowego użycia.

**Wymagane funkcjonalności:**
- ✅ Podstawowe endpointy API (movies, people, generate, jobs)
- ✅ Generowanie opisów AI (mock lub real)
- ✅ System kolejek (Horizon)
- ✅ Podstawowa walidacja i bezpieczeństwo
- ✅ Dokumentacja API (OpenAPI/Swagger)

### POC (Proof of Concept)
**Cel:** Dowód konceptu - demonstracja kluczowych możliwości systemu.

**Wymagane funkcjonalności:**
- ✅ Działające generowanie AI (real lub mock)
- ✅ Podstawowe endpointy API
- ✅ Demonstracja unikalności treści
- ✅ Podstawowa dokumentacja

---

## 📊 Rekomendowana Kolejność Realizacji

### 🔴 FAZA 1: Fundamenty (MVP Core) - **KRYTYCZNE**

**Czas realizacji:** ~15-20h  
**Cel:** Podstawowa funkcjonalność API działająca end-to-end

#### 1. **TASK-023** - Integracja i naprawa połączenia z OpenAI
- **Status:** 🔄 IN_PROGRESS
- **Priorytet:** 🔴 Wysoki
- **Czas:** 3h
- **Dlaczego:** Fundament - bez tego nie ma generowania AI
- **Blokuje:** Wszystkie zadania związane z AI

#### 2. **TASK-037 Faza 1** - Weryfikacja istnienia filmów/osób (✅ UKOŃCZONE)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Uwaga:** Już zrealizowane, ale ważne dla jakości MVP

#### 3. **TASK-038 Faza 1** - Weryfikacja zgodności danych AI (✅ UKOŃCZONE)
- **Status:** ✅ COMPLETED
- **Priorytet:** 🔴 Wysoki
- **Uwaga:** Już zrealizowane, zapewnia jakość danych

#### 4. **TASK-013** - Konfiguracja dostępu do Horizon
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni → **🔴 Wysoki dla MVP**
- **Czas:** 1-2h
- **Dlaczego:** Horizon jest kluczowy dla monitorowania jobów w MVP
- **Blokuje:** Monitoring i debugowanie w produkcji

#### 5. **TASK-011** - Stworzenie CI dla staging (GHCR)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni → **🔴 Wysoki dla MVP**
- **Czas:** 3h
- **Dlaczego:** Automatyzacja deploymentu jest kluczowa dla MVP
- **Blokuje:** Szybkie iteracje i testy na staging

---

### 🟡 FAZA 2: Stabilność i Jakość (MVP Quality) - **WAŻNE**

**Czas realizacji:** ~10-15h  
**Cel:** Zapewnienie stabilności i jakości dla MVP

#### 6. **TASK-031** - Kierunek rozwoju wersjonowania opisów AI
- **Status:** 🔄 IN_PROGRESS
- **Priorytet:** 🔴 Wysoki
- **Czas:** 1-2h
- **Dlaczego:** Definiuje strategię wersjonowania - ważne dla MVP
- **Zależności:** TASK-012, TASK-024

#### 7. **TASK-024** - Wdrożenie planu baseline locking
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni → **🟡 Średni dla MVP**
- **Czas:** 4h
- **Dlaczego:** Zapewnia spójność danych przy wielokrotnym generowaniu
- **Zależności:** TASK-012, TASK-023

#### 8. **TASK-022** - Endpoint listy osób (List People)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 2-3h
- **Dlaczego:** Uzupełnia podstawowe endpointy API dla MVP
- **Synergia:** TASK-032, TASK-033

#### 9. **TASK-015** - Automatyczne testy Newman w CI
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 2h
- **Dlaczego:** Zapewnia jakość API endpoints w CI/CD
- **Blokuje:** Automatyczna weryfikacja API po zmianach

---

### 🟢 FAZA 3: Rozszerzenia (MVP Enhancements) - **OPCJONALNE**

**Czas realizacji:** ~15-20h  
**Cel:** Dodatkowe funkcjonalności poprawiające UX

#### 10. **TASK-032** - Automatyczne tworzenie obsady przy generowaniu filmu
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 3h
- **Dlaczego:** Wzbogaca dane filmu, ale nie jest krytyczne dla MVP
- **Zależności:** TASK-022

#### 11. **TASK-033** - Usunięcie modelu Actor i konsolidacja na Person
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 2-3h
- **Dlaczego:** Refaktoryzacja - poprawia spójność, ale nie blokuje MVP
- **Zależności:** TASK-032, TASK-022

#### 12. **TASK-025** - Standaryzacja flag produktowych i developerskich
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 1h
- **Dlaczego:** Usprawnia zarządzanie feature flags
- **Niski priorytet:** Nie blokuje MVP

#### 13. **TASK-026** - Zbadanie pól zaufania w odpowiedziach kolejkowanych generacji
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 1-2h
- **Dlaczego:** Analiza - może być odłożona na później

---

### 🔵 FAZA 4: Dokumentacja i Testy (MVP Polish) - **WSPIERAJĄCE**

**Czas realizacji:** ~5-8h  
**Cel:** Poprawa jakości kodu i dokumentacji

#### 14. **TASK-029** - Uporządkowanie testów według wzorca AAA lub GWT
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 2-3h
- **Dlaczego:** Poprawa czytelności testów, ale nie blokuje MVP

#### 15. **TASK-028** - Weryfikacja tagów priorytetu w synchronizacji TASKS -> Issues
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 0.5-1h
- **Dlaczego:** Automatyzacja - pomocne, ale nie krytyczne

#### 16. **TASK-030** - Opracowanie dokumentu o technice testów „trzech linii"
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Czas:** 1-2h
- **Dlaczego:** Dokumentacja - może być odłożona
- **Zależności:** TASK-029

---

### ⚪ FAZA 5: Roadmap (Post-MVP) - **NIE DLA MVP**

**Czas realizacji:** ~35-45h  
**Cel:** Funkcjonalności z roadmapy, nie wymagane dla MVP

#### 17. **TASK-008** - Webhooks System (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Czas:** 8-10h
- **Uwaga:** Z roadmapy, nie dla MVP

#### 18. **TASK-009** - Admin UI (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Czas:** 15-20h
- **Uwaga:** Z roadmapy, nie dla MVP

#### 19. **TASK-010** - Analytics/Monitoring Dashboards (Roadmap)
- **Status:** ⏳ PENDING
- **Priorytet:** 🟢 Niski
- **Czas:** 10-12h
- **Uwaga:** Z roadmapy, nie dla MVP

#### 20. **TASK-018** - Wydzielenie PhpstanFixer jako paczki Composer
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 3-4h
- **Uwaga:** Refaktoryzacja narzędzi, nie dla MVP

#### 21. **TASK-019** - Migracja produkcyjnego obrazu Docker na Distroless
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Czas:** 3-4h
- **Uwaga:** Optymalizacja, nie dla MVP

---

## 📋 Tabela Priorytetyzacji

| # | Task ID | Nazwa | Priorytet | Czas | Faza | Status | Blokuje |
|---|---------|-------|-----------|------|------|--------|---------|
| 1 | TASK-023 | Integracja OpenAI | 🔴 | 3h | 1 | 🔄 | Wszystkie AI |
| 2 | TASK-013 | Konfiguracja Horizon | 🔴 | 1-2h | 1 | ⏳ | Monitoring |
| 3 | TASK-011 | CI dla staging | 🔴 | 3h | 1 | ⏳ | Deployment |
| 4 | TASK-031 | Wersjonowanie opisów | 🔴 | 1-2h | 2 | 🔄 | - |
| 5 | TASK-024 | Baseline locking | 🟡 | 4h | 2 | ⏳ | - |
| 6 | TASK-022 | List People endpoint | 🟡 | 2-3h | 2 | ⏳ | - |
| 7 | TASK-015 | Testy Newman w CI | 🟡 | 2h | 2 | ⏳ | - |
| 8 | TASK-032 | Automatyczna obsada | 🟡 | 3h | 3 | ⏳ | TASK-022 |
| 9 | TASK-033 | Konsolidacja Actor→Person | 🟡 | 2-3h | 3 | ⏳ | TASK-032 |
| 10 | TASK-025 | Standaryzacja flag | 🟡 | 1h | 3 | ⏳ | - |
| 11 | TASK-026 | Pola zaufania | 🟡 | 1-2h | 3 | ⏳ | - |
| 12 | TASK-029 | Uporządkowanie testów | 🟡 | 2-3h | 4 | ⏳ | - |
| 13 | TASK-028 | Weryfikacja tagów | 🟡 | 0.5-1h | 4 | ⏳ | - |
| 14 | TASK-030 | Dokumentacja testów | 🟢 | 1-2h | 4 | ⏳ | TASK-029 |

---

## 🎯 Rekomendacje dla MVP

### Minimum Viable Product (MVP)

**Wymagane zadania (Faza 1 + część Fazy 2):**
1. ✅ TASK-037 Faza 1 (UKOŃCZONE)
2. ✅ TASK-038 Faza 1 (UKOŃCZONE)
3. 🔄 TASK-023 (IN_PROGRESS) - **KRYTYCZNE**
4. ⏳ TASK-013 - **KRYTYCZNE**
5. ⏳ TASK-011 - **KRYTYCZNE**
6. 🔄 TASK-031 - **WAŻNE**
7. ⏳ TASK-024 - **WAŻNE**

**Szacowany czas MVP:** ~15-20h (bez zadań już ukończonych)

**Co MVP powinien mieć:**
- ✅ Działające endpointy API (movies, people, generate, jobs)
- ✅ Generowanie AI (mock lub real)
- ✅ System kolejek (Horizon) z monitoringiem
- ✅ Podstawowa walidacja (hallucination_guard)
- ✅ CI/CD dla staging
- ✅ Dokumentacja API (OpenAPI/Swagger)

---

## 🧪 Rekomendacje dla POC

### Proof of Concept (POC)

**Wymagane zadania (minimalne):**
1. ✅ TASK-037 Faza 1 (UKOŃCZONE)
2. ✅ TASK-038 Faza 1 (UKOŃCZONE)
3. 🔄 TASK-023 (IN_PROGRESS) - **KRYTYCZNE**

**Szacowany czas POC:** ~3-5h (tylko TASK-023)

**Co POC powinien mieć:**
- ✅ Działające generowanie AI (mock lub real)
- ✅ Podstawowe endpointy API
- ✅ Demonstracja unikalności treści
- ✅ Podstawowa dokumentacja

**Uwaga:** POC może działać bez Horizon, CI/CD i innych zaawansowanych funkcji.

---

## 🔄 Zależności i Blokady

### Graf Zależności

```
TASK-023 (OpenAI)
  ↓
TASK-024 (Baseline locking)
  ↓
TASK-031 (Wersjonowanie)

TASK-022 (List People)
  ↓
TASK-032 (Automatyczna obsada)
  ↓
TASK-033 (Konsolidacja Actor→Person)

TASK-029 (Uporządkowanie testów)
  ↓
TASK-030 (Dokumentacja testów)
```

### Blokady

- **TASK-023** blokuje wszystkie zadania związane z AI
- **TASK-013** blokuje monitoring w produkcji
- **TASK-011** blokuje automatyzację deploymentu
- **TASK-022** blokuje TASK-032 i TASK-033

---

## 📊 Podsumowanie

### MVP (Minimum Viable Product)
- **Czas realizacji:** ~15-20h (bez zadań ukończonych)
- **Krytyczne zadania:** TASK-023, TASK-013, TASK-011
- **Status:** 2/7 zadań ukończonych (TASK-037 F1, TASK-038 F1)
- **Blokady:** TASK-023 (IN_PROGRESS)

### POC (Proof of Concept)
- **Czas realizacji:** ~3-5h
- **Krytyczne zadania:** TASK-023
- **Status:** 2/3 zadań ukończonych
- **Blokady:** TASK-023 (IN_PROGRESS)

---

## 🚀 Następne Kroki

1. **Dokończyć TASK-023** - najwyższy priorytet
2. **Zrealizować TASK-013** - konfiguracja Horizon
3. **Zrealizować TASK-011** - CI dla staging
4. **Zrealizować TASK-031** - wersjonowanie opisów
5. **Zrealizować TASK-024** - baseline locking

Po ukończeniu tych zadań, MVP będzie gotowy do demonstracji.

---

**Ostatnia aktualizacja:** 2025-12-01

