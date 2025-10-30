# Code Quality Tools in CI Pipeline

## Overview
This document explains which code quality tools are used in the CI pipeline and why.

## Tools Used

### ✅ Laravel Pint
- **Purpose**: Code formatting (PSR-12 standard)
- **Why**: Laravel's official code formatter, simple and fast
- **Alternatives**: php-cs-fixer (not needed - Pint is built on top of it)
- **Status**: ✅ Active in CI

### ✅ PHPStan (with Larastan)
- **Purpose**: Static analysis - finds bugs without running code
- **Level**: 5 (moderate strictness, good balance)
- **Why**: Catches type errors, undefined methods, null pointer exceptions
- **Status**: ✅ Active in CI

### ✅ Composer Audit
- **Purpose**: Security vulnerability scanning for dependencies
- **Why**: Identifies known CVEs in composer packages
- **Status**: ✅ Active in CI

### ✅ GitLeaks
- **Purpose**: Detects secrets, API keys, passwords in code
- **Why**: Prevents accidental commit of sensitive data
- **Status**: ✅ Active in CI

## Tools NOT Used (and why)

### ❌ php-cs-fixer
- **Reason**: Laravel Pint is built on php-cs-fixer and provides a simpler Laravel-optimized interface
- **Verdict**: No need - Pint is sufficient

### ❌ Psalm
- **Reason**: Overlaps with PHPStan - both do static analysis
- **Comparison**:
  - PHPStan: Better Laravel support (via Larastan), wider adoption
  - Psalm: More strict by default, different approach
- **Verdict**: PHPStan + Larastan is enough for Laravel projects

### ❌ PHPUnit + Pest
- **Note**: PHPUnit is used for testing (not code quality)
- **Pest**: Available but not configured - PHPUnit works fine

## Recommendation: PHPStan vs Psalm

### Should you use both?
**NO** - They serve the same purpose and would create:
- Duplicate work (fixing same issues twice)
- Conflicting rules
- Slower CI pipeline

### Choose ONE:
- **PHPStan + Larastan** ✅ (recommended for Laravel)
  - Better Laravel framework understanding
  - Laravel-specific checks (Eloquent, Facades, etc.)
  - More widely adopted in Laravel community

- **Psalm** (alternative)
  - More strict by default
  - Different error detection approach
  - Less Laravel-specific support

## Current CI Pipeline

```yaml
security:
  - Composer audit     # Security vulnerabilities
  - Laravel Pint       # Code formatting
  - PHPStan           # Static analysis
  - GitLeaks          # Secrets detection
```

## Best Practices

1. **Run Pint before commit**: `vendor/bin/pint`
2. **Check PHPStan locally**: `vendor/bin/phpstan analyse`
3. **Fix issues incrementally**: Start with level 5, gradually increase
4. **Don't ignore all errors**: Use `@phpstan-ignore` sparingly

## Level Guide

- **Level 0**: Basic checks (anyone can start)
- **Level 3**: Most code works without issues
- **Level 5**: ✅ Current - good balance
- **Level 7**: Very strict (enterprise projects)
- **Level 9**: Maximum strictness (rarely achievable)

