# Porównanie Security Workflows - Przed i Po Refaktoryzacji

> **Data utworzenia:** 2025-01-10  
> **Kontekst:** Analiza duplikacji i organizacja security workflows  
> **Kategoria:** technical

## ❌ PRZED Refaktoryzacją

### security-pipeline.yml (stara wersja) - Z DUPLIKACJAMI

**Trigger:** PR + Daily + Manual

**Narzędzia:**
- ❌ **GitLeaks** - DUPLIKACJA (już w `code-security-scan.yml`)
- ❌ **Composer Audit** - DUPLIKACJA (już w `code-security-scan.yml`, `ci.yml`)
- ❌ **PHPStan** - DUPLIKACJA (już w `ci.yml`)
- ❌ **Trivy** - DUPLIKACJA (już w `docker-security-scan.yml`)

**Problem:**
- 4 zduplikowane narzędzia
- Niepotrzebne zużycie zasobów CI/CD
- Długi czas wykonania
- Trudność w utrzymaniu (zmiany w wielu miejscach)

---

## ✅ PO Refaktoryzacji

### security-pipeline.yml (nowa wersja) - BEZ DUPLIKACJI

**Trigger:** Manual + Weekly (tylko kompleksowe audyty)

**Narzędzia (TYLKO NOWE):**
- ✅ **Hadolint** - Dockerfile security linter
- ✅ **npm audit** - Node.js dependencies audit
- ✅ **Security Headers Check** - API security headers configuration
- ✅ **Laravel Security Checker** - Framework-specific security checks

**Zalety:**
- Zero duplikacji
- Szybsze workflow (mniej redundantnych skanów)
- Nowe narzędzia bezpieczeństwa
- Agregacja wyników z innych workflow

---

## 📊 Mapa Wszystkich Workflow

### Dedykowane Workflow (Automatic - na każdym PR/commit)

| Workflow | Narzędzie | Trigger | Cel |
|----------|-----------|---------|-----|
| `code-security-scan.yml` | GitLeaks | PR/Push/Daily | Secret detection |
| `code-security-scan.yml` | Composer Audit | PR/Push/Daily | Dependency audit |
| `ci.yml` | Composer Audit | PR/Push | Dependency audit (w CI) |
| `ci.yml` | PHPStan | PR/Push | Static analysis |
| `docker-security-scan.yml` | Trivy | PR/Push/Weekly | Container security |
| `codeql.yml` | CodeQL | PR/Push/Weekly | Advanced SAST |

### Kompleksowy Workflow (Manual/Weekly)

| Workflow | Narzędzie | Trigger | Cel |
|----------|-----------|---------|-----|
| `security-pipeline.yml` | Hadolint | Manual/Weekly | Dockerfile linter |
| `security-pipeline.yml` | npm audit | Manual/Weekly | Node.js dependencies |
| `security-pipeline.yml` | Security Headers | Manual/Weekly | API headers check |
| `security-pipeline.yml` | Laravel Checker | Manual/Weekly | Framework security |

---

## 🔄 Zmiany w Triggerach

### PRZED:
```yaml
# security-pipeline.yml
on:
  pull_request:  # ⚠️ Duplikacja z innymi workflow
  schedule:      # Daily - za często
  workflow_dispatch:
```

### PO:
```yaml
# security-pipeline.yml
on:
  workflow_dispatch:  # ✅ Manual - tylko gdy potrzeba
  schedule:           # ✅ Weekly - raz w tygodniu
    - cron: '0 3 * * 0'  # Niedziela 3:00 UTC
```

---

## ✅ Zalety Nowego Podejścia

1. **Brak duplikacji** - każde narzędzie w jednym miejscu
2. **Szybsze PR checks** - mniej redundantnych skanów
3. **Nowe narzędzia** - Hadolint, npm audit, Security Headers
4. **Lepsza organizacja** - dedykowane vs kompleksowe workflow
5. **Agregacja wyników** - security-pipeline łączy wyniki bez duplikacji

---

## 📋 Rekomendacje Dodatkowych Narzędzi

### ✅ Dodane w security-pipeline.yml:
- Hadolint (Dockerfile linter)
- npm audit (Node.js dependencies)
- Security Headers Check
- Laravel Security Checker

### 🔄 Do Rozważenia w Przyszłości:
- **OWASP Dependency Check** - rozszerzenie Composer Audit (jeśli potrzebne)
- **Bandit** - Python security scanner (jeśli dodamy Python)
- **Safety** - Python dependencies (jeśli dodamy Python)
- **Checkov** - Infrastructure as Code security (jeśli dodamy Terraform/CloudFormation)
- **SonarQube/SonarCloud** - kompleksowa analiza jakości kodu (jeśli potrzebne)

---

**Ostatnia aktualizacja:** 2025-01-10

