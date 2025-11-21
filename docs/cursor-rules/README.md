# Cursor Rules - Documentation

> **Note:** This directory contains English versions of Cursor rules for learning purposes.  
> Cursor/Claude uses only Polish versions from `.cursor/rules/*.mdc` and `CLAUDE.md`.

## 📁 Structure

- **`.cursor/rules/*.mdc`** - Polish versions (used by Cursor/Claude)
- **`CLAUDE.md`** - Polish version (used by Cursor/Claude)
- **`docs/cursor-rules/pl/*.mdc`** - English versions (for learning English)
- **`docs/CLAUDE.pl.md`** - English version (for learning English)

## 🔄 Synchronization

When updating Polish versions in `.cursor/rules/*.mdc` or `CLAUDE.md`:

1. **Update Polish version first** (in `.cursor/rules/` or root)
2. **Immediately update English version** (in `docs/cursor-rules/pl/` or `docs/CLAUDE.pl.md`)
3. **Keep both versions synchronized** - lack of synchronization is treated as a review error

When adding a new file in `.cursor/rules/*.mdc`:

1. **Create Polish version first** (in `.cursor/rules/`)
2. **Immediately create English version** (in `docs/cursor-rules/pl/` with the same filename)
3. **Both versions must exist** - new files require both PL and EN versions

## 📝 Purpose

English versions are maintained for:
- Learning English by comparing translations
- Reference for English-speaking developers
- Documentation completeness

**Important:** Cursor/Claude AI agents use **only Polish versions** from `.cursor/rules/*.mdc` and `CLAUDE.md`.

---

**Last updated:** 2025-11-12
