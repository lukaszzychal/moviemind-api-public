# Security Policy for MovieMind API

## Supported Versions

We provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x  | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security vulnerability, please follow these steps:

### 1. Do NOT create a public issue
**Never** report security vulnerabilities through public GitHub issues, discussions, or any other public channels.

### 2. Report privately
Send an email to: **lukasz.zychal.dev@gmail.com**

Include the following information:
- Description of the vulnerability
- Steps to reproduce the issue
- Potential impact assessment
- Any suggested fixes or mitigations

### 3. Response timeline
- **Initial response**: Within 24 hours
- **Status update**: Within 72 hours
- **Resolution**: Within 30 days (depending on complexity)

### 4. What to expect
- We will acknowledge receipt of your report
- We will investigate and validate the vulnerability
- We will provide regular updates on our progress
- We will coordinate the release of fixes

## Security Measures

### Automated Security Scanning
- **Dependabot**: Automatic dependency vulnerability scanning
- **GitLeaks**: Secret and credential detection
- **GitHub Security Advisories**: Integrated security monitoring
- **CodeQL**: Static analysis for security vulnerabilities

### Manual Security Reviews
- **Code Reviews**: All code changes require security review
- **Dependency Audits**: Regular manual dependency reviews
- **Infrastructure Reviews**: Security assessment of deployment infrastructure

### Security Best Practices
- **API Key Management**: Secure storage and rotation of API keys
- **Environment Variables**: Sensitive data in environment variables only
- **HTTPS Only**: All communications encrypted
- **Input Validation**: Comprehensive input sanitization
- **Rate Limiting**: Protection against abuse and DoS attacks
- **Prompt Injection Prevention**: Sanitization and validation of all inputs used in AI prompts

## Security Features

### Authentication & Authorization
- **API Key Authentication**: Secure API key-based authentication
- **Rate Limiting**: Protection against abuse
- **Input Validation**: Comprehensive input sanitization
- **CORS Configuration**: Proper cross-origin resource sharing setup

### Data Protection
- **Encryption at Rest**: Database encryption for sensitive data
- **Encryption in Transit**: HTTPS/TLS for all communications
- **Data Minimization**: Only collect necessary data
- **Secure Storage**: Environment variables for sensitive configuration

### AI Security
- **Prompt Injection Protection**: Comprehensive sanitization of user inputs before using in AI prompts
- **Input Validation**: Detection and blocking of malicious prompt injection attempts
- **Security Logging**: All prompt injection attempts are logged for monitoring
- **Defense in Depth**: Multiple layers of protection (SlugValidator, PromptSanitizer, OpenAiClient)

### Infrastructure Security
- **Container Security**: Secure Docker container configuration
- **Database Security**: PostgreSQL with proper access controls
- **Cache Security**: Redis with authentication and encryption
- **Network Security**: Proper network segmentation

## Security Checklist

### For Contributors
- [ ] No hardcoded secrets or API keys
- [ ] Input validation implemented
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] CSRF protection
- [ ] Prompt injection prevention (for AI-related features)
- [ ] Proper error handling (no sensitive data exposure)
- [ ] Security headers implemented
- [ ] Dependencies up to date

### For Maintainers
- [ ] Security reviews for all changes
- [ ] Regular dependency updates
- [ ] Security monitoring enabled
- [ ] Incident response plan ready
- [ ] Backup and recovery procedures
- [ ] Access control reviews
- [ ] Security training for team

## Known Security Considerations

### API Key Management
- **OpenAI API Keys**: Never commit to repository
- **Database Credentials**: Use environment variables
- **Redis Passwords**: Secure configuration required

### Data Handling
- **User Data**: Minimal data collection
- **Logging**: No sensitive data in logs
- **Caching**: Secure cache configuration

### Third-Party Integrations
- **OpenAI API**: Secure API key handling and prompt injection protection
- **TMDb API**: Input sanitization for external data sources
- **Database Providers**: Secure connection strings
- **Cache Providers**: Authentication and encryption

### Prompt Injection Protection

MovieMind API implements comprehensive protection against prompt injection attacks:

#### What is Prompt Injection?
Prompt injection is a security vulnerability where malicious input is used to manipulate AI behavior, potentially leading to:
- Data exfiltration
- Unauthorized actions
- System compromise
- Bypassing safety guidelines

#### Protection Mechanisms

1. **Input Sanitization** (`PromptSanitizer`):
   - Removal of newlines, carriage returns, and tabs
   - Detection of suspicious patterns (e.g., "ignore previous", "system:", "jailbreak")
   - Length validation
   - Escaping of special characters

2. **Multi-Layer Validation**:
   - `SlugValidator`: Early detection in slug validation
   - `PromptSanitizer`: Comprehensive sanitization before prompt construction
   - `OpenAiClient`: Final sanitization before API calls

3. **Security Logging**:
   - All prompt injection attempts are logged with context
   - Monitoring and alerting for suspicious patterns
   - IP address and user agent tracking

4. **Defense in Depth**:
   - Multiple validation layers
   - Fail-safe defaults
   - Comprehensive test coverage

#### Implementation Details

- **Slug Sanitization**: All user-provided slugs are sanitized before use in AI prompts
- **TMDb Data Sanitization**: External data from TMDb API is sanitized to prevent injection through compromised sources
- **Pattern Detection**: Advanced pattern matching detects common injection techniques
- **Error Handling**: Malicious inputs are rejected with clear error messages

For more details, see:
- [`docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md`](docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md)
- [`api/app/Services/PromptSanitizer.php`](api/app/Services/PromptSanitizer.php)

## Comprehensive Security Documentation

For detailed security information, procedures, and best practices, see:
- [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) - Comprehensive security guide covering:
  - OWASP Top 10 mapping
  - OWASP LLM Top 10 for AI security
  - Security audit procedures (ad-hoc and comprehensive)
  - CI/CD security pipeline
  - Best practices and incident management

## Security Updates

### Regular Updates
- **Dependencies**: Weekly security updates via Dependabot
- **Base Images**: Regular Docker image updates
- **Security Patches**: Immediate critical security patches

### Emergency Updates
- **Critical Vulnerabilities**: Immediate patches
- **Zero-Day Exploits**: Emergency response procedures
- **Security Incidents**: Incident response protocol

## Compliance

### Data Protection
- **GDPR Compliance**: European data protection regulations
- **Data Minimization**: Only collect necessary data
- **Right to Deletion**: User data deletion capabilities
- **Data Portability**: Export user data capabilities

### Security Standards
- **OWASP Top 10**: Protection against common vulnerabilities
- **Security Headers**: Implementation of security headers
- **Secure Coding**: Following secure coding practices

## Contact Information

### Security Team
- **Email**: lukasz.zychal.dev@gmail.com
- **Response Time**: 24 hours
- **Escalation**: For urgent issues, mark email as "URGENT"

### General Support
- **Issues**: [GitHub Issues](https://github.com/lukaszzychal/moviemind-api-public/issues)
- **Discussions**: [GitHub Discussions](https://github.com/lukaszzychal/moviemind-api-public/discussions)

## Acknowledgments

We appreciate the security research community and responsible disclosure. Security researchers who report vulnerabilities will be acknowledged (with permission) in our security advisories.

---

**Last Updated**: January 2025  
**Next Review**: July 2025

## Security Audits

### Audit Schedule

**Ad-hoc Security Reviews:**
- During code review or when implementing new features
- Quick security checklist verification
- Best practices check

**Comprehensive Security Audits:**
- **Quarterly** (every 3 months) - basic audits
- **Semi-annually** (every 6 months) - detailed audits
- **Before major releases** - pre-release audits
- **After security incidents** - post-incident audits

For detailed audit procedures, see:
- [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md#security-audits)

## Recent Security Updates

### January 2025
- **Comprehensive Security Documentation**: Created comprehensive security guide
  - OWASP Top 10 mapping to current implementation
  - OWASP LLM Top 10 for AI security
  - Security audit procedures and schedules
  - CI/CD security pipeline documentation
  - Best practices and incident management procedures
  - See [`docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md`](docs/knowledge/technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) for details

- **Prompt Injection Protection**: Implemented comprehensive protection against prompt injection attacks
  - Added `PromptSanitizer` service for input sanitization
  - Extended `SlugValidator` with injection detection
  - Integrated sanitization in `OpenAiClient`
  - Added security tests for prompt injection scenarios
  - See [`docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md`](docs/knowledge/technical/PROMPT_INJECTION_SECURITY_ANALYSIS.md) for details
