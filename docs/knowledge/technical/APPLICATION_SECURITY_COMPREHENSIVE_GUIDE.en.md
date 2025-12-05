# Comprehensive Application Security Guide for MovieMind API

> **Created:** 2025-01-10  
> **Context:** Comprehensive application security document with OWASP, AI security, audits  
> **Category:** technical  
> **Polish version:** [`APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](./APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md)

## 🎯 Goal

Comprehensive security guide for MovieMind API application covering:

- OWASP Top 10 and security standards
- AI Security (OWASP LLM Top 10)
- Security audits (ad-hoc and comprehensive)
- CI/CD security pipeline
- Best practices and procedures

## 📋 Table of Contents

1. [OWASP Top 10 - Main Threats](#owasp-top-10)
2. [OWASP LLM Top 10 - AI Security](#owasp-llm-top-10)
3. [AI Security in MovieMind API](#ai-security)
4. [Security Audits](#security-audits)
5. [CI/CD Security Pipeline](#cicd-pipeline)
6. [Best Practices](#best-practices)
7. [Incident Management](#incident-management)

---

## 🛡️ OWASP Top 10 - Main Threats

### 2021/2024 Top 10 Threat List

1. **A01:2021 – Broken Access Control**
   - **Risk:** Unauthorized access to resources
   - **Protection in MovieMind API:**
     - API key authentication
     - Rate limiting
     - Validation on all endpoints

2. **A02:2021 – Cryptographic Failures**
   - **Risk:** Improper handling of sensitive data
   - **Protection in MovieMind API:**
     - HTTPS only (TLS/SSL)
     - Environment variables for secrets
     - GitLeaks for secret detection in code

3. **A03:2021 – Injection**
   - **Risk:** SQL Injection, Command Injection, LDAP Injection
   - **Protection in MovieMind API:**
     - Eloquent ORM (parameterized queries)
     - Input validation and sanitization
     - Prompt injection protection (for AI)

4. **A04:2021 – Insecure Design**
   - **Risk:** Security gaps in architecture
   - **Protection in MovieMind API:**
     - Defense in depth
     - Security by design
     - Regular security reviews

5. **A05:2021 – Security Misconfiguration**
   - **Risk:** Incorrect security configuration
   - **Protection in MovieMind API:**
     - Secure defaults
     - Environment-based configuration
     - Regular configuration reviews

6. **A06:2021 – Vulnerable and Outdated Components**
   - **Risk:** Outdated libraries with vulnerabilities
   - **Protection in MovieMind API:**
     - Composer audit (automatic)
     - Dependabot (automatic updates)
     - Regular dependency updates

7. **A07:2021 – Identification and Authentication Failures**
   - **Risk:** Weak authentication mechanisms
   - **Protection in MovieMind API:**
     - API key authentication
     - Rate limiting
     - Secure token storage

8. **A08:2021 – Software and Data Integrity Failures**
   - **Risk:** Unverified data and software
   - **Protection in MovieMind API:**
     - Input validation
     - TMDb data verification
     - Signed commits

9. **A09:2021 – Security Logging and Monitoring Failures**
   - **Risk:** Lack of monitoring and logging
   - **Protection in MovieMind API:**
     - Comprehensive logging
     - Security event logging
     - Monitoring alerts

10. **A10:2021 – Server-Side Request Forgery (SSRF)**
    - **Risk:** Forced server-side requests
    - **Protection in MovieMind API:**
      - Input validation
      - URL whitelisting (when applicable)
      - Network segmentation

### Mapping to MovieMind API

| OWASP Risk | Status | Implementation |
|------------|--------|----------------|
| A01 - Access Control | ✅ | API keys, rate limiting |
| A02 - Cryptographic Failures | ✅ | HTTPS, env variables |
| A03 - Injection | ✅ | ORM, validation, prompt sanitization |
| A04 - Insecure Design | ✅ | Security reviews |
| A05 - Security Misconfiguration | ✅ | Secure defaults |
| A06 - Vulnerable Components | ✅ | Composer audit, Dependabot |
| A07 - Authentication Failures | ✅ | API keys, rate limiting |
| A08 - Integrity Failures | ✅ | Validation, verification |
| A09 - Logging Failures | ⚠️ | Partially - requires extension |
| A10 - SSRF | ✅ | Input validation |

---

## 🤖 OWASP LLM Top 10 - AI Security

### Top 10 Threats for AI/LLM Applications

1. **LLM01:2023 – Prompt Injection**
   - **Risk:** AI prompt manipulation
   - **Protection in MovieMind API:**
     - `PromptSanitizer` - sanitization of all inputs
     - `SlugValidator` - early detection
     - Multi-layer validation
     - Security logging

2. **LLM02:2023 – Insecure Output Handling**
   - **Risk:** Unverified AI outputs
   - **Protection in MovieMind API:**
     - JSON validation
     - Schema verification
     - Output sanitization

3. **LLM03:2023 – Training Data Poisoning**
   - **Risk:** Training data poisoning
   - **Protection in MovieMind API:**
     - We don't train our own models
     - We use verified sources (TMDb)
     - Data verification

4. **LLM04:2023 – Model Denial of Service**
   - **Risk:** DoS through expensive AI requests
   - **Protection in MovieMind API:**
     - Rate limiting
     - Request size limits
     - Timeout protection

5. **LLM05:2023 – Supply Chain Vulnerabilities**
   - **Risk:** Vulnerabilities in AI dependencies
   - **Protection in MovieMind API:**
     - Regular dependency audits
     - Vendor security reviews
     - Version pinning

6. **LLM06:2023 – Sensitive Information Disclosure**
   - **Risk:** Sensitive data leakage
   - **Protection in MovieMind API:**
     - Input sanitization
     - Output filtering
     - No secrets in prompts

7. **LLM07:2023 – Insecure Plugin Design**
   - **Risk:** Insecure AI plugins
   - **Status:** Not applicable (no plugins)

8. **LLM08:2023 – Excessive Agency**
   - **Risk:** Excessive AI permissions
   - **Protection in MovieMind API:**
     - Strict role definition
     - Limited scope of operations
     - No system access

9. **LLM09:2023 – Overreliance**
   - **Risk:** Over-reliance on AI
   - **Protection in MovieMind API:**
     - Human verification process
     - Fallback mechanisms
     - Data verification

10. **LLM10:2023 – Model Theft**
    - **Risk:** AI model theft
    - **Status:** Not applicable (we use external models)

### Detailed Prompt Injection Analysis

See detailed analysis: [`PROMPT_INJECTION_SECURITY_ANALYSIS.md`](./PROMPT_INJECTION_SECURITY_ANALYSIS.md)

---

## 🔒 AI Security in MovieMind API

### Current Security Measures

#### 1. Prompt Sanitization

**Service:** `PromptSanitizer`

- Removal of newline characters (`\n`, `\r`, `\t`)
- Suspicious pattern detection
- Logging of injection attempts
- Length validation

#### 2. Multi-Layer Validation

1. **SlugValidator** - early detection in slugs
2. **PromptSanitizer** - sanitization before prompt construction
3. **OpenAiClient** - final sanitization before API calls

#### 3. Input Verification

- TMDb data verification before use in prompts
- Slug validation
- JSON schema validation for outputs

#### 4. Security Logging

- All prompt injection attempts are logged
- IP address tracking
- User agent tracking
- Context preservation

### Recommendations

1. ✅ **Implemented:**
   - Prompt sanitization
   - Multi-layer validation
   - Security logging
   - Input verification

2. 🔄 **To consider:**
   - Rate limiting per IP for AI requests
   - Anomaly detection for suspicious patterns
   - Metrics dashboard for security events
   - Automated alerts for multiple attempts

---

## 🔍 Security Audits

### Types of Audits

#### 1. Ad-hoc Security Reviews

**Definition:** Security reviews performed when:

- Code review
- Implementing new features
- Changes in security-critical code

**Frequency:**

- **Always** for security-critical changes
- **Ad-hoc** during code review

**Scope:**

- Code review for security
- Verification of security controls implementation
- Best practices check
- Quick security checklist

**Process:**

1. Developer starts review
2. Security checklist verification
3. Vulnerability verification
4. Documentation of findings
5. Fix minor issues immediately
6. Create tasks for major issues

**Checklist for Ad-hoc Audits:**

- [ ] Input validation and sanitization
- [ ] Output encoding/escaping
- [ ] Authentication and authorization
- [ ] Error handling (without information leaks)
- [ ] Logging (without secrets)
- [ ] Dependency vulnerabilities
- [ ] Secrets management
- [ ] Prompt injection (for AI features)

#### 2. Comprehensive Security Audits

**Definition:** Full security reviews of entire application

**Frequency:**

- **Quarterly** (every 3 months) - basic audits
- **Semi-annually** (every 6 months) - detailed audits
- **Before major releases** - pre-release audits
- **After security incidents** - post-incident audits

**Scope:**

1. **OWASP Top 10 Review**
   - Check all 10 categories
   - Mapping to current implementation
   - Gap identification

2. **OWASP LLM Top 10 Review**
   - Check all 10 categories for AI
   - Review prompt injection protection
   - Verify AI security controls

3. **Dependency Audit**
   - Composer audit (automatic)
   - Manual review of critical dependencies
   - Update outdated libraries

4. **Configuration Review**
   - Environment variables
   - Security headers
   - CORS configuration
   - Rate limiting settings

5. **Code Security Review**
   - SAST (Static Application Security Testing)
   - Manual code review security-critical parts
   - Architecture review

6. **Infrastructure Security**
   - Docker security
   - Database security
   - Redis security
   - Network security

7. **Authentication & Authorization**
   - API key management
   - Rate limiting effectiveness
   - Access control verification

8. **Data Protection**
   - Encryption at rest
   - Encryption in transit
   - Data minimization
   - GDPR compliance

9. **Logging & Monitoring**
   - Security event logging
   - Monitoring coverage
   - Alert configuration

10. **Incident Response**
    - Response procedures
    - Communication plans
    - Recovery procedures

**Comprehensive Audit Process:**

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
   - Provide remediation recommendations

4. **Remediation** (1-4 weeks)
   - Implement fixes
   - Verify fixes
   - Follow-up review

**Audit Report Template:**

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

### Audit Automation

#### CI/CD Integration

**Automated audits in pipeline:**

- GitLeaks (secrets detection) - every commit
- Composer audit (dependencies) - every PR
- CodeQL (static analysis) - daily
- Docker security scan - every build
- PHPStan (code quality) - every PR

**Automated audit schedule:**

- **GitLeaks:** Every commit + daily at 2:00 UTC
- **Composer Audit:** Every PR + weekly
- **CodeQL:** Daily at 2:21 UTC + every PR
- **Docker Scan:** Every build
- **PHPStan:** Every PR

#### Manual Audits

**Ad-hoc:**
- Code review security checklist
- Ad-hoc security reviews

**Comprehensive:**
- Quarterly reviews
- Pre-release audits
- Post-incident audits

---

## 🔄 CI/CD Security Pipeline

### Current Pipeline

#### 1. Pre-Commit Hooks (Local)

**Tools:**
- GitLeaks - secret detection
- Markdownlint - documentation formatting
- PHP linting (Pint) - code formatting

**Workflow:**

```bash
# Automatically before every commit
gitleaks protect --source . --verbose --no-banner --staged
npm run markdownlint:fix
cd api && vendor/bin/pint
```

#### 2. Pull Request Checks

**Tools and workflows:**

1. **GitLeaks Security Scan** (`.github/workflows/code-security-scan.yml`)
   - Trigger: PR to main/develop
   - Schedule: Daily at 2:00 UTC
   - Detects: Secrets, credentials

2. **CodeQL Analysis** (`.github/workflows/codeql.yml`)
   - Trigger: PR to main + daily at 2:21 UTC
   - Detects: Security vulnerabilities (SAST)
   - Languages: Actions, JavaScript/TypeScript, Python

3. **Docker Security Scan** (`.github/workflows/docker-security-scan.yml`)
   - Trigger: Build image
   - Detects: Vulnerabilities in Docker images

4. **CI Pipeline** (`.github/workflows/ci.yml`)
   - Security job:
     - Composer audit
     - PHPStan static analysis
     - PHP linting

### Recommended Extended Pipeline

#### 1. Security-First Pipeline

**Proposed structure:**

```yaml
# .github/workflows/security-pipeline.yml
name: Security Pipeline

on:
  pull_request:
    branches: [main, develop]
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM UTC
  workflow_dispatch:  # Manual trigger

jobs:
  security-scan:
    name: Comprehensive Security Scan
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4
      
      # 1. Secret Detection
      - name: GitLeaks Scan
        uses: gitleaks/gitleaks-action@v2
      
      # 2. Dependency Audit
      - name: Composer Audit
        run: composer audit --format=json
      
      # 3. Static Analysis (SAST)
      - name: CodeQL Analysis
        uses: github/codeql-action/analyze@v4
      
      # 4. Docker Security Scan
      - name: Docker Security Scan
        uses: aquasecurity/trivy-action@master
      
      # 5. Container Image Scan
      - name: Container Image Scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ghcr.io/${{ github.repository }}:latest
      
      # 6. Security Headers Check
      - name: Security Headers Check
        run: |
          # Check security headers configuration
          # ...
      
      # 7. Generate Security Report
      - name: Generate Security Report
        run: |
          # Aggregate all security scan results
          # Generate comprehensive report
```

#### 2. Security Dashboard

**Recommended tools:**
- **GitHub Security Dashboard** - native integration
- **Dependabot Alerts** - automatic notifications
- **CodeQL Alerts** - security vulnerabilities
- **Custom Metrics** - custom security metrics

#### 3. Automated Remediation

**Future extensions:**
- Automatic dependency updates (Dependabot)
- Auto-fix for some issues (formatting)
- Automated security patches (when safe)

### Pipeline Frequency

| Tool | Trigger | Frequency |
|------|---------|-----------|
| GitLeaks | Commit, PR, Schedule | Every commit + daily |
| Composer Audit | PR, Schedule | Every PR + weekly |
| CodeQL | PR, Schedule | Every PR + daily |
| Docker Scan | Build | Every build |
| PHPStan | PR | Every PR |
| Security Headers | PR, Schedule | Every PR + weekly |

---

## 📋 Best Practices

### During Development

#### 1. Security-First Mindset

**Principles:**

- ✅ Always think about security when coding
- ✅ Security by design, not as an afterthought
- ✅ Defense in depth - multiple layers of protection
- ✅ Fail secure - safe default behaviors

#### 2. Code Review Security Checklist

**Before every PR:**

- [ ] Input validation and sanitization
- [ ] Output encoding/escaping
- [ ] Authentication and authorization verified
- [ ] Error handling without information leaks
- [ ] Logging without secrets
- [ ] Dependencies updated
- [ ] Secrets only in environment variables
- [ ] Prompt injection protection (for AI)
- [ ] SQL injection protection (ORM used)
- [ ] XSS protection (if applicable)

#### 3. Handling Security Issues

**During task:**
- ✅ **Minor issues** - fix immediately
- ✅ **Medium issues** - add as part of current task
- ✅ **Major issues** - create separate high-priority task

**Prioritization:**

- 🔴 **Critical (P0)** - fix immediately, blocks deploy
- 🟡 **High (P1)** - fix before next release
- 🟢 **Medium (P2)** - fix in next sprint
- ⚪ **Low (P3)** - fix when time permits

### Secrets Management

#### 1. Never in Code

**Prohibited:**

- ❌ Hardcoded secrets in code
- ❌ Secrets in configuration files (committed)
- ❌ Secrets in logs
- ❌ Secrets in error messages

**Allowed:**

- ✅ Environment variables
- ✅ Secret management systems (HashiCorp Vault, AWS Secrets Manager)
- ✅ Encrypted secrets in CI/CD (GitHub Secrets)

#### 2. GitLeaks Verification

**Before every commit:**

```bash
gitleaks protect --source . --verbose --no-banner --staged
```

**Before every push:**

```bash
gitleaks protect --source . --verbose --no-banner
```

### Dependency Management

#### 1. Regular Updates

- ✅ **Composer audit** - before every commit
- ✅ **Dependabot** - automatic updates
- ✅ **Manual review** - critical dependencies

#### 2. Version Pinning

- ✅ **Production** - pinned versions in `composer.lock`
- ✅ **Development** - possible `^` ranges for minor updates

### Input Validation

#### 1. All Inputs

- ✅ Validate length
- ✅ Validate format
- ✅ Sanitize content
- ✅ Type checking

#### 2. AI-Specific

- ✅ Prompt injection detection
- ✅ Length limits
- ✅ Pattern detection
- ✅ Security logging

### Error Handling

#### 1. Without Information Leaks

- ✅ Generic error messages for users
- ✅ Detailed errors only in logs (development)
- ✅ No stack traces in production
- ✅ No file paths in errors

#### 2. Logging

- ✅ Security events always logged
- ✅ No secrets in logs
- ✅ Structured logging
- ✅ Log rotation

---

## 🚨 Incident Management

### Response Procedure

#### 1. Incident Detection

**Sources:**

- Security alerts (GitHub, Dependabot)
- Monitoring alerts
- User reports
- Security audits

#### 2. Risk Assessment

**Criteria:**

- **Critical:** Active exploit, data breach
- **High:** High-risk vulnerability, inactive
- **Medium:** Medium-risk vulnerability
- **Low:** Low risk, informational

#### 3. Response

**Critical:**

1. Immediate impact assessment
2. Temporary block (if possible)
3. Patch/hotfix
4. User communication (if applicable)

**High:**

1. Impact assessment (24h)
2. Remediation plan (48h)
3. Fix implementation (1 week)
4. Follow-up review

**Medium/Low:**

1. Add to backlog
2. Prioritization
3. Standard fix process

### Incident Documentation

**Template:**

```markdown
# Security Incident - YYYY-MM-DD

## Incident Details
- **Date:** YYYY-MM-DD HH:MM
- **Severity:** Critical/High/Medium/Low
- **Type:** [Vulnerability/Data Breach/DDoS/etc.]
- **Status:** Open/Investigating/Fixed/Closed

## Description
[Incident description]

## Impact
- **Affected Systems:** [list]
- **Data Affected:** [if applicable]
- **Users Affected:** [if applicable]

## Timeline
- YYYY-MM-DD HH:MM - Discovery
- YYYY-MM-DD HH:MM - Assessment
- YYYY-MM-DD HH:MM - Remediation started
- YYYY-MM-DD HH:MM - Remediation completed

## Root Cause
[Cause analysis]

## Remediation
[Fix description]

## Prevention
[Preventive measures]

## Lessons Learned
[Conclusions]
```

### Post-Incident Review

**After every incident:**

1. Post-mortem meeting (48h after)
2. Document lessons learned
3. Update procedures
4. Follow-up audit (if applicable)

---

## 📊 Security Metrics

### Key Metrics

1. **Vulnerability Metrics**
   - Number of vulnerabilities found (Critical/High/Medium/Low)
   - Time to remediation (MTTR)
   - Security test coverage

2. **Audit Metrics**
   - Audit frequency
   - Number of findings per audit
   - Trend of findings over time

3. **Pipeline Metrics**
   - Number of security checks in pipeline
   - Pass rate of security checks
   - Security pipeline execution time

4. **Incident Metrics**
   - Number of incidents
   - Response time (MTTR)
   - Remediation time

### Security Score

**Proposed scoring system:**

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

---

## 🔗 Related Documents

- [`SECURITY.md`](../../../SECURITY.md) - Security Policy
- [`PROMPT_INJECTION_SECURITY_ANALYSIS.md`](./PROMPT_INJECTION_SECURITY_ANALYSIS.md) - Detailed prompt injection analysis
- [`docs/knowledge/reference/MANUAL_TESTING_GUIDE.md`](../reference/MANUAL_TESTING_GUIDE.md) - Manual testing guide
- [OWASP Top 10](https://owasp.org/Top10/) - OWASP Top 10
- [OWASP LLM Top 10](https://owasp.org/www-project-llm-top-10/) - OWASP LLM Top 10
- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/) - Application Security Verification Standard

---

## 📌 Notes

- Document is living and will be updated as the application evolves
- Regular document reviews (every 3 months)
- Integration with development lifecycle process
- Security-first mindset for entire team

---

**Last Updated:** 2025-01-10

**Next Review:** 2025-04-10

