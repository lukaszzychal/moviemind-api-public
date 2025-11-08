# 📋 Issues - Dokumentacja Zadań i Refaktoryzacji

Ten katalog zawiera dokumentację zadań, refaktoryzacji i zmian w projekcie.

---

## 📁 **Struktura**

### **Główne pliki:**
- **[TASKS.md](./TASKS.md)** - Główny backlog zadań/issues (⚠️ **ZACZYNAJ OD TEGO PLIKU**)
- **[TASK_TEMPLATE.md](./TASK_TEMPLATE.md)** - Szablon do tworzenia nowych zadań
- **[REFACTOR_CONTROLLERS_SOLID.md](./REFACTOR_CONTROLLERS_SOLID.md)** - Szczegółowy opis refaktoryzacji kontrolerów

### **Szczegółowe opisy zadań:**
- `REFACTOR_CONTROLLERS_SOLID.md` - Refaktoryzacja kontrolerów API zgodnie z SOLID

---

## 🚀 **Jak używać z AI Agentem**

### **Dla AI Agenta:**
1. **Przeczytaj `TASKS.md`** - znajdź zadanie ze statusem `⏳ PENDING`
2. **Zmień status na `🔄 IN_PROGRESS`** - zaznacz że zaczynasz pracę
3. **Przeczytaj szczegóły zadania:**
   - Jeśli jest link do szczegółowego opisu, przeczytaj ten plik
   - Jeśli opis jest bezpośrednio w `TASKS.md`, użyj go
4. **Wykonaj zadanie** - implementuj zgodnie z opisem
5. **Po zakończeniu:**
   - Zmień status na `✅ COMPLETED`
   - Przenieś zadanie do sekcji "Zakończone Zadania"
   - Zaktualizuj datę "Ostatnia aktualizacja"
   - Dodaj notatkę o zakończeniu (opcjonalnie)

### **Dla użytkownika:**
1. **Dodaj nowe zadanie:**
   - Otwórz `TASKS.md`
   - Dodaj zadanie do sekcji "Aktywne Zadania" (PENDING)
   - Użyj szablonu z `TASK_TEMPLATE.md`
2. **Jeśli potrzebujesz szczegółowego opisu:**
   - Stwórz nowy plik w `docs/issue/` (np. `TASK_XXX_DESCRIPTION.md`)
   - Dodaj link do tego pliku w `TASKS.md`
3. **Agent AI automatycznie:**
   - Znajdzie zadanie w `TASKS.md`
   - Przeczyta szczegóły
   - Wykona zadanie
   - Zaktualizuje status

---

## 📝 **Format Dokumentów**

### **TASKS.md:**
- Główny backlog zadań
- Statusy: `⏳ PENDING`, `🔄 IN_PROGRESS`, `✅ COMPLETED`, `❌ CANCELLED`
- Priorytety: `🔴 Wysoki`, `🟡 Średni`, `🟢 Niski`
- Linki do szczegółowych opisów (jeśli dostępne)

### **Szczegółowe opisy zadań:**
- Pełny opis zadania
- Problemy do rozwiązania
- Proponowane zmiany
- Plan implementacji
- Korzyści
- Checklist

---

## 🎯 **Status Dokumentów**

- ⏳ **PENDING** - Zadanie oczekuje na wykonanie
- 🔄 **IN_PROGRESS** - Zadanie w trakcie wykonywania
- ✅ **COMPLETED** - Zadanie zakończone
- ❌ **CANCELLED** - Zadanie anulowane

---

## 📊 **Przykład użycia**

### **1. Użytkownik dodaje zadanie:**

```markdown
#### `TASK-002` - Dodanie Rate Limiting
- **Status:** ⏳ PENDING
- **Priorytet:** 🟡 Średni
- **Opis:** Implementacja rate limiting dla API
- **Szczegóły:** [docs/issue/RATE_LIMITING.md](./RATE_LIMITING.md)
```

### **2. AI Agent znajduje zadanie:**

- Czyta `TASKS.md` → znajduje `TASK-002` ze statusem `⏳ PENDING`
- Czyta szczegóły z `RATE_LIMITING.md`
- Zmienia status na `🔄 IN_PROGRESS`

### **3. AI Agent wykonuje zadanie:**

- Implementuje rate limiting
- Testuje implementację
- Aktualizuje dokumentację

### **4. AI Agent kończy zadanie:**

- Zmienia status na `✅ COMPLETED`
- Przenosi do sekcji "Zakończone Zadania"
- Aktualizuje datę

---

## 💡 **Wskazówki**

1. **Zawsze zaczynaj od `TASKS.md`** - to główny plik zadań
2. **Używaj szablonu** - `TASK_TEMPLATE.md` zawiera wszystkie potrzebne pola
3. **Dziel złożone zadania** - używaj podzadań dla większych tasków
4. **Aktualizuj statusy** - pomaga śledzić postęp
5. **Dodawaj szczegóły** - im więcej informacji, tym lepiej AI zrozumie zadanie

---

**Ostatnia aktualizacja:** 2025-01-27
