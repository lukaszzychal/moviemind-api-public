# Engineering Guidelines

> **Source:** Migrated from `.cursor/rules/old/coding-standards.mdc`, `.cursor/rules/old/philosophy.mdc`, `.cursor/rules/old/dont-do.mdc`  
> **Category:** reference

## Philosophy: Pragmatic approach

- Principles are tools, not goals in themselves
- Readability > Conciseness
- Simple code is better than "perfect" code following all principles
- Don't apply principles forcefully

## 💡 Key principles in a nutshell

1. **TDD** - Test before code, always
2. **Tools** - Pint, PHPStan, tests before commit
3. **SOLID** - Apply pragmatically, not forcefully
4. **DRY** - Remove duplication, but don't overdo it
5. **Readability** - Code must be understandable to others
6. **Refactoring** - When code is hard to maintain
7. **Security** - Always check secrets before commit

## Philosophy

**Principles are tools, not goals in themselves.**

- Code must be **readable** and **understandable**
- Apply principles **pragmatically**, not fanatically
- **Simplicity** is better than excessive abstraction
- **Readability** is more important than "perfect" architecture

## KISS (Keep It Simple, Stupid)

- **Simplicity over complexity** - choose simpler solutions
- **Don't overdo abstraction** - sometimes simple code is better
- **Avoid premature optimization** - working code first, then optimization
- **Readability > Performance** (in most cases)
- **The simplest solution that works** is the best

## SOLID (apply pragmatically)

- **SRP**: One class = one responsibility
- **OCP**: Open for extension, closed for modification
- **LSP**: Subclasses must be replaceable
- **ISP**: Specific interfaces, not general ones
- **DIP**: Depend on abstractions, not concrete implementations

## DRY (Don't Repeat Yourself)

- Refactor duplication when it occurs in 3+ places
- Don't overdo abstraction - sometimes duplication is more readable
- Refactor only when it makes practical sense

## CUPID

- **C**omposable - easy to compose
- **U**nix philosophy - does one thing well
- **P**redictable - predictable
- **I**diomatic - follows Laravel conventions
- **D**omain-based - domain-based

## Architecture Choice

### Principle: Start Simple, Scale When Needed

**Start with a simple solution, add complexity only when justified.**

### Simple solutions (Transaction Scripts)

**Use when:**
- ✅ Small to medium project (MVP, prototypes)
- ✅ Simple business logic (CRUD, simple operations)
- ✅ Small team (1-3 people)
- ✅ Fast development and delivery
- ✅ Low risk of requirement changes
- ✅ Simple domain (doesn't require complex business logic)

**Examples:**
- Laravel Controllers with direct access to Models
- Simple Services with business logic
- Repository pattern (if needed)
- Minimal abstraction

**Advantages:**
- Fast development
- Easy to understand
- Low maintenance costs (initially)
- Fast iteration

### Advanced architectures (DDD, Hexagonal, Clean Architecture)

**Use when:**
- ✅ Large, complex project
- ✅ Complex business logic (many rules, states)
- ✅ Multiple teams working in parallel
- ✅ Long project lifespan (5+ years)
- ✅ High risk of requirement changes
- ✅ Complex domain (domain experts, rich domain model)
- ✅ High testability and isolation required
- ✅ Need for multiple ports (API, CLI, Web, Events)

**Examples:**
- Domain-Driven Design (DDD) - Entities, Value Objects, Domain Services
- Hexagonal Architecture - Ports & Adapters
- Clean Architecture - layers with dependency rules
- CQRS + Event Sourcing (when needed)

**Advantages:**
- High testability
- Better separation of responsibilities
- Easier to add new features
- Framework independence
- Long-term maintainability

**Disadvantages:**
- More code (boilerplate)
- Slower initial development
- More abstraction (can be harder to understand)
- Higher initial costs

### Migration: Simple → Advanced

**When to refactor to more advanced architecture:**

1. ✅ Code becomes hard to maintain
2. ✅ Difficult to add new features without changing existing ones
3. ✅ Tests are hard to write
4. ✅ Lots of business logic duplication
5. ✅ Team grows and needs better organization
6. ✅ Domain becomes more complex
7. ✅ Framework independence is required

**When NOT to refactor:**
- ❌ "For the principle" - without a concrete problem
- ❌ When code works well and is easy to maintain
- ❌ When project is small and simple
- ❌ When there's no time/resources for refactoring

### Recommendations for MovieMind API

**Current state:**
- Simple architecture with Services, Repositories, Controllers
- Event-Driven for asynchronous operations (Jobs)
- Laravel conventions

**When to consider DDD/Hexagonal:**
- When business logic becomes very complex
- When there will be a need for many different ports (Web, CLI, Queue, Events)
- When team grows and better separation is needed
- When independence from Laravel is needed (e.g. shared kernel)

**Principle:**
**Start simple, refactor when concrete problems arise, not "just in case".**

## GRASP (General Responsibility Assignment Software Patterns)

GRASP patterns help assign responsibilities in object-oriented design:

- **Information Expert** - assign responsibility to the class with the information needed
- **Creator** - assign creation responsibility to a class that uses/contains the object
- **Controller** - assign handling responsibility to a class representing the system/facade
- **Low Coupling** - minimize dependencies between classes
- **High Cohesion** - keep related functionality together
- **Polymorphism** - use polymorphism for variations in behavior
- **Pure Fabrication** - create classes that don't represent domain concepts (e.g., Repository)
- **Indirection** - use intermediate objects to decouple classes
- **Protected Variations** - protect against variations by encapsulating them

## YAGNI (You Aren't Gonna Need It)

- **Don't add functionality until it's needed** - avoid premature abstraction
- **Solve today's problems, not tomorrow's** - focus on current requirements
- **Refactor when needed** - add complexity only when there's a concrete need

## Code Smells (fix when they hinder work)

### Structural Smells
- **God Class/Method** - class/method does too much → split into smaller classes/methods
- **Long Parameter List** - use DTO/Request object → create parameter object
- **Feature Envy** - method uses more data from another class → move method to that class
- **Data Clumps** - groups of data always together → use Value Object
- **Primitive Obsession** - using primitives instead of objects → use Value Objects
- **Duplicate Code** - same code in multiple places → extract to common method/class
- **Long Method** - method is too long → extract methods
- **Large Class** - class has too many responsibilities → split into smaller classes

### Behavioral Smells
- **Shotgun Surgery** - one change requires many small changes → consolidate related code
- **Divergent Change** - class changes for multiple reasons → split responsibilities
- **Parallel Inheritance Hierarchies** - similar inheritance structures → merge or use composition
- **Lazy Class** - class doesn't do enough → inline or merge with another class
- **Speculative Generality** - code for future use that isn't needed → remove it (YAGNI)

### Dependency Smells
- **Dependency Inversion Violation** - high-level modules depend on low-level → use interfaces/abstractions
- **Tight Coupling** - classes depend too much on each other → introduce interfaces/abstractions
- **Circular Dependency** - classes depend on each other → break the cycle

## Design Patterns (use when appropriate)

### Creational Patterns
- **Factory** - create objects without specifying exact class
- **Builder** - construct complex objects step by step
- **Singleton** - ensure only one instance exists (use sparingly, prefer dependency injection)

### Structural Patterns
- **Repository** - abstract data access layer
- **Adapter** - make incompatible interfaces work together
- **Decorator** - add behavior to objects dynamically
- **Facade** - provide simplified interface to complex subsystem

### Behavioral Patterns
- **Strategy** - define family of algorithms, make them interchangeable
- **Observer** - notify multiple objects about state changes (Laravel Events)
- **Command** - encapsulate requests as objects (Laravel Jobs)
- **Template Method** - define algorithm skeleton, let subclasses fill details

## Architectural Patterns

- **Repository Pattern** - abstract data access (already used in project)
- **Service Layer** - business logic layer (already used in project)
- **Event-Driven** - communicate via events (Laravel Events/Listeners)
- **CQRS** - separate read and write models (when needed)
- **Hexagonal Architecture** - ports and adapters (when needed)
- **Clean Architecture** - dependency rule with layers (when needed)

## Refactoring and Optimization Principles

### When to Refactor
1. ✅ **Code duplication** - same logic in 3+ places
2. ✅ **Dependency violations** - DIP, tight coupling
3. ✅ **Code smells** - God Class, Long Method, etc.
4. ✅ **Hard to test** - difficult to write unit tests
5. ✅ **Hard to extend** - adding features requires many changes
6. ✅ **Performance issues** - identified bottlenecks (measure first!)

### Refactoring During Feature Development
- **Always refactor when adding features** - improve code quality incrementally
- **Fix code smells as you encounter them** - don't accumulate technical debt
- **Apply SOLID/GRASP when making changes** - use principles pragmatically
- **Extract common logic** - use Repository, Service, or Trait when appropriate

### Optimization Guidelines
- **Measure first** - profile before optimizing
- **Optimize bottlenecks** - focus on actual performance issues
- **Don't optimize prematurely** - working code first, optimization second
- **Consider readability** - readable code is often fast enough

## Code Quality Audits

### Ad-Hoc Audits (During Task Execution)

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
3. Apply appropriate fix strategy (see: Problem Fixing Strategy)
4. Document found issues (if they require separate tasks)

### Comprehensive Audits (Planned)

**When to conduct:**
- **Quarterly** (every quarter) - basic code quality audits (4-8 hours)
- **Semi-annually** (every 6 months) - detailed audits with full analysis (1-2 days)
- **Before major releases** - before larger releases (2-3 days)
- **After major refactoring** - after larger refactorings (1 day)

**Scope:**
- Entire application or selected modules
- All aspects of code quality (SOLID, DRY, code smells, testability, performance)
- Architecture and design patterns
- Test coverage and test quality

**Process:**
1. Audit planning (1-2 days before)
2. Conduct audit according to checklist
3. Document found issues
4. Prioritize problems
5. Create tasks for issues requiring fixes
6. Report results

### Problem Fixing Strategy

**Minor problems (fix immediately):**
- Code smells in files affected by current task
- Minor SOLID violations in context of current task
- Code duplication in files affected by task
- Missing type hints in new code
- Formatting (Pint should fix this automatically)
- Minor method refactorings (extract method, rename)

**Action:** Fix immediately, add to commit, document in commit message

**Medium problems (add to current task if time permits):**
- Code smells in related files (not directly affected)
- Refactoring small methods/classes in context of task
- Unifying approach in related files
- Minor SOLID violations in related files

**Action:** If time permits → fix within task, if not → create task with priority 🟡

**Large problems (create new task):**
- Refactoring entire modules
- Architecture redesign
- Large SOLID violations requiring larger changes
- Code smells requiring refactoring of many files
- Performance issues requiring analysis
- Code duplication requiring larger refactoring

**Action:** Always create new task, priority: 🟡 (medium) or 🔴 (high, if blocking)

### Quick Audit Checklist (Ad-Hoc)

During task execution, check:
- [ ] Is code readable and understandable?
- [ ] Are there no obvious code smells (God Class, Long Method)?
- [ ] Are type hints and strict types present?
- [ ] Is there no code duplication in files affected by task?
- [ ] Is dependency injection used correctly?
- [ ] Are tests written (if this is a new feature)?
- [ ] Does PHPStan not report errors?

### Comprehensive Audit Checklist

**SOLID Principles:**
- [ ] Single Responsibility Principle (SRP)
- [ ] Open/Closed Principle (OCP)
- [ ] Liskov Substitution Principle (LSP)
- [ ] Interface Segregation Principle (ISP)
- [ ] Dependency Inversion Principle (DIP)

**Code Quality:**
- [ ] DRY (Don't Repeat Yourself)
- [ ] Code smells (God Class, Long Method, etc.)
- [ ] Testability
- [ ] Readability
- [ ] Type safety

**Architecture:**
- [ ] Separation of concerns
- [ ] Dependency management
- [ ] Design patterns (used appropriately)
- [ ] Performance considerations

**Testing:**
- [ ] Test coverage (minimum 80%)
- [ ] Test quality
- [ ] TDD compliance

### Code Quality Metrics

**Key Metrics:**
- **PHPStan Level** - currently 5, goal: maintain or increase
- **Test Coverage** - goal: minimum 80%
- **Code Smells** - number of found code smells
- **SOLID Violations** - number of SOLID principle violations
- **Duplication** - percentage of duplicated code
- **Cyclomatic Complexity** - average cyclomatic complexity of methods

**Reporting:**
- Report after each comprehensive audit
- Tracking trends over time
- Comparison with previous audits

### Related Documentation

- [Code Quality Audits Guide](../technical/CODE_QUALITY_AUDITS_GUIDE.md) - Comprehensive guide
- [Security Audits Guide](../technical/APPLICATION_SECURITY_COMPREHENSIVE_GUIDE.md) - Security audits
- [Refactoring Proposals](../technical/REFACTORING_PROPOSAL.md) - Refactoring proposals
- [Code Quality Tools](./CODE_QUALITY_TOOLS.md) - Code quality tools

## Coding standards

- **PSR-12** - formatting (enforced by Pint)
- **Laravel Conventions** - Laravel conventions
- **Type hints** - always use types
- **Strict types** - `declare(strict_types=1);` in PHP files
- **Return types** - always specify return type
- **Repository Pattern** - use Repository for data access, not direct Model queries in Jobs/Services
- **Dependency Injection** - prefer constructor injection, use service container

## 🚫 What NOT to do

### Prohibited list

1. ❌ Commit without tests
2. ❌ Ignore failing tests
3. ❌ Skip code quality tools (Pint, PHPStan, tests)
4. ❌ Commit secrets (always check GitLeaks)
5. ❌ Commit debug code (`dd()`, `dump()`, `var_dump()`)
6. ❌ Create too large commits (one commit = one logical change)
7. ❌ Apply principles forcefully (don't create excessive abstractions)
8. ❌ Ignore code smells (fix when they hinder work)
9. ❌ Create code without thinking about readability
10. ❌ Skip type hints and strict types

### Principle

If something is on the "NOT to do" list, it means it is **prohibited** and should not happen.

## 📚 Additional Resources

- **Tasks:** `docs/issue/TASKS.md` - ⭐ START HERE
- **Detailed rules:** `docs/AI_AGENT_CONTEXT_RULES.md`
- **Tests:** `docs/TESTING_STRATEGY.md`
- **Quality tools:** `docs/CODE_QUALITY_TOOLS.md`
- **Architecture:** `docs/ARCHITECTURE_ANALYSIS.md`
- **Project context:** `CLAUDE.md`
- **Cursor explanation:** `docs/CURSOR_RULES_EXPLANATION.md`

