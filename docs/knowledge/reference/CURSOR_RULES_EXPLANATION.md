# Cursor IDE - Reguły i Konfiguracja

## 📋 Przegląd

Cursor IDE oferuje kilka sposobów konfiguracji reguł i kontekstu dla AI. Poniżej wyjaśnienie różnic i aktualnych praktyk.

---

## 🔧 Pliki Konfiguracyjne Cursor

### 1. `.cursorrules` (Przestarzały, ale nadal działa)

**Status:** ⚠️ Przestarzały, ale nadal wspierany

**Lokalizacja:** Główny katalog projektu (`.cursorrules`)

**Opis:**
- Stary format reguł dla Cursor IDE
- Automatycznie wczytywany przez Cursor
- Jeden plik z wszystkimi regułami
- **Nadal działa**, ale zalecana jest migracja do nowego formatu

**Przykład:**
```
.cursorrules
```

**Zalety:**
- ✅ Prosty - jeden plik
- ✅ Automatycznie wczytywany
- ✅ Działa od razu

**Wady:**
- ❌ Przestarzały format
- ❌ Trudniejszy do zarządzania przy wielu regułach
- ❌ Brak organizacji (wszystko w jednym pliku)

---

### 2. `.cursor/rules/*.mdc` (Nowy, zalecany format)

**Status:** ✅ Nowy, zalecany format

**Lokalizacja:** `.cursor/rules/*.mdc` (każda reguła w osobnym pliku)

**Opis:**
- Nowy format reguł wprowadzony przez Cursor
- Każda reguła w osobnym pliku `.mdc`
- Lepsza organizacja i zarządzanie
- Łatwiejsze utrzymanie

**Struktura:**
```
.cursor/
  └── rules/
      ├── coding-standards.mdc
      ├── testing.mdc
      ├── architecture.mdc
      └── workflow.mdc
```

**Zalety:**
- ✅ Nowoczesny format
- ✅ Lepsza organizacja
- ✅ Łatwiejsze zarządzanie wieloma regułami
- ✅ Możliwość modularyzacji

**Wady:**
- ❌ Wymaga utworzenia struktury katalogów
- ❌ Więcej plików do zarządzania

---

### 3. `CLAUDE.md` (Opcjonalny kontekst)

**Status:** 📄 Opcjonalny plik kontekstu

**Lokalizacja:** Główny katalog projektu (`CLAUDE.md` lub `CLAUDE.local.md`)

**Opis:**
- **NIE jest standardowym plikiem reguł Cursor**
- To opcjonalny plik markdown, który można dołączyć jako kontekst
- W ustawieniach Cursor: "Include CLAUDE.md in context" (domyślnie włączone)
- Używany jako **dodatkowy kontekst** o projekcie, nie jako reguły

**Przeznaczenie:**
- Opis architektury projektu
- Konwencje nazewnictwa
- Struktura kodu
- Technologie i biblioteki
- Wszystko co pomaga AI zrozumieć projekt

**Różnica od `.cursorrules`:**
- `.cursorrules` = **INSTRUKCJE** (co robić, jak działać)
- `CLAUDE.md` = **KONTEKST** (jak działa projekt, co zawiera)

**Przykład zawartości:**
```markdown
# MovieMind API - Kontekst Projektu

## Architektura
- Laravel 12
- PHP 8.2+
- PostgreSQL
- Redis
- Queue: Laravel Horizon

## Struktura
- Controllers: app/Http/Controllers
- Services: app/Services
- Jobs: app/Jobs
```

---

## 🔄 Co używać w tym projekcie?

### ✅ Aktualna konfiguracja: Nowy format `.cursor/rules/*.mdc` (zaimplementowany)

Projekt używa nowego formatu z podzielonymi regułami:

- `.cursor/rules/priorities.mdc` - Priorytety
- `.cursor/rules/testing.mdc` - Test Driven Development
- `.cursor/rules/workflow.mdc` - Workflow przed commitem
- `.cursor/rules/coding-standards.mdc` - Zasady kodowania
- `.cursor/rules/dont-do.mdc` - Co NIE robić
- `.cursor/rules/task-management.mdc` - System zarządzania zadaniami
- `.cursor/rules/checklist.mdc` - Checklist przed commitem
- `.cursor/rules/philosophy.mdc` - Filozofia i kluczowe zasady

**Dodatkowo:**
- `CLAUDE.md` - kontekst projektu (architektura, struktura)

### ⚠️ Stary format `.cursorrules`

Plik `.cursorrules` jest przestarzały i został zastąpiony. Zawiera tylko informację o migracji.

---

## 📝 Rekomendacja dla MovieMind API

**Zalecana struktura:**

```
.cursor/
  └── rules/
      ├── coding-standards.mdc      # Reguły kodowania (SOLID, DRY, etc.)
      ├── testing.mdc               # Reguły testów (TDD)
      ├── workflow.mdc              # Workflow przed commitem
      └── project-rules.mdc         # Specyficzne reguły projektu

CLAUDE.md                           # Kontekst projektu (architektura, struktura)
```

**Lub prostsza wersja:**

```
.cursorrules                        # Wszystkie reguły (przestarzały, ale działa)
CLAUDE.md                           # Kontekst projektu
```

---

## 🔍 Jak to działa w Cursor IDE?

### Ustawienia w Cursor:
1. **Settings → Rules, Memories, Commands**
2. **Project Rules** - wczytuje `.cursor/rules/*.mdc` lub `.cursorrules`
3. **Include CLAUDE.md in context** - wczytuje `CLAUDE.md` jako dodatkowy kontekst

### Priorytety wczytywania:
1. `.cursor/rules/*.mdc` (jeśli istnieje) - nowy format
2. `.cursorrules` (jeśli nie ma `.cursor/rules`) - stary format
3. `CLAUDE.md` (jeśli włączone w ustawieniach) - dodatkowy kontekst

---

## ✅ Aktualna konfiguracja w projekcie

**Obecnie mamy:**
- ✅ `.cursor/rules/*.mdc` - nowy format z podzielonymi regułami (8 modułów)
- ✅ `CLAUDE.md` - kontekst projektu (architektura, struktura)
- ⚠️ `.cursorrules` - przestarzały, zawiera tylko informację o migracji

**Status migracji:**
- ✅ Migracja zakończona - projekt używa nowego formatu
- ✅ Wszystkie reguły przeniesione do `.cursor/rules/*.mdc`
- ✅ `CLAUDE.md` utworzony z kontekstem projektu

---

## 🎯 Podsumowanie

| Plik | Typ | Status | Przeznaczenie |
|------|-----|--------|---------------|
| `.cursorrules` | Reguły | ⚠️ Przestarzały | Instrukcje dla AI |
| `.cursor/rules/*.mdc` | Reguły | ✅ Zalecany | Instrukcje dla AI (nowy format) |
| `CLAUDE.md` | Kontekst | 📄 Opcjonalny | Informacje o projekcie |

**Różnica:**
- **Reguły** (`.cursorrules` / `.cursor/rules/*.mdc`) = **CO i JAK robić**
- **Kontekst** (`CLAUDE.md`) = **JAK działa projekt**

---

## 📚 Źródła

- [Cursor Rules Documentation](https://docs.cursor.com/en/context/rules)
- [Cursor Context Documentation](https://docs.cursor.com/en/context)

