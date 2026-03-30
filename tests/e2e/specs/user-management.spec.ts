import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('User Management', () => {
  test.setTimeout(90_000);

  test.beforeEach(async ({ page }) => {
    const projectRoot = path.resolve(__dirname, '../../..');
    const composeExec = 'docker compose -f compose.yml -f compose.e2e.yml exec -T php';
    try {
      execSync(`${composeExec} php artisan test:prepare-e2e`, { stdio: 'pipe', cwd: projectRoot });
    } catch (e) {
      throw new Error(
        `test:prepare-e2e failed: ${(e as Error).message}. Start stack: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate. Run from project root.`
      );
    }
    await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible({ timeout: 10000 });
    await expect(page.getByLabel('Email address')).toBeVisible({ timeout: 5000 });
    await expect(page.getByLabel('Password')).toBeVisible({ timeout: 5000 });
    const emailInput = page.getByLabel('Email address');
    const passwordInput = page.getByLabel('Password');
    await emailInput.click();
    await emailInput.fill('admin@moviemind.local');
    await emailInput.press('Tab');
    await passwordInput.fill('password123');
    await page.waitForTimeout(400);
    const signInBtn = page.getByRole('button', { name: 'Sign in' });
    try {
      await Promise.all([
        page.waitForURL(
          (url) => {
            const pathname = new URL(url).pathname;
            return pathname.startsWith('/admin') && !pathname.startsWith('/admin/login');
          },
          { timeout: 35000 }
        ),
        signInBtn.click(),
      ]);
    } catch (e) {
      const pathname = new URL(page.url()).pathname;
      if (pathname.startsWith('/admin/login')) {
        const bodyText = await page.locator('body').innerText().catch(() => '');
        const failureScreenshot = path.join(projectRoot, 'tests', 'e2e', 'login-failure.png');
        await page.screenshot({ path: failureScreenshot }).catch(() => {});
        throw new Error(
          `Admin login failed (still on /admin/login). Screenshot: ${failureScreenshot}. Start app with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate. Body (500 chars): ${bodyText.slice(0, 500)}`
        );
      }
      throw e;
    }
    await expect(page).toHaveURL(/\/admin(?:\/(?!login).*)?\/?$/, { timeout: 10000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
    if (new URL(page.url()).host !== new URL(baseURL).host) {
      throw new Error(
        'Session lost when opening /admin/users. Start app with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate'
      );
    }

    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
    const pathname = new URL(page.url()).pathname;
    if (pathname.startsWith('/admin/login')) {
      throw new Error(
        'Session lost when opening /admin/users. Restart app with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate'
      );
    }
    await expect(page).toHaveURL(/\/admin\/users/, { timeout: 10000 });
  });

  test('can create a user and assign a feature flag scope', async ({ page }) => {
    const uniqueId = Date.now();
    const userName = `Test User ${uniqueId}`;
    const userEmail = `testuser${uniqueId}@example.com`;
    const featureName = `test_feature_${uniqueId}`;
    await expect(page.getByRole('button', { name: /New|Create|Add/i }).first()).toBeVisible({ timeout: 15000 });
    await page.getByRole('button', { name: /New|Create|Add/i }).first().click();
    const nameField = page.getByLabel('Name').or(page.locator('input[name="data[name]"]'));
    await expect(nameField).toBeVisible({ timeout: 15000 });
    await nameField.fill(userName);
    await page.getByLabel('Email').fill(userEmail);
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Create' }).first().click();
    await expect(page.getByText(userName)).toBeVisible({ timeout: 15000 });

    await page.goto('/admin/features/create');
    await expect(page.getByLabel('Name')).toBeVisible({ timeout: 10000 });
    await page.getByLabel('Name').fill(featureName);

    const scopeChoices = page.locator('.choices').filter({ has: page.locator('select#data\\.scope') });
    await scopeChoices.locator('.choices__inner').click();
    await page.keyboard.type(userName);
    const scopeOption = page.getByRole('option', { name: new RegExp(userName) }).first();
    await expect(scopeOption).toBeVisible({ timeout: 10000 });
    await scopeOption.click();

    await page.getByLabel('Enabled').check();
    await page.getByRole('button', { name: 'Create' }).first().click();

    await expect(page).toHaveURL(/.*features/);
    await expect(page.getByText(featureName)).toBeVisible({ timeout: 15000 });
  });
});
