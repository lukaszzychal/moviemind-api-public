# Cursor Rules - Documentation

> **Note:** This directory contains Polish versions of Cursor rules for learning purposes.  
> Cursor/Claude uses only English versions from `.cursor/rules/*.mdc` and `CLAUDE.md`.

## 🎯 Language Priority

**English is the PRIMARY and REQUIRED language for all files in `.cursor/rules/*.mdc`.**

- **Priority:** English versions in `.cursor/rules/` are the source of truth
- **Purpose:** AI agents (Cursor/Claude) read only English versions
- **Polish versions:** Maintained in `docs/cursor-rules/pl/` for learning purposes

See `.cursor/rules/language-priority.mdc` for detailed rules.

## 📁 Structure

- **`.cursor/rules/*.mdc`** - English versions (used by Cursor/Claude) - **PRIORITY**
- **`CLAUDE.md`** - English version (used by Cursor/Claude) - **PRIORITY**
- **`docs/cursor-rules/pl/*.mdc`** - Polish versions (for learning English)
- **`docs/CLAUDE.pl.md`** - Polish version (for learning English)

## 🔄 Synchronization

When updating English versions in `.cursor/rules/*.mdc` or `CLAUDE.md`:

1. **Update English version first** (in `.cursor/rules/` or root)
2. **Immediately update Polish version** (in `docs/cursor-rules/pl/` or `docs/CLAUDE.pl.md`)
3. **Keep both versions synchronized** - lack of synchronization is treated as a review error

When adding a new file in `.cursor/rules/*.mdc`:

1. **Create English version first** (in `.cursor/rules/`)
2. **Immediately create Polish version** (in `docs/cursor-rules/pl/` with the same filename)
3. **Both versions must exist** - new files require both EN and PL versions

## 📝 Purpose

Polish versions are maintained for:
- Learning English by comparing translations
- Reference for Polish-speaking developers
- Documentation completeness

**Important:** Cursor/Claude AI agents use **only English versions** from `.cursor/rules/*.mdc` and `CLAUDE.md`.

---

## 🔗 Related Documents

- [Language Priority Rules](../.cursor/rules/language-priority.mdc) - Detailed rules about English priority
- [Workflow Rules](../.cursor/rules/workflow.mdc) - Pre-commit workflow and guidelines

---

**Last updated:** 2025-12-06
