# 📋 Jak Przeglądać Zadania z TASKS.md w Cursor IDE

**Data utworzenia:** 2025-11-04  
**Status:** ✅ Przewodnik

---

## ❓ **Czy Cursor ma dedykowany panel do zadań?**

**Odpowiedź:** ❌ **NIE** - Cursor AI nie ma dedykowanego panelu do wyświetlania zadań z pliku markdown.

---

## ✅ **Alternatywne Sposoby Przeglądania Zadań**

### **1. Otwórz plik TASKS.md bezpośrednio**

**Sposób:**
- Otwórz plik `docs/issue/TASKS.md` w edytorze
- Użyj `Cmd+P` (Mac) lub `Ctrl+P` (Windows/Linux) i wpisz `TASKS.md`
- Plik otworzy się w głównym edytorze z pełnym formatowaniem markdown

**Korzyści:**
- ✅ Pełne formatowanie markdown
- ✅ Kolorowa składnia
- ✅ Możliwość edycji
- ✅ Wbudowane wyszukiwanie (`Cmd+F` / `Ctrl+F`)

---

### **2. Użyj Outline View (Podgląd struktury)**

**Sposób:**
1. Otwórz plik `TASKS.md`
2. Kliknij ikonę **Outline** w prawym górnym rogu (lub `Cmd+Shift+O` / `Ctrl+Shift+O`)
3. Zobacz strukturę dokumentu (nagłówki, sekcje)

**Korzyści:**
- ✅ Szybka nawigacja po sekcjach
- ✅ Widok struktury dokumentu
- ✅ Przejście do sekcji jednym kliknięciem

---

### **3. Użyj Command Palette do wyszukiwania**

**Sposób:**
1. Otwórz Command Palette (`Cmd+Shift+P` / `Ctrl+Shift+P`)
2. Wpisz `@TASKS.md` lub `@docs/issue/TASKS.md`
3. Wybierz plik z listy

**Korzyści:**
- ✅ Szybkie otwieranie pliku
- ✅ Działa z innych plików (nie musisz otwierać pliku ręcznie)

---

### **4. Poproś AI o wyświetlenie zadań**

**Sposób:**
W chat Cursor AI możesz zapytać:
- `"pokaż mi zadania z TASKS.md"`
- `"jakie zadania są PENDING?"`
- `"pokaz zadania w tej iteracji"`
- `"pokaz następne zadanie"`

**Korzyści:**
- ✅ AI automatycznie przeczyta plik
- ✅ Wyświetli zadania w czytelnej formie
- ✅ Może filtrować (PENDING, IN_PROGRESS, COMPLETED)
- ✅ Może pokazać statystyki

**Przykład:**
```
Użytkownik: "pokaż mi zadania z TASKS.md"
AI: [wyświetla listę zadań z pliku]
```

---

### **5. Użyj Markdown Preview**

**Sposób:**
1. Otwórz plik `TASKS.md`
2. Kliknij ikonę **Preview** w prawym górnym rogu (lub `Cmd+Shift+V` / `Ctrl+Shift+V`)
3. Zobacz sformatowany widok markdown

**Korzyści:**
- ✅ Ładne formatowanie
- ✅ Czytelne nagłówki
- ✅ Kolorowe znaczniki statusu (✅, ⏳, 🔄)
- ✅ Możliwość side-by-side (edytor + preview)

---

### **6. Stwórz skrót/alias w Cursor**

**Sposób:**
1. Otwórz Command Palette (`Cmd+Shift+P`)
2. Wpisz `Preferences: Open Keyboard Shortcuts`
3. Dodaj skrót dla otwierania `docs/issue/TASKS.md`

**Przykład skrótu:**
```json
{
  "key": "cmd+shift+t",
  "command": "workbench.action.files.openFile",
  "args": ["docs/issue/TASKS.md"]
}
```

**Korzyści:**
- ✅ Szybki dostęp do zadań
- ✅ Jeden skrót klawiszowy

---

### **7. Użyj File Explorer z filtrem**

**Sposób:**
1. Otwórz File Explorer (`Cmd+Shift+E` / `Ctrl+Shift+E`)
2. Przejdź do `docs/issue/`
3. Kliknij na `TASKS.md`

**Korzyści:**
- ✅ Widok struktury projektu
- ✅ Łatwe nawigowanie

---

## 🤖 **Rekomendowane Podejście: AI Chat**

**Najlepszy sposób do pracy z zadaniami w Cursor:**

### **Komendy dla AI:**

1. **Wyświetl wszystkie zadania:**
   ```
   "pokaż mi zadania z TASKS.md"
   ```

2. **Filtruj zadania:**
   ```
   "pokaż zadania PENDING z TASKS.md"
   "pokaż zadania IN_PROGRESS"
   "pokaż zadania COMPLETED z dzisiaj"
   ```

3. **Znajdź konkretne zadanie:**
   ```
   "pokaż TASK-007 z TASKS.md"
   "znajdź zadanie o Feature Flags"
   ```

4. **Statystyki:**
   ```
   "pokaz statystyki zadań z TASKS.md"
   "ile zadań jest PENDING?"
   ```

5. **Następne zadanie:**
   ```
   "pokaz następne zadanie do wykonania"
   "jaki jest priorytet następnego zadania?"
   ```

6. **Wykonanie zadania:**
   ```
   "wykonaj następne zadanie z TASKS.md"
   "run next task"
   ```

---

## 📊 **Przykład: Jak AI Wyświetla Zadania**

Gdy poprosisz AI o zadania, zobaczysz:

```
## 📋 Zadania z TASKS.md

### ⏳ PENDING (10 zadań)

1. **TASK-004** - Aktualizacja README.md (Symfony → Laravel)
   - Priorytet: 🟢 Niski
   - Szacowany czas: 1 godzina

2. **TASK-007** - Feature Flags Hardening
   - Priorytet: 🟡 Średni
   - Szacowany czas: 2-3 godziny

... (więcej zadań)

### ✅ COMPLETED (5 zadań)

1. **TASK-011** - Rate Limiting dla Jobs Endpoint
   - Czas wykonania: 10m 0s
   - Wykonane przez: AI

... (więcej zadań)
```

---

## 🔧 **Rozszerzenia VS Code (działa w Cursor)**

Jeśli chcesz więcej funkcji, możesz zainstalować rozszerzenia VS Code (Cursor jest oparty na VS Code):

### **1. Markdown All in One**
- Lepsze formatowanie markdown
- Spis treści
- Podgląd

### **2. Todo Tree**
- Wyszukuje TODO/FIXME w kodzie
- Może być dostosowany do TASKS.md

### **3. Markdown Preview Enhanced**
- Zaawansowany podgląd markdown
- Eksport do PDF/HTML

---

## 💡 **Rekomendacja**

**Najlepsze podejście:**
1. ✅ **Otwórz plik TASKS.md** w edytorze (szybki dostęp)
2. ✅ **Użyj AI Chat** do wyszukiwania i filtrowania zadań
3. ✅ **Użyj Outline View** do nawigacji po sekcjach
4. ✅ **Poproś AI o wykonanie zadania** - AI automatycznie zaktualizuje plik

**Przykład workflow:**
```
1. "pokaż następne zadanie" → AI wyświetla TASK-007
2. "wykonaj TASK-007" → AI wykonuje zadanie i aktualizuje plik
3. "pokaz statystyki" → AI wyświetla zaktualizowane statystyki
```

---

## 📚 **Dodatkowe Zasoby**

- [Cursor Documentation](https://cursor.sh/docs)
- [VS Code Markdown Guide](https://code.visualstudio.com/docs/languages/markdown)
- [TASKS.md](../issue/TASKS.md) - Główny plik z zadaniami

---

**Ostatnia aktualizacja:** 2025-11-04

