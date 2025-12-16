# Pre-Commit and Release Checklist

> **Source:** Migrated from `.cursor/rules/old/checklist.mdc` and `.cursor/rules/old/workflow.mdc`  
> **Category:** reference

## Pre-Commit Checklist

### Tools
- [ ] Code formatted by Pint
- [ ] PHPStan without errors
- [ ] All tests pass
- [ ] GitLeaks does not detect secrets
- [ ] Composer audit does not detect critical vulnerabilities

### Tests
- [ ] New code has tests (if it's a new feature)
- [ ] Tests are readable
- [ ] TDD was applied

### Quality
- [ ] Code is readable and follows conventions
- [ ] SOLID applied (when it makes sense)
- [ ] Duplication removed (when needed)
- [ ] Type hints and `declare(strict_types=1)`

### Cleanup
- [ ] No debug code
- [ ] No unused code
- [ ] No commented code (unless it explains "why")

### Git
- [ ] Commit message is descriptive and follows convention
- [ ] Commit contains one logical change

---

## Pre-Commit Workflow (REQUIRED)

### Automatic Git Hooks Installation

**BEFORE EVERY COMMIT OR PUSH - automatically check and install hooks:**

1. **Check if hooks are installed:**
   ```bash
   if [ ! -f ".git/hooks/pre-commit" ]; then
       if [ -f "scripts/git-hooks/pre-commit" ]; then
           cp scripts/git-hooks/pre-commit .git/hooks/pre-commit
           chmod +x .git/hooks/pre-commit
           echo "✅ Git hooks installed automatically"
       fi
   fi
   ```

2. **For AI Agent:**
   - Before every `git commit` or `git push`, automatically check if `.git/hooks/pre-commit` exists
   - If it doesn't exist, automatically install from `scripts/git-hooks/pre-commit`
   - Make sure the hook has execute permissions (`chmod +x`)
   - **DO NOT ask the user - execute automatically**

## Before every commit you MUST run and fix:

### 1. Markdownlint (Markdown formatting) - if .md files were changed
```bash
# Automatic fix (uses npx or npm scripts)
npm run markdownlint:fix
# Or for specific files
npx markdownlint-cli2-fix docs/issue/pl/TASKS.md

# Check
npm run markdownlint:check
```
**Installation:** `npm install` (markdownlint-cli2 is in package.json) or `./scripts/setup-markdownlint.sh`

### 2. Laravel Pint (PHP formatting)
```bash
cd api && vendor/bin/pint
```

### 3. PHPStan (static analysis)
```bash
cd api && vendor/bin/phpstan analyse --memory-limit=2G
```
Level: 5. Zero errors before commit.

#### PHPStan Auto-Fix (optional - uses internal command)
```bash
# Suggest mode (preview changes, default)
cd api && php artisan phpstan:auto-fix --mode=suggest

# Apply mode (write changes to files)
cd api && php artisan phpstan:auto-fix --mode=apply

# Or use with existing PHPStan JSON output
cd api && vendor/bin/phpstan analyse --memory-limit=2G --error-format=json > phpstan-output.json 2>&1 || true
cd api && php artisan phpstan:auto-fix --input=phpstan-output.json --mode=suggest
```

**Note:** The internal `phpstan:auto-fix` command uses `App\Support\PhpstanFixer` module. This is separate from the external `lukaszzychal/phpstan-fixer` package (which was removed due to Laravel compatibility issues).

#### PHPStan Log Archiving
- After each PHPStan run, save the full output (`stdout` + `stderr`) to a log file.
- Use directory `docs/logs/phpstan/` (create on first use, e.g. `mkdir -p docs/logs/phpstan`).
- File name: `phpstan-YYYYMMDD-HHMMSS.log` (e.g. `phpstan-20251109-153000.log`).
- Recommended command:
```bash
cd api && vendor/bin/phpstan analyse --memory-limit=2G 2>&1 | tee ../docs/logs/phpstan/phpstan-$(date +"%Y%m%d-%H%M%S").log
```
- Do not delete existing log files - they will be needed in future projects.

### 4. PHPUnit (tests)
```bash
cd api && php artisan test
```
All tests must pass.

### 5. Markdownlint (Markdown formatting) - **AUTOMATICALLY IN PRE-COMMIT HOOK**

Pre-commit hook automatically runs markdownlint-cli2-fix for .md files before commit.

### 6. GitLeaks (secrets) - **REQUIRED BEFORE COMMIT AND PUSH**
```bash
gitleaks protect --source . --verbose --no-banner
```
Zero detected secrets.

**IMPORTANT:** Before every commit and push **ALWAYS** run GitLeaks. If GitLeaks is not installed, install it:
```bash
# macOS
brew install gitleaks

# Or use setup script
./scripts/setup-pre-commit.sh
```

**Automatic hook installation:**
- If hooks are not installed, automatically install them from `scripts/git-hooks/pre-commit`
- Check if `.git/hooks/pre-commit` exists, if not - copy from `scripts/git-hooks/pre-commit`
- Make sure the hook has execute permissions: `chmod +x .git/hooks/pre-commit`

### 7. Composer Audit (security)
```bash
cd api && composer audit
```
Fix critical vulnerabilities before commit.

### 8. API Documentation Update (if endpoints were changed)
**IMPORTANT:** If API endpoints were added/changed/removed, **ALWAYS** update:

#### OpenAPI Spec
```bash
# Update docs/openapi.yaml
# Add/change/remove endpoints according to code changes
```

#### Postman Collection
```bash
# Update docs/postman/moviemind-api.postman_collection.json
# Add/change/remove requests according to API changes
```

#### Insomnia Collection
```bash
# Update docs/insomnia/moviemind-api-insomnia.json
# Add/change/remove requests according to API changes
```

**When to update:**
- ✅ New endpoint added
- ✅ Endpoint path changed
- ✅ Request/response parameters changed
- ✅ Endpoint removed
- ✅ Status codes changed
- ✅ Authorization added/changed

**Update format:**
- OpenAPI: According to OpenAPI 3.0 format
- Postman: JSON format compatible with Postman Collection v2.1
- Insomnia: JSON format compatible with Insomnia Export

### 9. Manual Testing Instructions Update (if testing mechanisms were changed)
**IMPORTANT:** If testing mechanisms, endpoints, feature flags, response format, or log structure were changed, **ALWAYS** update:

#### Manual Testing Guide
```bash
# Update docs/knowledge/reference/MANUAL_TESTING_GUIDE.md (PL)
# Update docs/knowledge/reference/MANUAL_TESTING_GUIDE.en.md (EN)
```

**When to update:**
- ✅ API endpoints changed (added/changed/removed)
- ✅ Duplication prevention mechanisms changed (e.g. slot management, locking)
- ✅ Feature flags changed (added/changed/removed)
- ✅ API response format changed
- ✅ Slug format requirements changed
- ✅ Log structure changed (e.g. new logs, format change)
- ✅ Feature flag activation method changed
- ✅ New test scenarios added

**Update format:**
- Update relevant test sections
- Update command examples
- Update troubleshooting
- Update final checklist
- Update "Last update" date

## Complete check (everything at once)

```bash
# Markdown formatting (if .md files changed, optional - pre-commit hook does this automatically)
npm run markdownlint:fix && \
cd api && \
  vendor/bin/pint && \
  vendor/bin/phpstan analyse --memory-limit=2G && \
  php artisan test && \
  cd .. && \
  gitleaks protect --source . --verbose --no-banner && \
  cd api && \
  composer audit
```

**Note:** Pre-commit hook automatically runs markdownlint for .md files, so manual run is optional.

**Remember:** After API changes always update documentation (OpenAPI, Postman, Insomnia) and manual testing instructions!

## Automatic Git Hooks Installation

**Before every commit or push:**
1. Check if `.git/hooks/pre-commit` exists
2. If it doesn't exist, automatically install from `scripts/git-hooks/pre-commit`:
   ```bash
   cp scripts/git-hooks/pre-commit .git/hooks/pre-commit && chmod +x .git/hooks/pre-commit
   ```
3. Check if GitLeaks is installed (`command -v gitleaks`)
4. If not, inform the user about the need to install (don't block, but warn)

**Automatic secret verification:**
- Before every commit: run `gitleaks protect --source . --verbose --no-banner --staged`
- Before every push: run `gitleaks protect --source . --verbose --no-banner`
- If secrets are detected, **BLOCK** commit/push and inform the user

## Branch Workflow (default rule)

- **90% of tasks** should be done on short feature branches (`feature/…`) covering one coherent topic.
- Each branch ends with a pull request to `main` (you can approve yourself, but PR and diff are mandatory).
- `main` must always remain deployable; merge only after tests/pre-commits pass.
- Longer, multi-week branches should be used only in exceptional reconstructions — then divide work into smaller PRs.
- **Commit messages** should be written only in English, in the style `type: short description` (e.g. `feat: add movie resource`). Messages in other languages are not allowed.
- **Pull Request descriptions** (body) should be written only in English. All PR descriptions, titles, and sections must be in English. PR descriptions in other languages are not allowed.
- Agent can execute `git commit`, but `git push` runs only on explicit user command.

## Immediate commits

- After each change in Cursor rules files (`.cursor/rules/*.mdc`), immediately commit these files.
- After adding a new task or updating content of an existing task in `docs/issue/**/*.md`, immediately commit these changes in a separate commit.

## Task management (TASKS.md)

- When starting a task **change status to `🔄 IN_PROGRESS`**, enter `Start time` (with minute precision) and mark the executor (`🤖/👨‍💻/⚙️`).
- When completing a task:
  - Fill in `End time` and **calculate `Duration`** (format `HHhMMm`), even when abbreviation says AUTO,
  - Change status to `✅ COMPLETED`,
  - **move the entire task block to the "✅ Completed Tasks" section**,
  - add a brief description of work done (e.g. list of key changes).
- Don't leave completed tasks in the "Active" section. This section should contain only tasks with status `⏳` or `🔄`.
- After updating `TASKS.md`, trigger sync workflow (push/merge to `main` or manually in GitHub Actions) to keep Issues consistent.

## Documentation (PL / EN)

- Each document must have Polish and English versions (e.g. in `docs/…/pl` and `docs/…/en`).
- When modifying one language **immediately** update the other – lack of synchronization is treated as a review error.
- If content is long, summaries are acceptable, but both languages must contain complete key information and link to each other.
- Index files (e.g. `docs/issue/README.md`) should clearly indicate where PL and EN versions are located.

## External links and proper nouns

- When providing publicly available proper nouns (e.g. online stores, providers) **always** include a reliable URL link next to it.
- Each external link **must be verified** (open/test check) to ensure it's active and leads to the correct source.
- If you cannot confirm the link, **clearly mark this**, adding an alternative source or warning.
- Format responses clearly (headings, bullet lists) according to Cursor guidelines.

## Manual testing

- **ALWAYS** manually test tasks and functions that can be tested without automated tests.
- **Inform continuously** about testing steps and results:
  - Describe each testing step (e.g. "Testing endpoint GET /api/v1/movies/{slug}")
  - Provide results of each test (success/error, status code, response time, etc.)
  - Inform about encountered problems or unexpected behaviors
  - Report testing progress (e.g. "Tested 3/5 scenarios")
- Before completing a task, verify that the implementation works correctly in practice (e.g. by calling the API endpoint, checking response, verifying behavior in browser).
- If testing requires running Docker or another tool (e.g. Redis, Horizon, PostgreSQL), **always inform the user**:
  - What tool is needed
  - How to run it (e.g. `docker compose up -d`, `php artisan horizon`)
  - What commands to run for verification
  - Where to check results (logs, dashboard, etc.)
- Examples of situations requiring manual testing:
  - New API endpoints (checking response, status codes, JSON format)
  - Integrations with external services (OpenAI, etc.)
  - UI/UX changes (if applicable)
  - Features requiring user interaction
  - Scenarios difficult to test automatically (e.g. race conditions, timing issues)
- If manual testing is not possible or would take too much time, inform the user about this and propose an alternative approach (e.g. automated tests, documentation, etc.).

## Informing about AI model and tokens

- **ALWAYS** inform the user about which AI model you're using at the beginning of the response or when it's relevant to the context.
- Format: `**AI Model used:** [model name]` (e.g. `Claude Sonnet 4.5 (Auto)`, `OpenAI gpt-4o-mini`).
- If token information (used/remaining) is available and **providing it doesn't consume additional tokens**, you can include it:
  - Basic format: `**Tokens:** used: X, remaining: Y` (or similar, depending on available data).
  - If detailed token information for prompt/response or input/output is available, you can provide it:
    - Format: `**Tokens:** input: X, output: Y, total: Z` or `**Tokens:** prompt: X, response: Y, total: Z`
    - Include only information that is available and providing it doesn't consume additional tokens.
- If token information is not available or providing it would consume additional tokens, omit this information.
- In the context of MovieMind API application, if you mention the model used by the application (e.g. OpenAI), always provide the full model name (e.g. `gpt-4o-mini`).

