# 📊 Analiza CI/CD Workflows - MovieMind API

## 🔍 Obecne Workflows

### 1. `ci.yml` - Główny CI Pipeline
**Status:** ✅ Dobry, wymaga optymalizacji

**Zawartość:**
- ✅ `test` job: Testy PHP 8.2, 8.3, 8.4 (matrix) - 3 równoległe joby
- ✅ `security` job: Composer audit, Pint, PHPStan, GitLeaks
- ✅ `docker-build` job: Build i push do GHCR

**Problemy:**
- ❌ Brak `needs:` - joby uruchamiają się równolegle (docker-build nie czeka na testy)
- ❌ Brak cache w `security` job
- ❌ Duplikacja GitLeaks z `code-security-scan.yml`
- ❌ Docker build uruchamia się nawet gdy testy fail

**Czas wykonania:** ~60-90s (równolegle)

---

### 2. `code-security-scan.yml` - Security Scanning
**Status:** ⚠️ Częściowo redundantny

**Zawartość:**
- ✅ GitLeaks scan (z pełną historią - `fetch-depth: 0`)
- ✅ Composer security audit

**Problemy:**
- ⚠️ GitLeaks jest też w `ci.yml` → duplikacja
- ⚠️ Composer audit jest też w `ci.yml` → duplikacja
- ✅ Ale ma `fetch-depth: 0` dla lepszego GitLeaks scan

**Trigger:**
- Push, PR, schedule (codziennie 2 AM)

**Czas wykonania:** ~20-30s

---

### 3. `docker-security-scan.yml` - Docker Security
**Status:** ✅ Dobry

**Zawartość:**
- ✅ Build Docker image
- ✅ Trivy container scan (SARIF)
- ✅ Trivy filesystem scan
- ✅ Upload do GitHub Security

**Problemy:**
- ⚠️ Buduje obraz za każdym razem (może użyć z `docker-build` job)

**Trigger:**
- Push, PR, schedule (tygodniowo), workflow_dispatch

**Czas wykonania:** ~90-120s

---

### 4. `release.yml` - Release Management
**Status:** ✅ Kompleksowy, można ulepszyć

**Zawartość:**
- ✅ Testy przed release
- ✅ Quality checks (Pint)
- ✅ Generowanie changelog
- ✅ Tworzenie GitHub Release
- ✅ Build i push Docker
- ✅ Deploy do produkcji (SSH)
- ✅ Update composer.json version

**Problemy:**
- ⚠️ Uruchamia testy (powinny już przejść w CI)
- ⚠️ `working-directory` missing w niektórych krokach
- ✅ Ma deploy do production

**Trigger:**
- Push tags `v*`

---

## ❌ Główne Problemy

### 1. **Duplikacja Security Checks**

| Check | ci.yml | code-security-scan.yml | docker-security-scan.yml |
|-------|--------|------------------------|--------------------------|
| GitLeaks | ✅ | ✅ | ❌ |
| Composer Audit | ✅ | ✅ | ❌ |
| Trivy | ❌ | ❌ | ✅ |

**Rozwiązanie:** Konsolidacja lub specjalizacja

### 2. **Brak Dependencies**

```yaml
# Obecne (wszystko równolegle):
test ─┐
      ├→ (wszystko równolegle)
security ─┐
docker-build ─┘

# Powinno być:
test ──┐
       ├→ docker-build
security ─┘
```

**Problem:** Docker build może się wykonać nawet gdy testy fail

### 3. **Brak Cache w Security Job**

Security job nie używa cache dla Composer, więc pobiera dependencies za każdym razem.

### 4. **Docker Build Zawsze**

Docker build uruchamia się na każdym push/PR, nawet gdy nie jest potrzebny.

---

## ✅ Proponowane Rozwiązania

### **Rozwiązanie A: Optymalizacja Obecnego (Zalecane)**

#### Krok 1: Zoptymalizuj `ci.yml`

```yaml
jobs:
  test:
    # ... obecne kroki ...
  
  lint:  # ← Rozdziel security na lint i security
    name: Code Quality
    runs-on: ubuntu-latest
    steps:
      # Pint, PHPStan (szybkie)
  
  security:
    name: Security Quick Check
    runs-on: ubuntu-latest
    steps:
      # Composer audit (szybki)
      # Usuń GitLeaks (jest w code-security-scan)
  
  docker-build:
    name: Docker Build
    needs: [test, lint, security]  # ← DODAJ DEPENDENCY
    if: github.event_name == 'push'  # ← Tylko na push
    # ... obecne kroki ...
```

#### Krok 2: Usuń Duplikacje

- **GitLeaks:** Usuń z `ci.yml`, zostaw w `code-security-scan.yml` (ma `fetch-depth: 0`)
- **Composer Audit:** Zostaw w obu, ale różne częstotliwości:
  - `ci.yml`: Szybki check (bez --no-dev)
  - `code-security-scan.yml`: Pełny audit (z --no-dev)

#### Krok 3: Dodaj Cache

```yaml
# W security job
- name: Cache Composer packages
  uses: actions/cache@v3
  with:
    path: api/vendor
    key: ${{ runner.os }}-php-8.3-${{ hashFiles('api/composer.lock') }}
```

---

### **Rozwiązanie B: Podział na Workflows (Dla Większych Projektów)**

#### `ci-fast.yml` - Szybkie Testy
```yaml
# Tylko testy, szybki feedback
jobs:
  test:
    matrix: php-versions
  lint:
    # Pint, PHPStan
```

#### `ci-full.yml` - Pełny CI
```yaml
# Wszystkie checks
jobs:
  test:
  lint:
  security:
  docker-build:
    needs: [test, lint]
```

#### `security.yml` - Security Scanning
```yaml
# Wszystkie security checks razem
jobs:
  composer-audit:
  gitleaks:
  trivy-container:
  trivy-fs:
```

---

### **Rozwiązanie C: Reusable Workflows (Enterprise Pattern)**

```yaml
# .github/workflows/reusable/test.yml
name: Test Workflow
on:
  workflow_call:
    inputs:
      php-version:
        required: true

jobs:
  test:
    # ... test logic
```

```yaml
# .github/workflows/ci.yml
jobs:
  test:
    uses: ./.github/workflows/reusable/test.yml
    with:
      php-version: '8.3'
```

---

## 🎯 Konkretne Rekomendacje

### Priorytet 1: Napraw Dependencies

**Dodaj do `ci.yml`:**

```yaml
docker-build:
  needs: [test, security]  # ← Czeka na sukces
  if: github.event_name == 'push'  # ← Tylko push, nie PR
```

**Efekt:**
- Docker build tylko po sukcesie testów
- Nie buduje na PR (szybsze)
- Oszczędność zasobów

### Priorytet 2: Dodaj Cache do Security

**Dodaj do `security` job w `ci.yml`:**

```yaml
- name: Cache Composer packages
  uses: actions/cache@v3
  with:
    path: api/vendor
    key: ${{ runner.os }}-php-8.3-${{ hashFiles('api/composer.lock') }}
```

**Efekt:**
- ~10-15s oszczędności na każdym uruchomieniu
- Mniej obciążenia GitHub Actions

### Priorytet 3: Usuń Duplikację GitLeaks

**Opcja A: Usuń z `ci.yml`** (Zalecane)
- GitLeaks w `code-security-scan.yml` ma `fetch-depth: 0` (lepsze)
- W `ci.yml` jest szybki check bez historii

**Opcja B: Zostaw oba**
- `ci.yml`: Szybki check (tylko staged changes)
- `code-security-scan.yml`: Pełny scan (pełna historia)

### Priorytet 4: Optymalizuj Docker Build

**Dodaj warunek:**

```yaml
docker-build:
  if: |
    github.event_name == 'push' && 
    (github.ref == 'refs/heads/main' || startsWith(github.ref, 'refs/tags/'))
```

**Lub użyj obrazu z `docker-security-scan`:**

```yaml
# W docker-security-scan.yml - użyj obrazu z ci.yml
- name: Use built image from CI
  if: github.event_name == 'push'
  uses: docker/build-push-action@v6
  # ... użyj cache z ci.yml
```

---

## 📈 Porównanie: Obecne vs Optymalizowane

| Aspekt | Obecne | Po Optymalizacji |
|--------|--------|------------------|
| **Czas CI (push)** | ~90s | ~70s |
| **Czas CI (PR)** | ~90s | ~60s (bez Docker build) |
| **Docker Build** | Zawsze | Tylko po sukcesie testów |
| **Duplikacja** | GitLeaks x2 | Każde narzędzie raz |
| **Cache Security** | ❌ Brak | ✅ ~10-15s oszczędności |
| **Feedback Time** | Wszystko naraz | Szybkie testy pierwsze |

---

## 🔄 Proponowany Flow

### Obecny Flow (Równoległy)
```
Push/PR
  ├─ test (8.2, 8.3, 8.4) ─┐
  ├─ security ──────────────┤ → Wszystko równolegle
  └─ docker-build ──────────┘
```

### Zalecany Flow (Sequential Gates)
```
Push/PR
  ├─ test (8.2, 8.3, 8.4) ─┐
  └─ security ─────────────┘
        │
        ↓ (tylko po sukcesie)
  docker-build (tylko na push)
```

### Idealny Flow (Parallel + Gates)
```
Push/PR
  ├─ test (8.2) ──┐
  ├─ test (8.3) ──┤
  ├─ test (8.4) ──┘
  │
  └─ security ───┐
                 ├─→ docker-build (tylko push)
                 └─→ docker-security-scan (schedule)
```

---

## 🎓 Best Practices Implementowane

✅ **Co już jest dobrze:**
- Matrix dla wielu wersji PHP
- Cache dla Composer (w test job)
- Security scanning (Trivy, GitLeaks, PHPStan)
- Artifacts dla raportów
- GitHub Security integration (SARIF)
- Docker build z cache
- Release workflow z changelog

⚠️ **Co można poprawić:**
- Dependencies między jobami
- Eliminacja duplikacji
- Cache w security job
- Warunki dla Docker build
- Smoke tests dla Docker
- Fail-fast strategy

---

## 💡 Alternatywne Wzorce

### Pattern 1: "Pipeline as Code" (Obecny)
```
Wszystkie joby w jednym workflow
```
✅ Prosty  
⚠️ Może być wolny

### Pattern 2: "Separated Concerns" (Zalecany)
```
ci.yml → testy + lint
security.yml → security checks
docker.yml → docker operations
deploy.yml → deployment
```
✅ Modułowy  
✅ Łatwy w utrzymaniu  
✅ Różne częstotliwości

### Pattern 3: "Reusable Workflows"
```
reusable/test.yml
reusable/security.yml
ci.yml → używa reusable
```
✅ DRY principle  
✅ Łatwy w utrzymaniu  
⚠️ Większa złożoność

---

## 📝 Rekomendacja Końcowa

### Dla Twojego Projektu (Średni rozmiar):

**1. Zoptymalizuj `ci.yml`** (Priorytet 1)
- Dodaj `needs: [test, security]` do `docker-build`
- Dodaj cache do `security` job
- Usuń GitLeaks (zostaw w code-security-scan)
- Dodaj `if: github.event_name == 'push'` do docker-build

**2. Przemianuj `code-security-scan.yml` → `security.yml`** (Priorytet 2)
- Kompleksowy security workflow
- Schedule codziennie
- Wszystkie security checks razem

**3. Uprość `docker-security-scan.yml`** (Priorytet 3)
- Tylko Docker scanning
- Schedule tygodniowo
- Może użyć obrazu z ci.yml jeśli dostępny

**4. Ulepsz `release.yml`** (Opcjonalnie)
- Usuń testy (powinny już przejść)
- Dodaj `working-directory` gdzie brakuje
- Upewnij się że wszystkie kroki mają working-directory

---

## 🚀 Quick Wins (Można zrobić teraz)

### 1. Dodaj Dependency (2 min)
```yaml
# W ci.yml
docker-build:
  needs: [test, security]
```

### 2. Dodaj Cache (2 min)
```yaml
# W security job
- name: Cache Composer
  uses: actions/cache@v3
  with:
    path: api/vendor
    key: ${{ runner.os }}-php-8.3-${{ hashFiles('api/composer.lock') }}
```

### 3. Usuń GitLeaks z ci.yml (1 min)
```yaml
# Usuń ten krok z security job:
- name: GitLeaks (secrets scan)
```

### 4. Dodaj warunek do Docker Build (1 min)
```yaml
docker-build:
  if: github.event_name == 'push'
```

**Łączny czas:** ~6 minut  
**Oszczędność:** ~20-30s na każdym CI + lepszy flow

---

## 📚 Dodatkowe Materiały

- [GitHub Actions Best Practices](https://docs.github.com/en/actions/learn-github-actions/best-practices)
- [Dependency Management](https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions#jobsjob_idneeds)
- [Caching Dependencies](https://docs.github.com/en/actions/using-workflows/caching-dependencies-to-speed-up-workflows)

---

## ✅ Checklist Implementacji

- [ ] Dodaj `needs:` do docker-build
- [ ] Dodaj cache do security job
- [ ] Usuń GitLeaks z ci.yml
- [ ] Dodaj warunek `if: push` do docker-build
- [ ] Przetestuj workflow na test branch
- [ ] Monitoruj czas wykonania przed/po
- [ ] Zaktualizuj dokumentację
