# Language Priority for Cursor Rules

> **Source:** Migrated from `.cursor/rules/old/language-priority.mdc`  
> **Category:** reference

## Principle

**English is the PRIMARY and REQUIRED language for all files in `.cursor/rules/*.mdc`. Polish versions are maintained in `docs/cursor-rules/pl/` for learning purposes only.**

## Language Hierarchy

1. **`.cursor/rules/*.mdc`** - **MUST be in English** (used by Cursor/Claude AI agents)
2. **`CLAUDE.md`** - **MUST be in English** (used by Cursor/Claude AI agents)
3. **`docs/cursor-rules/pl/*.mdc`** - Polish versions (for learning English, reference)
4. **`docs/CLAUDE.pl.md`** - Polish version (for learning English, reference)

## Why English is Priority

- **AI agents (Cursor/Claude) use ONLY English versions** from `.cursor/rules/` and `CLAUDE.md`
- English is the standard language for code documentation and AI instructions
- Polish versions are maintained in `docs/` for learning purposes and reference

## Rules

### When Creating New Rules

1. **Always create English version first** in `.cursor/rules/*.mdc`
2. **Immediately create Polish translation** in `docs/cursor-rules/pl/*.mdc`
3. **Both versions must exist** - new files require both EN and PL versions

### When Updating Rules

1. **Update English version first** in `.cursor/rules/*.mdc` or `CLAUDE.md`
2. **Immediately update Polish version** in `docs/cursor-rules/pl/` or `docs/CLAUDE.pl.md`
3. **Keep both versions synchronized** - lack of synchronization is treated as a review error

### Language Requirements

- ✅ **English** in `.cursor/rules/*.mdc` - **REQUIRED**
- ✅ **English** in `CLAUDE.md` - **REQUIRED**
- ✅ **Polish** in `docs/cursor-rules/pl/*.mdc` - **REQUIRED** (for learning)
- ✅ **Polish** in `docs/CLAUDE.pl.md` - **REQUIRED** (for learning)

## Documentation Structure

```
.cursor/rules/*.mdc          # English (AI agents use these)
CLAUDE.md                    # English (AI agents use this)
docs/cursor-rules/pl/*.mdc   # Polish (for learning English)
docs/CLAUDE.pl.md            # Polish (for learning English)
docs/*.md                    # Polish (project documentation)
```

## Enforcement

- AI Agent MUST create/update English versions in `.cursor/rules/` first
- AI Agent MUST immediately create/update Polish translations in `docs/cursor-rules/pl/`
- AI Agent MUST ensure both versions exist and are synchronized
- English versions in `.cursor/rules/` take priority - they are what AI agents read
- Polish versions in `docs/` are for human reference and learning

---

**Remember:** English is the source of truth. Polish versions are translations maintained for learning purposes.

