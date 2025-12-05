# Analiza Security Pipeline - Duplikacje i Rekomendacje

> **Data utworzenia:** 2025-01-10  
> **Kontekst:** Analiza duplikacji narzędzi w security-pipeline.yml  
> **Kategoria:** technical

## 🔍 Identyfikacja Duplikacji

### Narzędzia zduplikowane w `security-pipeline.yml`:

1. **GitLeaks** ✅ DUPLIKACJA
   - `security-pipeline.yml`: linia 28-36
   - `code-security-scan.yml`: linia 35-43
   - **Status:** Już uruchamiany w osobnym workflow

2. **Composer Audit** ✅ DUPLIKACJA
   - `security-pipeline.yml`: linia 49-58
   - `code-security-scan.yml`: linia 76 (job: security-audit)
   - `ci.yml`: linia 110-112 (job: security)
   - **Status:** Już uruchamiany w dwóch workflow

3. **PHPStan** ✅ DUPLIKACJA
   - `security-pipeline.yml`: linia 61-68
   - `ci.yml`: linia 119-122 (job: security)
   - **Status:** Już uruchamiany w CI

4. **Trivy Docker Scan** ✅ DUPLIKACJA
   - `security-pipeline.yml`: linia 86-103 (image) + 106-123 (filesystem)
   - `docker-security-scan.yml`: linia 52-112 (pełny scan)
   - **Status:** Już uruchamiany w osobnym workflow

5. **CodeQL** ❌ BRAK (OK - ma własny workflow)

## 💡 Rekomendacje

### Rozwiązanie 1: Security Pipeline jako Agregator + Nowe Narzędzia

**Zalety:**
- Brak duplikacji kodu
- Agregacja wyników z innych workflow
- Możliwość dodania nowych narzędzi
- Centralizacja raportowania

**Struktura:**
```yaml
# security-pipeline.yml - tylko manual trigger dla audytów
on:
  workflow_dispatch:  # Tylko manual trigger
  schedule:
    - cron: '0 3 * * 0'  # Raz w tygodniu (niedziela)

jobs:
  # Agregacja wyników z innych workflow
  aggregate-results:
    # Pobranie wyników z innych workflow
  
  # Nowe narzędzia (nie zduplikowane)
  hadolint:
    # Dockerfile linter
  
  npm-audit:
    # npm dependencies audit
  
  security-headers:
    # API security headers check
```

### Rozwiązanie 2: Usunąć security-pipeline.yml

**Zalety:**
- Zero duplikacji
- Wszystkie narzędzia w dedykowanych workflow

**Wady:**
- Brak centralnego raportowania
- Trudniejsze kompleksowe audyty

## 🛠️ Dodatkowe Narzędzia do Dodania

### 1. Hadolint (Dockerfile Linter)
**Cel:** Sprawdzanie Dockerfile pod kątem best practices
**Lokalizacja:** `docker/php/Dockerfile`

### 2. npm audit
**Cel:** Audyt zależności npm/Node.js
**Lokalizacja:** `package.json`, `api/package.json`

### 3. Security Headers Check
**Cel:** Weryfikacja security headers w API responses
**Metoda:** Test HTTP headers

### 4. OWASP Dependency Check
**Cel:** Rozszerzenie Composer Audit (jeśli potrzebne)

### 5. Laravel Security Checker
**Cel:** Specyficzne dla Laravel luki bezpieczeństwa

## 📊 Porównanie Workflow

| Narzędzie | code-security-scan.yml | ci.yml | docker-security-scan.yml | security-pipeline.yml |
|-----------|------------------------|--------|--------------------------|----------------------|
| GitLeaks | ✅ | ❌ | ❌ | ✅ (DUPLIKACJA) |
| Composer Audit | ✅ | ✅ | ❌ | ✅ (DUPLIKACJA) |
| PHPStan | ❌ | ✅ | ❌ | ✅ (DUPLIKACJA) |
| Trivy Docker | ❌ | ❌ | ✅ | ✅ (DUPLIKACJA) |
| CodeQL | ❌ | ❌ | ❌ | ❌ (OK) |

## ✅ Rekomendowane Działania

1. **Usunąć duplikacje** z `security-pipeline.yml`
2. **Zmienić trigger** na tylko `workflow_dispatch` (manual audits)
3. **Dodać nowe narzędzia:**
   - Hadolint (Dockerfile)
   - npm audit
   - Security Headers Check
4. **Agregować wyniki** z innych workflow zamiast duplikować
5. **Zachować** security-pipeline jako kompleksowy audit tool (manual)

---

**Ostatnia aktualizacja:** 2025-01-10

