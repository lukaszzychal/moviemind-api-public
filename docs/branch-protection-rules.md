# Branch Protection Rules for MovieMind API / Zasady Ochrony Gałęzi dla MovieMind API

## Overview / Przegląd
This document outlines the recommended branch protection rules for the MovieMind API repository to ensure code quality, security, and maintainability. / Ten dokument opisuje zalecane zasady ochrony gałęzi dla repozytorium MovieMind API, aby zapewnić jakość kodu, bezpieczeństwo i łatwość utrzymania.

## Main Branch Protection Rules / Zasady Ochrony Głównej Gałęzi

### Required Status Checks / Wymagane Sprawdzenia Statusu
- ✅ **Require status checks to pass before merging** / **Wymagaj przejścia sprawdzeń statusu przed scaleniem**
  - `gitleaks-security-scan` - GitLeaks security scan / GitLeaks skan bezpieczeństwa
  - `security-audit` - Composer security audit / Composer audyt bezpieczeństwa
  - `phpunit-tests` - PHP unit tests (when implemented) / PHP testy jednostkowe (gdy zaimplementowane)
  - `code-quality` - Code quality checks (when implemented) / Sprawdzenia jakości kodu (gdy zaimplementowane)

### Branch Protection Settings / Ustawienia Ochrony Gałęzi
- ✅ **Require branches to be up to date before merging** / **Wymagaj aktualności gałęzi przed scaleniem**
- ✅ **Require pull request reviews before merging** / **Wymagaj recenzji pull request przed scaleniem**
  - Required reviewers: 1 / Wymagani recenzenci: 1
  - Dismiss stale reviews when new commits are pushed / Odrzuć przestarzałe recenzje przy nowych commitach
  - Require review from code owners / Wymagaj recenzji od właścicieli kodu
- ✅ **Restrict pushes that create files larger than 100MB** / **Ogranicz pushy tworzące pliki większe niż 100MB**
- ✅ **Require linear history** (no merge commits) / **Wymagaj liniowej historii** (bez commitów merge)
- ✅ **Include administrators** in protection rules / **Uwzględnij administratorów** w zasadach ochrony

### Additional Security Settings / Dodatkowe Ustawienia Bezpieczeństwa
- ✅ **Require signed commits** (recommended) / **Wymagaj podpisanych commitów** (zalecane)
- ✅ **Require conversation resolution before merging** / **Wymagaj rozwiązania rozmów przed scaleniem**
- ✅ **Lock branch** (for critical releases) / **Zablokuj gałąź** (dla krytycznych wydań)

## Branch Naming Conventions / Konwencje Nazewnictwa Gałęzi

### Protected Branches / Chronione Gałęzie
- `main` - Production-ready code / Kod gotowy do produkcji
- `develop` - Integration branch for features / Gałąź integracyjna dla funkcji
- `release/*` - Release preparation branches / Gałęzie przygotowania wydań

### Feature Branches / Gałęzie Funkcji
- `feature/feature-name` - New features / Nowe funkcje
- `bugfix/bug-description` - Bug fixes / Naprawy błędów
- `hotfix/critical-fix` - Critical production fixes / Krytyczne naprawy produkcyjne
- `chore/task-description` - Maintenance tasks / Zadania konserwacyjne

## Code Review Requirements / Wymagania Recenzji Kodu

### Reviewers / Recenzenci
- **Required**: At least 1 reviewer / **Wymagany**: Co najmniej 1 recenzent
- **Code Owners**: Review required for changes to: / **Właściciele Kodu**: Recenzja wymagana dla zmian w:
  - `.github/workflows/` - CI/CD workflows / CI/CD przepływy pracy
  - `docker-compose.yml` - Infrastructure changes / Zmiany infrastruktury
  - `composer.json` - Dependency changes / Zmiany zależności
  - `README.md` - Documentation changes / Zmiany dokumentacji

### Review Guidelines / Wytyczne Recenzji
1. **Security**: All security-related changes require security team review / **Bezpieczeństwo**: Wszystkie zmiany związane z bezpieczeństwem wymagają recenzji zespołu bezpieczeństwa
2. **Dependencies**: Dependency updates require thorough review / **Zależności**: Aktualizacje zależności wymagają dokładnej recenzji
3. **Infrastructure**: Docker and deployment changes need infrastructure review / **Infrastruktura**: Zmiany Docker i wdrożenia wymagają recenzji infrastruktury
4. **Documentation**: README and API docs changes need documentation review / **Dokumentacja**: Zmiany README i dokumentacji API wymagają recenzji dokumentacji

## Automated Checks / Automatyczne Sprawdzenia

### Pre-merge Checks / Sprawdzenia Przed Scaleniem
1. **GitLeaks Scan**: Detects secrets and sensitive information / **Skan GitLeaks**: Wykrywa sekrety i wrażliwe informacje
2. **Security Audit**: Checks for known vulnerabilities in dependencies / **Audyt Bezpieczeństwa**: Sprawdza znane luki w zależnościach
3. **Code Quality**: Ensures code meets quality standards / **Jakość Kodu**: Zapewnia spełnienie standardów jakości
4. **Tests**: All tests must pass / **Testy**: Wszystkie testy muszą przejść

### Post-merge Actions / Akcje Po Scaleniu
1. **Dependabot**: Automatic dependency updates / **Dependabot**: Automatyczne aktualizacje zależności
2. **Security Scanning**: Continuous security monitoring / **Skanowanie Bezpieczeństwa**: Ciągłe monitorowanie bezpieczeństwa
3. **Documentation**: Auto-update API documentation / **Dokumentacja**: Automatyczna aktualizacja dokumentacji API

## Emergency Procedures / Procedury Awaryjne

### Hotfix Process / Proces Hotfix
For critical production issues: / Dla krytycznych problemów produkcyjnych:
1. Create `hotfix/critical-issue` branch from `main` / Utwórz gałąź `hotfix/critical-issue` z `main`
2. Implement minimal fix / Zaimplementuj minimalną naprawę
3. Request expedited review / Poproś o przyspieszoną recenzję
4. Merge directly to `main` (bypass normal process) / Scal bezpośrednio do `main` (omijając normalny proces)
5. Cherry-pick to `develop` / Cherry-pick do `develop`

### Security Incident Response / Reakcja na Incydent Bezpieczeństwa
1. Immediately lock affected branches / Natychmiast zablokuj dotknięte gałęzie
2. Create security advisory / Utwórz poradę bezpieczeństwa
3. Implement fix in private branch / Zaimplementuj naprawę w prywatnej gałęzi
4. Coordinate release with security team / Skoodynuj wydanie z zespołem bezpieczeństwa

## Implementation Steps / Kroki Implementacji

### GitHub Repository Settings / Ustawienia Repozytorium GitHub
1. Go to **Settings** → **Branches** / Przejdź do **Settings** → **Branches**
2. Click **Add rule** for `main` branch / Kliknij **Add rule** dla gałęzi `main`
3. Configure the following settings: / Skonfiguruj następujące ustawienia:

```yaml
Branch Protection Rule for 'main' / Zasada Ochrony Gałęzi dla 'main':
  Require a pull request before merging / Wymagaj pull request przed scaleniem:
    ✅ Required / ✅ Wymagane
    ✅ Require approvals: 1 / ✅ Wymagaj zatwierdzeń: 1
    ✅ Dismiss stale PR approvals when new commits are pushed / ✅ Odrzuć przestarzałe zatwierdzenia PR przy nowych commitach
    ✅ Require review from code owners / ✅ Wymagaj recenzji od właścicieli kodu
  
  Require status checks to pass before merging / Wymagaj przejścia sprawdzeń statusu przed scaleniem:
    ✅ Required / ✅ Wymagane
    ✅ Require branches to be up to date before merging / ✅ Wymagaj aktualności gałęzi przed scaleniem
    ✅ Status checks: gitleaks-security-scan, security-audit / ✅ Sprawdzenia statusu: gitleaks-security-scan, security-audit
  
  Require conversation resolution before merging / Wymagaj rozwiązania rozmów przed scaleniem:
    ✅ Required / ✅ Wymagane
  
  Require signed commits / Wymagaj podpisanych commitów:
    ✅ Required / ✅ Wymagane
  
  Require linear history / Wymagaj liniowej historii:
    ✅ Required / ✅ Wymagane
  
  Include administrators / Uwzględnij administratorów:
    ✅ Required / ✅ Wymagane
  
  Restrict pushes that create files larger than 100MB / Ogranicz pushy tworzące pliki większe niż 100MB:
    ✅ Required / ✅ Wymagane
```

### Code Owners File / Plik Właścicieli Kodu
Create `.github/CODEOWNERS`: / Utwórz `.github/CODEOWNERS`:

```
# Global owners / Globalni właściciele
* @lukaszzychal

# Security and CI/CD / Bezpieczeństwo i CI/CD
/.github/ @lukaszzychal
/.gitleaks.toml @lukaszzychal

# Infrastructure / Infrastruktura
/docker-compose.yml @lukaszzychal
/Dockerfile @lukaszzychal

# Dependencies / Zależności
/composer.json @lukaszzychal
/composer.lock @lukaszzychal

# Documentation / Dokumentacja
/README.md @lukaszzychal
/docs/ @lukaszzychal
```

## Monitoring and Alerts / Monitorowanie i Alerty

### Security Alerts / Alerty Bezpieczeństwa
- **Dependabot**: Automatic vulnerability notifications / **Dependabot**: Automatyczne powiadomienia o lukach
- **Secret Scanning**: Real-time secret detection / **Skanowanie Sekretów**: Wykrywanie sekretów w czasie rzeczywistym
- **GitLeaks**: Scheduled security scans / **GitLeaks**: Zaplanowane skany bezpieczeństwa

### Quality Metrics / Metryki Jakości
- **Code Coverage**: Minimum 80% test coverage / **Pokrycie Kodu**: Minimum 80% pokrycia testami
- **Security Score**: Maintain A+ rating / **Ocena Bezpieczeństwa**: Utrzymuj ocenę A+
- **Dependency Health**: Keep dependencies up to date / **Zdrowie Zależności**: Utrzymuj zależności na bieżąco

## Compliance and Auditing / Zgodność i Audyt

### Audit Trail / Ślad Audytowy
- All changes tracked through pull requests / Wszystkie zmiany śledzone przez pull requesty
- Security scans logged and archived / Skanowanie bezpieczeństwa logowane i archiwizowane
- Code review history maintained / Historia recenzji kodu utrzymywana

### Compliance Requirements / Wymagania Zgodności
- **GDPR**: Handle user data securely / **GDPR**: Bezpieczne przetwarzanie danych użytkowników
- **Security**: Regular security assessments / **Bezpieczeństwo**: Regularne oceny bezpieczeństwa
- **Quality**: Maintain high code quality standards / **Jakość**: Utrzymuj wysokie standardy jakości kodu

---

**Note**: These rules should be implemented gradually and adjusted based on team size and project requirements. Start with basic protection and add more rules as the project matures. / **Uwaga**: Te zasady powinny być implementowane stopniowo i dostosowywane na podstawie rozmiaru zespołu i wymagań projektu. Zacznij od podstawowej ochrony i dodawaj więcej zasad w miarę dojrzewania projektu.
