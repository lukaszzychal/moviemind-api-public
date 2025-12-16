# Security Process

> **Source:** Migrated from `.cursor/rules/old/secret-detection.mdc` and `.cursor/rules/old/security-awareness.mdc`  
> **Category:** reference

## Secret Detection

### Basic Principle

**ALWAYS check for secrets before commit and push. Zero tolerance for secrets in the repository.**

### Automatic Git Hooks Installation

**ALWAYS before commit or push - automatically check and install hooks:**

1. **Check if hooks are installed:**
   ```bash
   if [ ! -f ".git/hooks/pre-commit" ]; then
       # Automatically install hooks
       if [ -f "scripts/git-hooks/pre-commit" ]; then
           cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
           chmod +x .git/hooks/pre-commit
           echo "✅ Git hooks installed automatically"
       else
           echo "⚠️  Git hooks template not found at scripts/git-hooks/pre-commit"
       fi
   fi
   ```

2. **For AI Agent - automatic installation:**
   - Before every `git commit` or `git push`, automatically check if `.git/hooks/pre-commit` exists
   - If it doesn't exist, automatically install from `scripts/git-hooks/pre-commit`
   - Make sure the hook has execute permissions (`chmod +x`)
   - Don't ask the user - execute automatically

3. **Check if GitLeaks is installed:**
   ```bash
   if ! command -v gitleaks &> /dev/null; then
       echo "⚠️  GitLeaks not installed. Install with: brew install gitleaks"
       echo "⚠️  Or run: ./scripts/setup-pre-commit.sh"
       # Don't block, but warn the user
   fi
   ```

### Secret Verification Before Commit

**BEFORE EVERY COMMIT:**

1. **Run GitLeaks on staged files:**
   ```bash
   gitleaks protect --source . --verbose --no-banner --staged
   ```

2. **If secrets are detected:**
   - ❌ **BLOCK commit**
   - Inform the user about detected secrets
   - Show secret location (files, lines)
   - Suggest removing secrets and using environment variables

3. **If GitLeaks is not installed:**
   - ⚠️ Warn the user
   - Suggest installation: `brew install gitleaks` or `./scripts/setup-pre-commit.sh`
   - **DO NOT BLOCK** commit, but clearly warn

### Secret Verification Before Push

**BEFORE EVERY PUSH:**

1. **Run GitLeaks on entire repository:**
   ```bash
   gitleaks protect --source . --verbose --no-banner
   ```

2. **If secrets are detected:**
   - ❌ **BLOCK push**
   - Inform the user about detected secrets
   - Check commit history - the secret might be in an old commit
   - Suggest using `git filter-branch` or `git filter-repo` to remove secrets from history

### Types of Secrets to Detect

GitLeaks detects:
- API keys (OpenAI, AWS, Google Cloud, etc.)
- Passwords
- Tokens (JWT, OAuth, etc.)
- Private keys (SSH, GPG, etc.)
- Database credentials
- Secret keys and seed phrases

### Files to Check

**Always check:**
- `.env` and `.env.*` (should be in `.gitignore`)
- `.env.bak` and `*.env.bak` (should be in `.gitignore`)
- Configuration files with passwords
- Files with hardcoded credentials
- Backup files with secrets

### Workflow Before Commit

```bash
# 1. Check if hooks are installed
if [ ! -f ".git/hooks/pre-commit" ]; then
    cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
    chmod +x .git/hooks/pre-commit
fi

# 2. Check secrets
if command -v gitleaks &> /dev/null; then
    gitleaks protect --source . --verbose --no-banner --staged
    if [ $? -ne 0 ]; then
        echo "❌ Secrets detected! Commit blocked."
        exit 1
    fi
else
    echo "⚠️  GitLeaks not installed. Install with: brew install gitleaks"
fi

# 3. Continue with other checks (Pint, PHPStan, etc.)
```

### Workflow Before Push

```bash
# 1. Check secrets in entire repository
if command -v gitleaks &> /dev/null; then
    gitleaks protect --source . --verbose --no-banner
    if [ $? -ne 0 ]; then
        echo "❌ Secrets detected! Push blocked."
        exit 1
    fi
else
    echo "⚠️  GitLeaks not installed. Install with: brew install gitleaks"
    echo "⚠️  Proceeding without secret check (NOT RECOMMENDED)"
fi
```

### What to Do When a Secret is Detected

1. **Remove secret from file:**
   - Replace secret with environment variable
   - Use `.env` for local values
   - Use GitHub Secrets for CI/CD

2. **If secret is in commit history:**
   - Use `git filter-branch` or `git filter-repo` to remove from history
   - Regenerate the secret (API key, password, etc.)
   - Update all places where the secret was used

3. **Update `.gitignore`:**
   - Make sure files with secrets are ignored
   - Add patterns for backup files (`.env.bak`, `*.bak`)

### Tools

- **GitLeaks** - main tool for secret detection
- **GitHub Push Protection** - backup at GitHub level (works after push)
- **Git hooks** - local protection before commit

### Priority

**Security > Everything Else**

Secrets in the repository are a critical security problem. Always check before commit and push.

---

## Security Awareness and Practices

### Principle

**Security is the highest priority. Always consider security implications during development, code review, and task execution.**

### Security-First Mindset

#### During Development

1. **Always consider security:**
   - Before writing code, think about security implications
   - Security by design, not as an afterthought
   - Defense in depth - multiple layers of protection
   - Fail secure - safe default behaviors

2. **Security during task execution:**
   - ✅ **Minor security issues** - fix immediately as part of current task
   - ✅ **Medium security issues** - add to current task if time permits
   - ✅ **Major security issues** - create separate high-priority task immediately

3. **Security code review checklist:**
   - [ ] Input validation and sanitization
   - [ ] Output encoding/escaping
   - [ ] Authentication and authorization verified
   - [ ] Error handling without information leaks
   - [ ] Logging without secrets
   - [ ] Dependencies updated
   - [ ] Secrets only in environment variables
   - [ ] Prompt injection protection (for AI features)
   - [ ] SQL injection protection (ORM used)
   - [ ] XSS protection (if applicable)

### Security Standards

#### OWASP Top 10

Always consider OWASP Top 10 vulnerabilities:
1. Broken Access Control
2. Cryptographic Failures
3. Injection (SQL, Command, LDAP, Prompt Injection)
4. Insecure Design
5. Security Misconfiguration
6. Vulnerable and Outdated Components
7. Identification and Authentication Failures
8. Software and Data Integrity Failures
9. Security Logging and Monitoring Failures
10. Server-Side Request Forgery (SSRF)

#### OWASP LLM Top 10 (for AI features)

Always consider OWASP LLM Top 10 vulnerabilities:
1. Prompt Injection
2. Insecure Output Handling
3. Training Data Poisoning
4. Model Denial of Service
5. Supply Chain Vulnerabilities
6. Sensitive Information Disclosure
7. Insecure Plugin Design
8. Excessive Agency
9. Overreliance
10. Model Theft

### Security Audits

#### Ad-hoc Security Reviews

**When:** During code review or when implementing new features

**Process:**
1. Review code for security issues
2. Check security checklist
3. Verify security controls implementation
4. Document findings
5. Fix minor issues immediately
6. Create tasks for major issues

**Scope:**
- Quick security review
- Security checklist verification
- Best practices check

#### Comprehensive Security Audits

**Frequency:**
- **Quarterly** (every 3 months) - basic audits
- **Semi-annually** (every 6 months) - detailed audits
- **Before major releases** - pre-release audits
- **After security incidents** - post-incident audits

**Scope:**
1. OWASP Top 10 review
2. OWASP LLM Top 10 review (for AI features)
3. Dependency audit
4. Configuration review
5. Code security review (SAST)
6. Infrastructure security
7. Authentication & Authorization
8. Data protection
9. Logging & Monitoring
10. Incident response procedures

**Process:**
1. **Planning** (1-2 days before)
   - Define scope
   - Prepare checklist
   - Schedule time

2. **Execution** (1-3 days)
   - Conduct audit
   - Document findings
   - Prioritize issues

3. **Reporting** (1 day after)
   - Create report
   - Categorize issues
   - Provide recommendations

4. **Remediation** (1-4 weeks)
   - Implement fixes
   - Verify fixes
   - Follow-up review

### Security Audit Template

When conducting comprehensive audits, use the following template:

```markdown
# Security Audit Report - YYYY-MM-DD

## Executive Summary
- Audit Date: YYYY-MM-DD
- Scope: [Comprehensive/Partial]
- Issues Found: X (Critical: Y, High: Z, Medium: W, Low: V)

## Findings

### Critical (P0)
- [Issue 1]
  - Description
  - Risk
  - Recommendation
  - Status

### High (P1)
- [Issue 2]
  ...

## OWASP Top 10 Mapping
- A01: ✅/⚠️/❌
- ...

## OWASP LLM Top 10 Mapping
- LLM01: ✅/⚠️/❌
- ...

## Recommendations
1. [Recommendation 1]
2. [Recommendation 2]

## Action Items
- [ ] Task 1
- [ ] Task 2
```

### Security Issue Prioritization

#### Priority Levels

1. **🔴 Critical (P0)** - Fix immediately, blocks deployment
   - Active exploits
   - Data breaches
   - System compromise
   - Critical vulnerabilities

2. **🟡 High (P1)** - Fix before next release
   - High-risk vulnerabilities
   - Security misconfigurations
   - Missing security controls

3. **🟢 Medium (P2)** - Fix in next sprint
   - Medium-risk vulnerabilities
   - Best practice violations
   - Minor security improvements

4. **⚪ Low (P3)** - Fix when time permits
   - Low-risk issues
   - Informational findings
   - Enhancement opportunities

#### Handling Security Issues

**During task execution:**
- ✅ **Minor issues** - fix immediately as part of current task
- ✅ **Medium issues** - add to current task if time permits
- ✅ **Major issues** - create separate high-priority task immediately

**After security audit:**
- Create tasks for all findings
- Prioritize based on risk level
- Track remediation progress
- Verify fixes

### Security Tools and Automation

#### Pre-Commit

- GitLeaks - secret detection
- Markdownlint - documentation formatting
- PHP linting (Pint) - code formatting

#### CI/CD Pipeline

- GitLeaks security scan (every commit, daily at 2 AM UTC)
- Composer audit (every PR, weekly)
- CodeQL analysis (every PR, daily at 2:21 UTC)
- Docker security scan (every build)
- PHPStan static analysis (every PR)

#### Automated Security Scans

**Frequency:**
- **GitLeaks:** Every commit + daily at 2:00 UTC
- **Composer Audit:** Every PR + weekly
- **CodeQL:** Every PR + daily at 2:21 UTC
- **Docker Scan:** Every build
- **PHPStan:** Every PR

### Security Best Practices

#### Secrets Management

**Never:**
- ❌ Hardcode secrets in code
- ❌ Commit secrets to repository
- ❌ Log secrets
- ❌ Expose secrets in error messages

**Always:**
- ✅ Use environment variables
- ✅ Use secret management systems
- ✅ Verify with GitLeaks before commit
- ✅ Rotate secrets regularly

#### Input Validation

**Always:**
- ✅ Validate all inputs
- ✅ Sanitize user input
- ✅ Check length limits
- ✅ Verify data types
- ✅ Use parameterized queries (ORM)

#### Error Handling

**Security considerations:**
- ✅ Generic error messages for users
- ✅ Detailed errors only in logs (development)
- ✅ No stack traces in production
- ✅ No file paths in errors
- ✅ No sensitive data in errors

#### Logging

**Security logging:**
- ✅ Log all security events
- ✅ Never log secrets
- ✅ Use structured logging
- ✅ Implement log rotation
- ✅ Monitor security logs

#### AI Security (Prompt Injection)

**For AI features:**
- ✅ Sanitize all inputs used in prompts
- ✅ Detect suspicious patterns
- ✅ Log injection attempts
- ✅ Use multi-layer validation
- ✅ Verify output format

### Security Documentation

#### Required Documentation

1. **Security Policy** (`SECURITY.md`)
   - Vulnerability reporting
   - Security measures
   - Best practices

2. **Security Analysis** (`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`)
   - OWASP Top 10 mapping
   - OWASP LLM Top 10 mapping
   - Audit procedures
   - Best practices

3. **Prompt Injection Analysis** (`docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md`)
   - Detailed prompt injection analysis
   - Protection mechanisms
   - Recommendations

#### Documentation Updates

**When to update:**
- After security audits
- After security incidents
- When new threats are identified
- When security procedures change
- Quarterly review

### Incident Response

#### Response Procedure

1. **Detection**
   - Security alerts
   - Monitoring alerts
   - User reports
   - Security audits

2. **Assessment**
   - Evaluate risk level
   - Determine impact
   - Classify severity

3. **Response**
   - Critical: Immediate action
   - High: Fix within 24-48 hours
   - Medium: Fix within 1 week
   - Low: Fix in next sprint

4. **Documentation**
   - Document incident
   - Root cause analysis
   - Remediation steps
   - Lessons learned

#### Post-Incident Review

**After every incident:**
1. Post-mortem meeting (48h after)
2. Document lessons learned
3. Update procedures
4. Follow-up audit (if applicable)

### Security Metrics

#### Key Metrics

1. **Vulnerability Metrics**
   - Number of vulnerabilities found
   - Time to remediation (MTTR)
   - Security test coverage

2. **Audit Metrics**
   - Audit frequency
   - Findings per audit
   - Trend over time

3. **Pipeline Metrics**
   - Security checks in pipeline
   - Pass rate
   - Execution time

4. **Incident Metrics**
   - Number of incidents
   - Response time (MTTR)
   - Remediation time

#### Security Score

**Scoring system:**
- **A+ (90-100):** Excellent security posture
- **A (80-89):** Good security posture
- **B (70-79):** Acceptable, needs improvement
- **C (60-69):** Needs significant improvement
- **D (<60):** Critical issues

**Factors:**
- OWASP Top 10 coverage
- OWASP LLM Top 10 coverage
- Dependency vulnerabilities
- Security test coverage
- Audit frequency
- Incident response time

### Related Documents

- [`SECURITY.md`](../../../SECURITY.md) - Security Policy
- [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](../technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) - Comprehensive Security Guide
- [`docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md`](../technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md) - Prompt Injection Analysis
- [OWASP Top 10](https://owasp.org/Top10/) - OWASP Top 10
- [OWASP LLM Top 10](https://owasp.org/www-project-llm-top-10/) - OWASP LLM Top 10

### Enforcement

- AI Agent MUST consider security during task execution
- AI Agent MUST fix minor security issues immediately
- AI Agent MUST create tasks for major security issues
- AI Agent MUST document security findings
- AI Agent MUST follow security best practices
- AI Agent MUST update security documentation when needed

