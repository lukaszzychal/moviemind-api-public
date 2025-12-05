# Code Quality Audits Guide

> **Created:** 2025-01-27  
> **Context:** Creating a comprehensive guide for code quality audits, refactoring, and application redesign  
> **Category:** technical

## 🎯 Purpose

This document defines a systematic approach to code quality audits, refactoring, and application redesign for MovieMind API. It contains principles, processes, and workflows for maintaining high code quality.

## 📋 Audit Types

### Ad-Hoc Audits

**When to conduct:**
- During task execution (when encountering code quality issues)
- During code review
- When noticing code smells or principle violations
- After encountering testing or maintenance problems

**Scope:**
- Files affected by current task
- Related files (if problem is visible)
- Specific issues (code smells, duplication, SOLID violations)

**Duration:**
- 15-30 minutes for small audits
- 1-2 hours for larger audits

**Process:**
1. Identify code quality problem
2. Assess problem size (minor/medium/large)
3. Apply appropriate fix strategy (see: Problem Fixing Workflow)
4. Document found issues (if they require separate tasks)

### Comprehensive Audits (Planned)

**When to conduct:**
- **Quarterly** (every quarter) - basic code quality audits
- **Semi-annually** (every 6 months) - detailed audits with full analysis
- **Before major releases** - before larger releases
- **After major refactoring** - after larger refactorings

**Scope:**
- Entire application or selected modules
- All aspects of code quality (SOLID, DRY, code smells, testability, performance)
- Architecture and design patterns
- Test coverage and test quality

**Duration:**
- Quarterly: 4-8 hours
- Semi-annually: 1-2 days
- Before major releases: 2-3 days
- After major refactoring: 1 day

**Process:**
1. Audit planning (1-2 days before)
2. Conduct audit according to checklist
3. Document found issues
4. Prioritize problems
5. Create tasks for issues requiring fixes
6. Report results

## 📊 Code Quality Audit Checklist

### SOLID Principles

- [ ] **Single Responsibility Principle (SRP)**
  - Each class has one responsibility
  - No "God Classes" (classes doing too much)
  - Methods are focused on one task

- [ ] **Open/Closed Principle (OCP)**
  - Classes are open for extension, closed for modification
  - Abstractions are used (interfaces, abstract classes)
  - No direct modifications of existing code when adding features

- [ ] **Liskov Substitution Principle (LSP)**
  - Subclasses can replace base classes without changing behavior
  - Interface contracts are followed
  - No contract violations in inheritance hierarchies

- [ ] **Interface Segregation Principle (ISP)**
  - Interfaces are specific, not general
  - Classes don't implement methods they don't use
  - Interfaces are split into smaller, more specific ones

- [ ] **Dependency Inversion Principle (DIP)**
  - High-level modules don't depend on low-level modules
  - Abstractions (interfaces) are used instead of concrete implementations
  - Dependency Injection is used consistently

### Code Quality

- [ ] **DRY (Don't Repeat Yourself)**
  - No code duplication (check if duplication occurs in 3+ places)
  - Common logic is extracted to methods/classes
  - No excessive abstraction (YAGNI)

- [ ] **Code Smells**
  - No "God Classes" (too large classes)
  - No "Long Methods" (too long methods)
  - No "Long Parameter Lists" (DTO/Request objects are used)
  - No "Feature Envy" (methods use data from other classes)
  - No "Data Clumps" (Value Objects are used)
  - No "Primitive Obsession" (Value Objects are used instead of primitives)
  - No "Shotgun Surgery" (one change requires many small changes)
  - No "Divergent Change" (class changes for multiple reasons)

- [ ] **Testability**
  - Code is easy to test
  - Dependency injection is used
  - No tight coupling
  - Methods are isolated and testable

- [ ] **Readability**
  - Code is readable and understandable
  - Variable/method/class names are descriptive
  - Comments explain "why", not "what"
  - Formatting is consistent (Pint)

- [ ] **Type Safety**
  - All parameters and return values have type hints
  - `declare(strict_types=1);` is used
  - No use of `mixed` (where possible)
  - PHPStan level 5+ without errors

### Architecture

- [ ] **Separation of Concerns**
  - Controllers only route requests
  - Business logic in Services
  - Data access in Repositories
  - No business logic in Models (except accessors/mutators)

- [ ] **Dependency Management**
  - Interfaces are used instead of concrete classes
  - Dependency Injection is consistent
  - No service location (except Jobs, where method injection)
  - No circular dependencies

- [ ] **Design Patterns**
  - Patterns are used appropriately (not forced)
  - Repository Pattern for data access
  - Service Layer for business logic
  - Event-Driven for asynchronous operations
  - Factory/Builder when needed

- [ ] **Performance Considerations**
  - N+1 queries are avoided (eager loading)
  - Cache is used appropriately
  - Query optimization (indexes, where clauses)
  - No premature optimization

### Testing

- [ ] **Test Coverage**
  - Minimum 80% test coverage
  - All new features have tests
  - Feature Tests for API endpoints
  - Unit Tests for business logic

- [ ] **Test Quality**
  - Tests are readable and understandable
  - Tests test behavior, not implementation (Chicago School)
  - No excessive mocks (only external APIs)
  - Tests are fast and isolated

- [ ] **TDD Compliance**
  - New features are created with TDD (Red-Green-Refactor)
  - Tests are written before implementation
  - All tests pass

## 🔄 Problem Fixing Workflow

### During Task Execution

**1. Encountering code quality problem:**
   - Assess problem size (minor/medium/large)
   - Check if it relates to current task
   - Apply appropriate strategy (fix vs task)

**2. Minor problems (fix immediately):**
   - Code smells in files affected by current task
   - Minor SOLID violations in context of current task
   - Code duplication in files affected by task
   - Missing type hints in new code
   - Formatting (Pint should fix this automatically)
   - Minor method refactorings (extract method, rename)

   **Action:**
   - Fix immediately
   - Add to commit (if relates to current task)
   - Document in commit message (e.g., "refactor: extract method for clarity")

**3. Medium problems (add to current task if time permits):**
   - Code smells in related files (not directly affected)
   - Refactoring small methods/classes in context of task
   - Unifying approach in related files
   - Minor SOLID violations in related files

   **Action:**
   - If time permits → fix within task
   - If no time → create task with priority 🟡 (medium)
   - Add to `docs/issue/pl/TASKS.md`

**4. Large problems (create new task):**
   - Refactoring entire modules
   - Architecture redesign
   - Large SOLID violations requiring larger changes
   - Code smells requiring refactoring of many files
   - Performance issues requiring analysis
   - Code duplication requiring larger refactoring

   **Action:**
   - Always create new task
   - Priority: 🟡 (medium) or 🔴 (high, if blocking)
   - Add to `docs/issue/pl/TASKS.md`
   - Describe problem, location, and proposed solution

### Decision Examples

**Example 1: Minor problem**
- **Situation:** While adding new method in `MovieService`, noticed that `generateSlug()` method is too long (50 lines)
- **Decision:** Fix immediately - extract logic to smaller methods
- **Action:** Refactoring within current commit

**Example 2: Medium problem**
- **Situation:** While working on `MovieController`, noticed that `PersonController` has similar logic (duplication)
- **Decision:** If time permits → fix within task, if not → create task
- **Action:** Create task "Refactor: Extract common logic from PersonController and MovieController"

**Example 3: Large problem**
- **Situation:** During audit, noticed that entire `Jobs` module has problems with dependency injection (service location)
- **Decision:** Create new task
- **Action:** Create task "Refactor: Replace service location with method injection in Jobs" with priority 🟡

## 📈 Code Quality Metrics

### Key Metrics

- **PHPStan Level** - currently 5, goal: maintain or increase
- **Test Coverage** - goal: minimum 80%
- **Code Smells** - number of found code smells
- **SOLID Violations** - number of SOLID principle violations
- **Duplication** - percentage of duplicated code
- **Cyclomatic Complexity** - average cyclomatic complexity of methods

### Reporting

- Report after each comprehensive audit
- Tracking trends over time
- Comparison with previous audits
- Metrics visualization (if possible)

### Metrics Tools

- **PHPStan** - static analysis, level 5
- **PHPUnit** - test coverage
- **Laravel Pint** - code formatting
- **Manual review** - code smells, SOLID violations

## 🔗 Integration with Existing Processes

### Code Review

- Checking compliance with code quality principles
- Detecting code smells
- Verifying SOLID principles
- Suggesting refactoring when needed

### Pre-Commit

- **Pint** (formatting) - already exists
- **PHPStan** (static analysis) - already exists
- **Tests** - already exist
- **GitLeaks** (secrets) - already exists

### CI/CD Pipeline

- Adding optional code quality checks
- Reporting quality metrics
- Warnings about code smells (non-blocking)

## 📝 Audit Report Template

```markdown
# Code Quality Audit Report - YYYY-MM-DD

## Executive Summary
- Audit Date: YYYY-MM-DD
- Scope: [Comprehensive/Partial]
- Issues Found: X (Critical: Y, High: Z, Medium: W, Low: V)

## Findings

### Critical (P0)
- [Issue 1]
  - Description: [Problem description]
  - Location: [File, line]
  - Recommendation: [Fix recommendation]
  - Status: [Open/In Progress/Resolved]

### High (P1)
- [Issue 2]
  ...

### Medium (P2)
- [Issue 3]
  ...

### Low (P3)
- [Issue 4]
  ...

## SOLID Principles Review
- SRP: ✅/⚠️/❌ [Comment]
- OCP: ✅/⚠️/❌ [Comment]
- LSP: ✅/⚠️/❌ [Comment]
- ISP: ✅/⚠️/❌ [Comment]
- DIP: ✅/⚠️/❌ [Comment]

## Code Quality Metrics
- PHPStan Level: 5
- Test Coverage: X%
- Code Smells: X
- Duplication: X%
- Cyclomatic Complexity: X (average)

## Recommendations
1. [Recommendation 1]
2. [Recommendation 2]
3. [Recommendation 3]

## Action Items
- [ ] Task 1: [Task description]
- [ ] Task 2: [Task description]
- [ ] Task 3: [Task description]

## Next Audit
- Scheduled: YYYY-MM-DD
- Type: [Quarterly/Semi-annually/Before Release]
```

## 🚀 Workflow/Pipeline for Audits

### Implementation Options

**Option A: Separate Workflow (Recommended)**
- Create `.github/workflows/code-quality-audit.yml`
- Manual trigger (workflow_dispatch)
- Scheduled runs (quarterly/semi-annually)
- Code quality metrics reporting

**Advantages:**
- Separation from other workflows
- Easy manual triggering
- Clear metrics and reports
- Possibility of integration with external tools

**Disadvantages:**
- Additional workflow to maintain
- Requires configuration

**Option B: Integrated with Existing Workflows**
- Add jobs to existing workflows (e.g., `ci.yml`)
- Automatic triggering on every PR

**Advantages:**
- Fewer files to maintain
- Automatic triggering

**Disadvantages:**
- May be less readable
- May slow down CI pipeline

**Option C: Manual Audits Only**
- Documentation + checklist
- Manual audit execution

**Advantages:**
- Simplicity
- Flexibility

**Disadvantages:**
- No automation
- Possibility of skipping audits

### Recommendation

**Option A (Separate Workflow)** for comprehensive audits + manual ad-hoc audits.

**Rationale:**
- Comprehensive audits require more time and shouldn't block CI
- Ad-hoc audits are ad-hoc and don't require automation
- Separate workflow allows flexible scheduling
- Clear reporting and metrics

## 📚 Related Documents

- [Code Writing Standards](../../.cursor/rules/coding-standards.mdc) - Code writing principles
- [Security Audits Guide](./APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) - Security audits guide
- [Refactoring Proposals](./REFACTORING_PROPOSAL.md) - Refactoring proposals
- [Code Quality Tools](../reference/CODE_QUALITY_TOOLS.md) - Code quality tools
- [Testing Strategy](../reference/TESTING_STRATEGY.md) - Testing strategy

## 🔄 Audit Frequency - Summary

### Ad-Hoc Audits
- **When:** During task execution, code review, when encountering problems
- **Time:** 15-30 minutes (small), 1-2 hours (larger)
- **Scope:** Files affected by task, specific issues

### Comprehensive Audits (Planned)
- **Quarterly** (every quarter): 4-8 hours - basic audits
- **Semi-annually** (every 6 months): 1-2 days - detailed audits
- **Before major releases**: 2-3 days - before larger releases
- **After major refactoring**: 1 day - after larger refactorings

## ✅ Quick Audit Checklist (Ad-Hoc)

During task execution, check:

- [ ] Is code readable and understandable?
- [ ] Are there no obvious code smells (God Class, Long Method)?
- [ ] Are type hints and strict types present?
- [ ] Is there no code duplication in files affected by task?
- [ ] Is dependency injection used correctly?
- [ ] Are tests written (if this is a new feature)?
- [ ] Does PHPStan not report errors?

---

**Last updated:** 2025-01-27

