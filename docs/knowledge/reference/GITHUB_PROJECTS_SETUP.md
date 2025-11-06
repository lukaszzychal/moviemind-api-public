# 📋 GitHub Projects Setup Guide

Ten przewodnik pokazuje, jak utworzyć GitHub Project dla repozytorium MovieMind API i zorganizować zadania z roadmapy.

## 🎯 Co to jest GitHub Projects?

GitHub Projects to narzędzie do zarządzania zadaniami w stylu Kanban board. Pozwala na:
- Wizualizację postępów zadań
- Organizację Issues i Pull Requests
- Planowanie sprintów i release'ów
- Śledzenie priorytetów

## 🚀 Krok 1: Utworzenie projektu

1. **Przejdź do repozytorium**: https://github.com/lukaszzychal/moviemind-api-public
2. **Kliknij zakładkę "Projects"** (u góry strony)
3. **Kliknij "New project"**
4. **Wybierz szablon**:
   - **"Board"** - klasyczny Kanban board (zalecany)
   - **"Table"** - widok tabeli z filtrowaniem
5. **Wpisz nazwę**: np. `MovieMind Roadmap` lub `MovieMind MVP`
6. **Wybierz widoczność**: Public lub Private
7. **Kliknij "Create project"**

## 📊 Krok 2: Konfiguracja kolumn (Board)

Domyślny szablon ma kolumny:
- **Todo** - zadania do zrobienia
- **In progress** - w trakcie
- **Done** - zakończone

### Zalecana struktura dla MovieMind:

| Kolumna | Opis | Automatyzacja |
|---------|------|---------------|
| **📋 Backlog** | Pomysły i przyszłe zadania | - |
| **🎯 To Do** | Zadania zaplanowane do pracy | - |
| **🚧 In Progress** | Obecnie w pracy | Auto-move gdy Issue jest assigned |
| **👀 In Review** | PR do review | Auto-move gdy PR otwarty |
| **✅ Done** | Zakończone zadania | Auto-move gdy Issue/PR zamknięty |
| **📌 Blocked** | Zablokowane (opcjonalnie) | - |

## 🔗 Krok 3: Powiązanie Issues z projektem

### Opcja A: Z poziomu Issue
1. Otwórz Issue w repozytorium
2. Po prawej stronie znajdź sekcję **"Projects"**
3. Kliknij **"add to project"**
4. Wybierz swój projekt
5. Karta automatycznie pojawi się w kolumnie **"Todo"**

### Opcja B: Z poziomu Project
1. Otwórz swój Project
2. Kliknij **"+ Add item"** lub **"+"**
3. Wybierz **"Issue"**
4. Wybierz istniejące Issue lub utwórz nowe

## 📝 Krok 4: Utworzenie kart na podstawie Roadmapy

Na podstawie roadmapy z README.md możesz utworzyć Issues i dodać je do projektu:

### Przykładowe zadania z roadmapy:

```markdown
## 🏆 Roadmap (z README.md)

- [ ] Admin panel for content management
- [ ] Webhook system for real-time notifications
- [ ] Advanced analytics and metrics
- [ ] Multi-tenant support
- [ ] Content versioning and A/B testing
- [ ] Integration with popular movie databases
```

### Utworzenie Issues dla każdego zadania:

1. **Admin panel**
   - Tytuł: `Add admin panel for content management`
   - Opis: Panel administracyjny do zarządzania filmami, opisami i osobami
   - Labels: `enhancement`, `admin`, `future`
   - Dodaj do projektu

2. **Webhook system**
   - Tytuł: `Implement webhook system for real-time notifications`
   - Opis: System webhooków do powiadamiania o zakończeniu generowania
   - Labels: `enhancement`, `webhooks`, `future`
   - Dodaj do projektu

3. **Analytics**
   - Tytuł: `Add advanced analytics and metrics`
   - Opis: Dashboard z metrykami użycia API, popularności treści
   - Labels: `enhancement`, `analytics`, `future`
   - Dodaj do projektu

4. **Multi-tenant**
   - Tytuł: `Implement multi-tenant support`
   - Opis: Obsługa wielu klientów/organizacji w jednej instancji
   - Labels: `enhancement`, `architecture`, `future`
   - Dodaj do projektu

5. **Content versioning**
   - Tytuł: `Add content versioning and A/B testing`
   - Opis: Historia zmian opisów, testowanie wariantów
   - Labels: `enhancement`, `content`, `future`
   - Dodaj do projektu

6. **Database integration**
   - Tytuł: `Integrate with popular movie databases`
   - Opis: Integracja z TMDb, IMDb API dla dodatkowych danych
   - Labels: `enhancement`, `integration`, `future`
   - Dodaj do projektu

## 🏷️ Krok 5: Utworzenie Labels (etykiet)

Labels pomagają kategoryzować zadania:

1. **Przejdź do Settings → Labels** w repozytorium
2. Utwórz etykiety:

```
bug              (czerwony) - Błędy i problemy
enhancement      (niebieski) - Nowe funkcjonalności
documentation    (zielony) - Dokumentacja
testing          (żółty) - Testy
refactoring      (fioletowy) - Refaktoryzacja
future           (szary) - Przyszłe zadania
priority-high    (czerwony) - Wysoki priorytet
priority-medium  (pomarańczowy) - Średni priorytet
priority-low     (szary) - Niski priorytet
```

## ⚙️ Krok 6: Automatyzacja (opcjonalne)

GitHub Projects obsługuje automatyzację:

1. W projekcie kliknij **"..." → Settings → Workflows**
2. Dodaj automatyzację:

**Przykład 1: Auto-move do "In Progress"**
- Gdy: Issue assigned
- Przenieś do: "In Progress"

**Przykład 2: Auto-move do "Done"**
- Gdy: Issue closed
- Przenieś do: "Done"

**Przykład 3: Auto-move PR do "In Review"**
- Gdy: Pull request opened
- Przenieś do: "In Review"

## 📈 Krok 7: Insights i raporty

Projects oferują:
- **Burndown charts** - wykresy postępów
- **Velocity tracking** - tempo pracy
- **Filtering** - filtrowanie po labels, assignees, milestones

Aby zobaczyć Insights:
1. W projekcie kliknij **"..." → Insights**

## 💡 Przykładowy workflow

1. **Planowanie**:
   - Dodaj zadania z roadmapy do kolumny **"Backlog"**
   - Priorytetyzuj przeciągając karty

2. **Rozpoczęcie pracy**:
   - Przenieś zadanie do **"To Do"**
   - Assign yourself do Issue
   - Automatycznie przenosi się do **"In Progress"**

3. **Code review**:
   - Otwórz Pull Request
   - Automatycznie przenosi się do **"In Review"**

4. **Zakończenie**:
   - Merge PR lub zamknij Issue
   - Automatycznie przenosi się do **"Done"**

## 🔗 Przydatne linki

- [GitHub Projects Documentation](https://docs.github.com/en/issues/planning-and-tracking-with-projects)
- [Project Automation](https://docs.github.com/en/issues/planning-and-tracking-with-projects/automating-your-project)
- [Project Views](https://docs.github.com/en/issues/planning-and-tracking-with-projects/customizing-views-in-your-project)

## 📝 Quick Start Checklist

- [ ] Utworzyć Project (Board)
- [ ] Skonfigurować kolumny
- [ ] Utworzyć Labels
- [ ] Utworzyć Issues z roadmapy
- [ ] Dodać Issues do projektu
- [ ] Skonfigurować automatyzację (opcjonalnie)
- [ ] Dodać link do projektu w README.md

---

**Gotowe!** Twój projekt jest gotowy do zarządzania zadaniami. 🎉

