# Cursor Rules - Documentation

> **Note:** This directory contains Polish versions of Cursor rules for learning purposes.  
> Cursor/Claude uses only English versions from `.cursor/rules/*.mdc` and `CLAUDE.md`.

## 📁 Structure

- **`.cursor/rules/*.mdc`** - English versions (used by Cursor/Claude)
- **`CLAUDE.md`** - English version (used by Cursor/Claude)
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

**Last updated:** 2025-11-12
