# Markdown Formatting Standards

> **Source:** Migrated from `.cursor/rules/old/markdown-formatting.mdc`  
> **Category:** reference

## Principle

**All Markdown files must follow consistent formatting standards before commit.**

## Required Tools

- **markdownlint-cli2** - Primary tool for linting and auto-fixing Markdown files
- **Configuration:** `.markdownlint.json` in project root

## Installation

**Recommended:** Install via npm (local dependency):

```bash
# Install dependencies (markdownlint-cli2 is in package.json)
npm install

# Or use setup script
./scripts/setup-markdownlint.sh
```

**Alternative:** Global installation:

```bash
npm install -g markdownlint-cli2
```

The pre-commit hook will automatically use `npx` if local installation is available, or fall back to global installation.

## Pre-Commit Workflow

**Before each commit:**

1. **Automatic formatting** - Pre-commit hook automatically fixes Markdown files:
   ```bash
   markdownlint-cli2-fix '**/*.md'
   ```

2. **Manual check** (if needed):
   ```bash
   npm run markdownlint:check
   # or
   npx markdownlint-cli2 '**/*.md'
   ```

3. **Auto-fix specific files**:
   ```bash
   npm run markdownlint:fix
   # or
   npx markdownlint-cli2-fix docs/issue/pl/TASKS.md
   ```

## Common Rules

The project uses `.markdownlint.json` with these key rules:

- **MD022** - Headings must be surrounded by blank lines
- **MD032** - Lists must be surrounded by blank lines
- **MD029** - Ordered list numbering must be consistent (1, 2, 3...)
- **MD009** - Trailing spaces (allowed 2 spaces for line breaks)
- **MD026** - No trailing punctuation in headings (except `:`, `.`, `,`, `;`, `!`)
- **MD013** - Line length: 120 characters (relaxed for code blocks, tables, headings)

## AI Agent Requirements

**When editing Markdown files:**

1. **Before commit:**
   - Pre-commit hook automatically runs `npx markdownlint-cli2-fix` or `npm run markdownlint:fix`
   - Fixed files are automatically added to staging
   - Verify no remaining issues: `npm run markdownlint:check` or `npx markdownlint-cli2 <files>`

2. **If markdownlint is not installed:**
   - Inform user: "Markdownlint not installed. Install with: npm install"
   - Pre-commit hook will try to use `npx --yes` to install on-the-fly
   - Don't block commit, but warn about formatting issues

3. **Formatting guidelines:**
   - Always add blank lines around headings
   - Always add blank lines around lists
   - Use consistent ordered list numbering (1, 2, 3...)
   - Remove trailing spaces (except intentional line breaks)
   - Keep line length under 120 characters where possible

## Exceptions

- **Code blocks** - Line length rules don't apply
- **Tables** - Line length rules don't apply
- **Headings** - Line length rules don't apply
- **Trailing spaces** - Allowed 2 spaces for intentional line breaks

## Enforcement

- Pre-commit hook automatically fixes Markdown files
- AI Agent should run markdownlint-cli2-fix before committing Markdown changes
- CI/CD can optionally check Markdown formatting (non-blocking)

