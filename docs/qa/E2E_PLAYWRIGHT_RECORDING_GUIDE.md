# E2E Tests: Playwright Recording Guide

> **For:** Developers, QA  
> **Scope:** End-to-end tests in `tests/e2e/` (Playwright, TypeScript)

---

## Running E2E tests

Before running `npm run test:e2e`, start the stack with the E2E override so that `APP_URL` matches Playwright’s baseURL (`http://127.0.0.1:8000`):

```bash
docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate
```

Then run the tests:

```bash
npm run test:e2e
```

When testing manually (e.g. with Codegen), open the app at `http://127.0.0.1:8000`.

---

## 1. Test language and stack

E2E tests are written in **TypeScript** (`.spec.ts` files) using **Playwright** (`@playwright/test`):

- **Location:** `tests/e2e/specs/*.spec.ts`
- **Config:** `tests/e2e/playwright.config.ts` (`baseURL: 'http://localhost:8000'`)
- **Run:** `npm run test:e2e` (uses the config above)

Test and describe names in the codebase are in English.

---

## 2. Recording scenarios in the browser (Codegen)

Instead of writing test code by hand, you can **record** actions in the browser; Playwright will generate the test code.

### 2.1 Prerequisites

- Application running (e.g. `docker compose up -d`)
- Node.js and project dependencies installed (`npm install`)
- **Admin login in tests:** Playwright uses `baseURL` (e.g. `http://127.0.0.1:8000`). Laravel uses `APP_URL` for redirects and cookies. If they differ, the session cookie is not sent and login fails in the test browser. **Use a dedicated E2E setup** so the main `api/.env` stays unchanged:
  - **Recommended:** Start the stack with the E2E override: `docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate`. Use `--force-recreate` so `php` and `horizon` pick up `APP_URL=http://127.0.0.1:8000`. Open the app at `http://127.0.0.1:8000` when testing manually.
  - **Config cache:** The E2E global-setup runs `config:clear` so Laravel uses the container’s `APP_URL` (and does not use a cached value from `bootstrap/cache/config.php`). If you do not start the app with the e2e override, admin login in tests may still fail.
  - **Optional:** For more E2E-only vars, copy `env/e2e.env.example` to `api/.env.e2e` and add `api/.env.e2e` to the compose override’s `env_file` (see `compose.e2e.yml`). Keep secrets in `.env`; use `.env.e2e` only for overrides like `APP_URL`.

### 2.2 Start the recorder

From the project root, use the same config as the existing E2E suite so that `baseURL` and other options match:

```bash
npx playwright codegen --config=tests/e2e/playwright.config.ts http://localhost:8000
```

Or rely on `baseURL` from the config (no URL needed):

```bash
npx playwright codegen --config=tests/e2e/playwright.config.ts
```

This opens a browser window (for interaction) and a Playwright Inspector panel (with generated code). Everything you do in the browser is translated into Playwright API calls.

### 2.3 What to record

1. Optionally go to `/admin/login` and log in (email/password, Sign in).
2. Perform the scenario you want to test (e.g. open Feature Flags, toggle a flag, open Movies, trigger Generate AI).
3. Stop recording and copy the generated code from the Inspector.

### 2.4 Save the generated code

In the Inspector, use **Copy** or **Save** and store the result in a new file under `tests/e2e/specs/`, e.g. `my-recorded.spec.ts`.

---

## 3. Making recorded tests compatible with the current setup

Recorded code does **not** include project-specific setup. To align with existing specs (e.g. `admin-flags.spec.ts`):

### 3.1 Add imports and structure

At the top: `import { test, expect } from '@playwright/test';` and, if needed, `execSync` and `path`. Wrap the recorded steps in `test.describe` and `test('...', async ({ page }) => { ... })`.

### 3.2 Add setup and login (admin tests)

For admin flows, add the same lifecycle and login as in existing specs: **beforeEach** with `test:prepare-e2e` (via `execSync`), then `page.goto('/admin/login', { waitUntil: 'networkidle' })`, fill email/password, click Sign in, `await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 25000 })`, and wait for a dashboard element (e.g. link "Movies"). See `tests/e2e/specs/admin-flags.spec.ts` for the full pattern. Paste your recorded steps inside the test; remove duplicated login steps if you recorded them.

### 3.3 Replace fixed waits with assertions

Codegen often emits `page.waitForTimeout(...)`. Replace with assertions like `await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible({ timeout: 10000 });` or `await expect(page).toHaveURL(...);`.

### 3.4 Keep selectors consistent

Prefer `getByRole`, `getByLabel`, `getByText`. Simplify long CSS `locator(...)` from Codegen to match the style of existing specs.

---

## 4. Editing and fixing recorded scenarios

You **do not** need to re-record a test from scratch when the app changes or the test fails. Edit the existing `.spec.ts` file.

### 4.1 When the UI or flow has changed

1. **Update selectors** – If a label, button text, or heading changed, adjust the corresponding line in the spec, e.g.:
   - `getByRole('button', { name: 'Sign in' })` → change `'Sign in'` if the button text changed.
   - `getByLabel('Email address')` → change to the new label if the form was renamed.
   - `getByRole('heading', { name: 'Feature Flags' })` → change the heading text if the page title changed.
2. **Update URLs** – If you use `expect(page).toHaveURL(...)` or `waitForURL(...)`, change the regex or string to match the new route.
3. **Add or relax waits** – If the page loads slower or content appears later, increase the `timeout` in `toBeVisible({ timeout: 15000 })` or add an extra assertion before the next action.

### 4.2 When the test fails (error or timeout)

1. **Run only that test** to get a fast feedback loop:
   ```bash
   npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts -g "should display feature flags"
   ```
2. **Read the error** – Playwright reports the file, line number, and usually the failed assertion (e.g. "Timeout waiting for locator", "Expected URL …").
3. **Open the HTML report** after a run to see screenshots and trace for the failing step:
   ```bash
   npx playwright show-report
   ```
4. **Fix the failing line** – Often it is:
   - **Selector no longer matches** – The element text or structure changed. Open the app in the browser, use **Pick locator** (in Playwright Inspector or VS Code extension) to get a new selector, then replace the old one in the spec with a stable alternative (`getByRole`, `getByLabel`, or `getByText`).
   - **Timeout** – Element appears later or the action is slower. Increase the `timeout` in the assertion or add a preceding `await expect(...).toBeVisible({ timeout: ... })`.
   - **Wrong URL** – Redirect or route changed. Update the `toHaveURL` / `waitForURL` pattern.

### 4.3 Re-recording only part of a scenario

If a whole section of the flow changed (e.g. a new modal or step), you can re-record just that part and paste it into the existing spec:

1. Start Codegen with the project config: `npx playwright codegen --config=tests/e2e/playwright.config.ts`.
2. Manually go to the same state where the changed flow starts (e.g. open the admin panel and navigate to the right page), or use a short script that does `page.goto(...)` and login so you start from the right place.
3. Record **only the new or changed steps** in the browser.
4. Copy the generated lines from the Inspector and **replace** the corresponding block in your existing spec (delete the old steps, paste the new ones).
5. Apply the same cleanup as in section 3: add/keep `test.describe` and setup, replace `waitForTimeout` with assertions, simplify selectors to `getByRole` / `getByLabel` / `getByText`.

### 4.4 Summary

| Situation | What to do |
|-----------|------------|
| Button/label/heading text changed | Edit the selector string in the spec (name, label, or text). |
| Test times out on an element | Increase `timeout` or add a visibility wait before the action. |
| Redirect or URL changed | Update `toHaveURL` / `waitForURL` regex or string. |
| Whole block of steps is wrong | Re-record that block with Codegen, paste into the spec, then clean up. |
| Need to see why it failed | Run the single test, then `npx playwright show-report` and open the trace/screenshot. |

---

## 5. Quick reference

| Task | Command |
|------|--------|
| Start app for E2E (APP_URL=127.0.0.1) | `docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate` |
| Run all E2E tests | `npm run test:e2e` |
| Run one spec | `npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts` |
| Run one test by name | `npm run test:e2e -- tests/e2e/specs/admin-flags.spec.ts -g "should display feature flags"` |
| Record with project config | `npx playwright codegen --config=tests/e2e/playwright.config.ts` |
| Open last HTML report | `npx playwright show-report` |

---

## 6. Related docs

- [Automated tests (PHP + E2E)](./AUTOMATED_TESTS.md)
- [Playwright config](../../tests/e2e/playwright.config.ts)
- [Admin E2E example](../../tests/e2e/specs/admin-flags.spec.ts)
