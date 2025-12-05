# Organizacja Security Workflows

> **Data utworzenia:** 2025-01-10  
> **Kontekst:** Analiza i organizacja security workflows bez duplikacji  
> **Kategoria:** technical

## 🎯 Cel

Zorganizowanie security workflows tak, aby:
- Brak duplikacji narzędzi
- Każde narzędzie w dedykowanym workflow
- Security-pipeline jako agregator nowych narzędzi i wyników

## 📊 Mapa Security Workflows

### 1. `code-security-scan.yml`
**Trigger:** PR, Push, Schedule (daily 2:00 UTC)

**Narzędzia:**
- ✅ **GitLeaks** - Secret detection
- ✅ **Composer Audit** - PHP dependencies

**Cel:** Podstawowe security checks na każdym PR/commit

---

### 2. `ci.yml`
**Trigger:** PR, Push

**Narzędzia:**
- ✅ **Composer Audit** - PHP dependencies (w job: security)
- ✅ **PHPStan** - Static analysis (w job: security)
- ✅ **Pint** - Code formatting (w job: security)

**Cel:** CI pipeline z security checks

---

### 3. `docker-security-scan.yml`
**Trigger:** PR, Push, Schedule (weekly Monday 2:00 UTC)

**Narzędzia:**
- ✅ **Trivy** - Docker image security scan
- ✅ **Trivy** - Filesystem security scan

**Cel:** Security scanning dla Docker containers

---

### 4. `codeql.yml`
**Trigger:** PR, Push, Schedule (weekly Monday 2:21 UTC)

**Narzędzia:**
- ✅ **CodeQL** - Advanced SAST (Static Application Security Testing)

**Cel:** Zaawansowana analiza statyczna kodu

**Języki:**
- Actions
- JavaScript/TypeScript
- Python

---

### 5. `security-pipeline.yml` (Refaktoryzowany)
**Trigger:** `workflow_dispatch` (manual) + Schedule (weekly Sunday 3:00 UTC)

**Narzędzia (TYLKO NOWE - BEZ DUPLIKACJI):**
- ✅ **Hadolint** - Dockerfile security linter
- ✅ **npm audit** - Node.js dependencies audit
- ✅ **Security Headers Check** - API security headers configuration
- ✅ **Laravel Security Checker** - Framework-specific security checks

**Agregacja wyników:**
- Linkuje do wyników z innych workflow
- Generuje kompleksowy raport

**Cel:** Kompleksowe audyty bezpieczeństwa (manual/weekly)

---

## ✅ Podział Odpowiedzialności

| Narzędzie | Workflow | Trigger | Cel |
|-----------|----------|---------|-----|
| GitLeaks | `code-security-scan.yml` | PR/Push/Daily | Secret detection |
| Composer Audit | `code-security-scan.yml`, `ci.yml` | PR/Push | Dependency audit |
| PHPStan | `ci.yml` | PR/Push | Static analysis |
| Trivy | `docker-security-scan.yml` | PR/Push/Weekly | Container security |
| CodeQL | `codeql.yml` | PR/Push/Weekly | Advanced SAST |
| Hadolint | `security-pipeline.yml` | Manual/Weekly | Dockerfile linter |
| npm audit | `security-pipeline.yml` | Manual/Weekly | Node.js dependencies |
| Security Headers | `security-pipeline.yml` | Manual/Weekly | API headers check |
| Laravel Checker | `security-pipeline.yml` | Manual/Weekly | Framework security |

## 🚫 Usunięte Duplikacje

**Z `security-pipeline.yml` usunięto:**
- ❌ GitLeaks (już w `code-security-scan.yml`)
- ❌ Composer Audit (już w `code-security-scan.yml` i `ci.yml`)
- ❌ PHPStan (już w `ci.yml`)
- ❌ Trivy (już w `docker-security-scan.yml`)

## ✅ Zalety Nowego Podejścia

1. **Brak duplikacji** - każde narzędzie w jednym miejscu
2. **Szybsze workflow** - mniej redundantnych skanów
3. **Nowe narzędzia** - Hadolint, npm audit, Security Headers
4. **Agregacja wyników** - security-pipeline łączy wyniki bez duplikacji
5. **Manual trigger** - kompleksowe audyty tylko gdy potrzebne

## 🔄 Workflow Dedykowane vs Kompleksowe

### Dedykowane Workflow (Automatic)
- Szybkie, specjalistyczne
- Uruchamiane na każdym PR/commit
- `code-security-scan.yml`, `ci.yml`, `docker-security-scan.yml`, `codeql.yml`

### Kompleksowe Workflow (Manual/Weekly)
- Pełne audyty bezpieczeństwa
- Uruchamiane manualnie lub raz w tygodniu
- `security-pipeline.yml`

---

**Ostatnia aktualizacja:** 2025-01-10

