# Branch Protection Rules for MovieMind API

## Overview
This document outlines the recommended branch protection rules for the MovieMind API repository to ensure code quality, security, and maintainability.

## Main Branch Protection Rules

### Required Status Checks
- ✅ Require status checks to pass before merging
  - `gitleaks-security-scan` — GitLeaks security scan
  - `security-audit` — Composer security audit
  - `phpunit-tests` — PHP unit tests (when implemented)
  - `code-quality` — Code quality checks (when implemented)

### Branch Protection Settings
- ✅ Require branches to be up to date before merging
- ✅ Require pull request reviews before merging
  - Required reviewers: 1
  - Dismiss stale reviews when new commits are pushed
  - Require review from code owners (CODEOWNERS)
- ✅ Restrict pushes that create files larger than 100MB
- ✅ Require linear history (no merge commits)
- ✅ Include administrators in protection rules

### Additional Security Settings
- ✅ Require signed commits (recommended)
- ✅ Block force pushes
- ✅ Restrict creations
- ✅ Restrict updates
- ✅ Restrict deletions
- ✅ Require deployments to succeed
- ✅ Require code scanning results
- ✅ Automatically request Copilot code review

## Branch Naming Conventions

### Protected Branches
- `main` — Production-ready code
- `develop` — Integration branch for features
- `release/*` — Release preparation branches

### Feature Branches
- `feature/feature-name` — New features
- `bugfix/bug-description` — Bug fixes
- `hotfix/critical-fix` — Critical production fixes
- `chore/task-description` — Maintenance tasks

## Code Review Requirements

### Reviewers
- Required: at least 1 reviewer
- Code Owners — review required for changes to:
  - `.github/workflows/` — CI/CD workflows
  - `docker-compose.yml` — Infrastructure changes
  - `composer.json` — Dependency changes
  - `README.md` — Documentation changes

### Review Guidelines
1. Security — All security-related changes require security team review
2. Dependencies — Dependency updates require thorough review
3. Infrastructure — Docker and deployment changes need infrastructure review
4. Documentation — README and API docs changes need documentation review

## Automated Checks

### Pre-merge Checks
1. GitLeaks Scan — Detects secrets and sensitive information
2. Security Audit — Checks for known vulnerabilities in dependencies
3. Code Quality — Ensures code meets quality standards
4. Tests — All tests must pass

### Post-merge Actions
1. Dependabot — Automatic dependency updates
2. Security Scanning — Continuous security monitoring
3. Documentation — Auto-update API documentation

## Emergency Procedures

### Hotfix Process
1. Create `hotfix/critical-issue` branch from `main`
2. Implement minimal fix
3. Request expedited review
4. Merge directly to `main` (bypass normal process)
5. Cherry-pick to `develop`

### Security Incident Response
1. Immediately lock affected branches
2. Create security advisory
3. Implement fix in private branch
4. Coordinate release with security team

## Implementation Steps

### GitHub Repository Settings
1. Go to Settings → Branches
2. Click Add rule for `main` branch
3. Configure the following settings:

```yaml
Branch Protection Rule for 'main':
  Require a pull request before merging:
    ✅ Required
    ✅ Require approvals: 1
    ✅ Dismiss stale PR approvals when new commits are pushed
    ✅ Require review from code owners

  Require status checks to pass:
    ✅ Required
    ✅ Require branches to be up to date before merging
    ✅ Status checks: gitleaks-security-scan, security-audit

  Require signed commits:
    ✅ Required

  Require linear history:
    ✅ Required

  Block force pushes:
    ✅ Required

  Restrict creations / updates / deletions:
    ✅ Required

  Require deployments to succeed:
    ✅ Required
    ✅ Environments: production, staging

  Require code scanning results:
    ✅ Required
    ✅ Tools: CodeQL, Semgrep

  Automatically request Copilot code review:
    ✅ Required

  Include administrators:
    ✅ Required
```

### Code Owners File (CODEOWNERS)
Create `.github/CODEOWNERS`:

```
# Global owners
* @lukaszzychal

# Security and CI/CD
/.github/ @lukaszzychal
/.gitleaks.toml @lukaszzychal

# Infrastructure
/docker-compose.yml @lukaszzychal
/Dockerfile @lukaszzychal

# Dependencies
/composer.json @lukaszzychal
/composer.lock @lukaszzychal

# Documentation
/README.md @lukaszzychal
/docs/ @lukaszzychal
```

## Security Levels and Recommendations

### Basic Protection (MVP)
- ✅ Require pull request, status checks, block force push, linear history

### Enhanced Protection (Production)
- ✅ Everything from MVP + signed commits, operation restrictions, deployments, code scanning

### Enterprise Protection (High Security)
- ✅ Everything from Production + Copilot reviews, multiple reviewers, strict bypass permissions

## Monitoring and Alerts

### Security Alerts
- Dependabot — Automatic vulnerability notifications
- Secret Scanning — Real-time secret detection
- GitLeaks — Scheduled security scans

### Quality Metrics
- Code Coverage — Minimum 80% test coverage
- Security Score — Maintain A+ rating
- Dependency Health — Keep dependencies up to date

## Compliance and Auditing

### Audit Trail
- All changes tracked through pull requests
- Security scans logged and archived
- Code review history maintained

### Compliance Requirements
- GDPR — Handle user data securely
- Security — Regular security assessments
- Quality — Maintain high code quality standards

---

Note: Implement these rules gradually and adjust based on team size and project requirements. Start with basic protection and add more rules as the project matures.


