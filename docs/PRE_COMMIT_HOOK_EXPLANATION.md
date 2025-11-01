# 🔧 Pre-commit Hook - Jak Działa

## 📋 Odpowiedzi na Pytania

### **1. Czy plik pre-commit jest wysłany na Git?**

**Odpowiedź:** Częściowo ✅

| Plik | W repo? | Lokalizacja | Status |
|------|---------|-------------|--------|
| **`.git/hooks/pre-commit`** | ❌ **NIE** | Lokalny hook (dla Ciebie) | Lokalny |
| **`scripts/pre-commit`** | ✅ **TAK** | Template dla innych devów | W repo |
| **`.pre-commit-config.yaml`** | ✅ **TAK** | Pre-commit framework config | W repo |

**Dlaczego `.git/hooks/pre-commit` NIE jest w repo?**
- Git hooks są **lokalne** - każdy dev ma swoje
- Nie commit'uje się hooków do repo (best practice)
- Zamiast tego: **template** w `scripts/pre-commit`

---

### **2. Czy automatycznie naprawia czy trzeba ręcznie?**

**Odpowiedź:** Automatycznie naprawia (jeśli możliwe) ✅

#### **Laravel Pint (Code Style):**

```bash
# Automatycznie naprawia style:
git commit -m "message"
# → Pint sprawdza style
# → Jeśli błędy → AUTO-FIX
# → Dodaje naprawione pliki do staging
# → Pyta o ponowne commit (po review)
```

**Przykład:**
```bash
🔍 Running pre-commit checks...
🎨 Running Laravel Pint...
❌ Laravel Pint: Code style issues found
Running auto-fix...
✅ Code style auto-fixed. Please review and commit again.
```

**Co robi:**
1. Sprawdza style → błędy znalezione
2. **Auto-fix** - automatycznie naprawia
3. `git add -u` - dodaje naprawione pliki
4. **Blokuje commit** - musisz zreviewować i commit ponownie

---

#### **PHPStan (Static Analysis):**

```bash
# NIE naprawia automatycznie - tylko sprawdza:
git commit -m "message"
# → PHPStan sprawdza błędy
# → Jeśli błędy → BLOCKUJE commit
# → Musisz naprawić ręcznie
```

**Przykład:**
```bash
🔍 Running PHPStan...
❌ PHPStan: Errors found
Please fix PHPStan errors before committing.
[ERROR] Offset 'success' on array... always exists
→ Commit zablokowany
```

**Co robi:**
1. Sprawdza kod → błędy znalezione
2. **Blokuje commit** - musisz naprawić ręcznie
3. **Nie auto-fixuje** - PHPStan nie naprawia błędów logicznych

---

## 🔄 Pełny Flow

### **Scenario 1: Wszystko OK**
```bash
git commit -m "message"
→ ✅ Laravel Pint: Code style OK
→ ✅ PHPStan: No errors found
→ ✅ Commit successful
```

### **Scenario 2: Style Issues (Auto-fix)**
```bash
git commit -m "message"
→ ❌ Laravel Pint: Code style issues found
→ 🛠️ Running auto-fix...
→ ✅ Code style auto-fixed
→ ⚠️ Please review and commit again
→ (Musisz zreviewować i commit ponownie)
```

### **Scenario 3: PHPStan Errors (Manual fix)**
```bash
git commit -m "message"
→ ✅ Laravel Pint: Code style OK
→ ❌ PHPStan: Errors found
→ 🛛 Commit blocked
→ (Musisz naprawić błędy ręcznie)
```

---

## 📂 Struktura Plików

```
moviemind-api-public/
├── .git/hooks/pre-commit          # ❌ Lokalny (NIE w repo)
├── scripts/pre-commit              # ✅ Template (W repo)
├── .pre-commit-config.yaml        # ✅ Config (W repo)
└── scripts/setup-pre-commit.sh    # ✅ Setup script (W repo)
```

### **Instalacja dla Nowych Devów:**

```bash
# 1. Sklonuj repo
git clone ...

# 2. Zainstaluj hook (kopiuje scripts/pre-commit do .git/hooks/)
cp scripts/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

# LUB użyj setup script:
./scripts/setup-pre-commit.sh
```

---

## ⚙️ Konfiguracja

### **`.git/hooks/pre-commit`** (lokalny)

**Co robi:**
1. Sprawdza czy są pliki PHP w staging
2. Uruchamia `vendor/bin/pint --test`
3. Jeśli błędy → auto-fix (`vendor/bin/pint`)
4. Uruchamia `vendor/bin/phpstan analyse`
5. Jeśli błędy → blockuje commit

**Lokalizacja:** `.git/hooks/pre-commit` (nie w repo)

---

### **`scripts/pre-commit`** (template w repo)

**Co to jest:**
- Template hooka dla innych deweloperów
- Kopiowany do `.git/hooks/pre-commit` podczas setupu
- Wersjonowany w repo

**Lokalizacja:** `scripts/pre-commit` (w repo)

---

## 🎯 Podsumowanie

| Pytanie | Odpowiedź |
|---------|-----------|
| **Czy pre-commit w repo?** | Template ✅ TAK, Hook ❌ NIE (lokalny) |
| **Automatycznie naprawia?** | Pint ✅ TAK, PHPStan ❌ NIE (tylko sprawdza) |
| **Co jeśli błędy?** | Pint auto-fixuje, PHPStan blokuje commit |

---

## 💡 Jak To Działa w Praktyce

```bash
# 1. Edytujesz plik
vim app/Services/MyService.php

# 2. Dodajesz do staging
git add app/Services/MyService.php

# 3. Commit
git commit -m "Add new service"

# 4. Pre-commit hook automatycznie:
#    - Sprawdza style (Pint)
#    - Jeśli błędy → auto-fixuje
#    - Sprawdza PHPStan
#    - Jeśli PHPStan errors → blokuje
#    - Jeśli wszystko OK → commit przechodzi
```

---

**Ostatnia aktualizacja:** 2025-11-01

