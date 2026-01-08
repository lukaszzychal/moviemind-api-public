# Branch Protection Setup Instructions

## Problem
PR pozwala na merge mimo że pipeline nie przeszedł.

## Rozwiązanie

### 1. Konfiguracja Branch Protection Rules

Przejdź do: **Settings → Branches → Add rule** dla `main` branch

### 2. Wymagane ustawienia:

```yaml
Branch Protection Rule for 'main':
  Require a pull request before merging:
    ✅ Required
    ✅ Require approvals: 1
    ✅ Dismiss stale PR approvals when new commits are pushed
    ✅ Require review from code owners

  Require status checks to pass before merging:
    ✅ Required
    ✅ Require branches to be up to date before merging
    ✅ Status checks to require:
       - Test PHP 8.2
       - Test PHP 8.3
       - Security & Lint
       - Postman API Tests (Newman)

  Block force pushes:
    ✅ Required

  Require linear history:
    ✅ Required

  Include administrators:
    ✅ Required
```

### 3. Automatyczna konfiguracja - użyj skryptu:

```bash
# Upewnij się, że masz zainstalowany GitHub CLI (gh)
# https://cli.github.com/

# Uruchom skrypt konfiguracyjny
./.github/workflows/configure-branch-protection.sh
```

### 4. Alternatywnie - użyj GitHub CLI ręcznie:

```bash
gh api repos/lukaszzychal/moviemind-api-public/branches/main/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"contexts":["Test PHP 8.2","Test PHP 8.3","Security & Lint","Postman API Tests (Newman)"]}' \
  --field enforce_admins=true \
  --field required_pull_request_reviews='{"required_approving_review_count":1,"dismiss_stale_reviews":true}' \
  --field restrictions=null \
  --field allow_force_pushes=false \
  --field allow_deletions=false \
  --field required_linear_history=true
```

### 4. Weryfikacja

Po skonfigurowaniu, PR nie będzie mógł być zmergowany dopóki wszystkie wymagane status checks nie przejdą.

