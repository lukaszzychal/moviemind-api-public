# Breaking Change Detection and Impact Analysis

> **Source:** Migrated from `.cursor/rules/old/breaking-change-detection.mdc`  
> **Category:** reference

## Principle

**Treat all changes as if they were being deployed to production with full data.**

Before making any change that could potentially:
- Damage existing data in the database
- Break existing functionality
- Change API contracts
- Modify database schema in incompatible ways
- Remove or rename fields/columns
- Change behavior of existing methods

**STOP and perform impact analysis.**

## Required Analysis

### 1. Identify Breaking Changes

Check if the change:
- Modifies database schema (migrations, columns, constraints)
- Changes model relationships or methods
- Alters API request/response formats
- Removes or renames fields/columns
- Changes behavior of existing methods
- Modifies validation rules
- Changes slug generation/parsing logic
- Alters job processing logic

### 2. Impact Assessment

For each identified breaking change:
- **Data Impact:** Will existing data be affected? How?
- **API Impact:** Will API consumers be affected? Which endpoints?
- **Functionality Impact:** Will existing features break? Which ones?
- **Migration Impact:** Can data be migrated safely? How?

### 3. Alternatives Analysis

Document:
- Alternative approaches that avoid breaking changes
- Backward compatibility solutions
- Gradual migration strategies
- Feature flags for safe rollout

### 4. Safe Change Process

If breaking change is necessary, provide:
- Database migration strategy (nullable columns, default values, data migration)
- Backward compatibility layer (if possible)
- API versioning strategy (if needed)
- Rollback plan
- Testing strategy (existing data, edge cases)

## Workflow

1. **Before making changes:**
   - Analyze if change is breaking
   - If breaking → STOP
   - Document impact analysis
   - Propose alternatives
   - Get approval or confirmation

2. **If breaking change is approved:**
   - Create migration plan
   - Implement backward compatibility (if possible)
   - Add comprehensive tests
   - Update documentation
   - Create rollback strategy

3. **Examples of breaking changes:**
   - Adding NOT NULL constraint to existing column
   - Removing column from table
   - Changing column type without migration
   - Removing or renaming model method
   - Changing API response format
   - Modifying slug generation logic (affects existing slugs)
   - Changing validation rules (may reject existing data)

## Exceptions

Changes that are NOT breaking:
- Adding new nullable columns
- Adding new optional API fields
- Adding new methods (without removing old ones)
- Adding new features (without modifying existing)
- Bug fixes that restore intended behavior

## Enforcement

- AI Agent MUST analyze changes before implementation
- AI Agent MUST stop and inform user if breaking change detected
- AI Agent MUST provide impact analysis and alternatives
- AI Agent MUST propose safe change process before proceeding

