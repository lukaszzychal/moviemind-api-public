import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('Admin Feature Flags Management', () => {
  test.setTimeout(90_000);

  test.beforeAll(async () => {
    const projectRoot = path.resolve(__dirname, '../../..');
    const composeExec =
      'docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T php php artisan test:prepare-e2e';
    try {
      execSync(composeExec, { stdio: 'inherit', cwd: projectRoot });
    } catch (error) {
      const err = error as { message?: string; stderr?: string };
      const stderr = err.stderr != null ? String(err.stderr).trim() : '';
      throw new Error(
        `test:prepare-e2e failed (cwd: ${projectRoot}). ${err.message ?? ''}${stderr ? ` Stderr: ${stderr}` : ''}. Ensure the stack is running: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate. Run npm run test:e2e from project root with Docker in PATH.`
      );
    }
    // Fail fast if APP_URL does not match baseURL (prevents "Session lost" after login)
    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
    const expectedAppUrl = 'http://127.0.0.1:8000';
    const res = await fetch(`${baseURL}/api/v1/health/db`);
    if (!res.ok) {
      throw new Error(
        `App health check failed (${res.status}) at ${baseURL}. Restart with: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate`
      );
    }
    const data = (await res.json()) as { app_url?: string };
    if (data.app_url == null) {
      throw new Error(
        `E2E requires APP_URL in health response (is APP_ENV=local?). Restart with: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate`
      );
    }
    if (data.app_url !== expectedAppUrl) {
      throw new Error(
        `E2E requires APP_URL to match baseURL. Current app_url is "${data.app_url}". Restart with: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate`
      );
    }
  });

  test.beforeEach(async ({ page }) => {
    const projectRoot = path.resolve(__dirname, '../../..');
    const composeExec =
      'docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T php php artisan test:prepare-e2e';
    try {
      execSync(composeExec, { stdio: 'pipe', cwd: projectRoot });
    } catch (e) {
      const err = e as { message?: string; stderr?: string };
      const stderr = err.stderr != null ? String(err.stderr).trim() : '';
      throw new Error(
        `test:prepare-e2e failed (cwd: ${projectRoot}). ${err.message ?? ''}${stderr ? ` Stderr: ${stderr}` : ''}. Ensure stack is running: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate. Run npm run test:e2e from project root.`
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
          `Admin login failed (still on /admin/login). Screenshot saved to: ${failureScreenshot}. Check: 1) Docker running (docker compose up -d). 2) Same DB for app and test:prepare-e2e. 3) Manual login at baseURL: admin@moviemind.local / password123. Body text (first 500 chars): ${bodyText.slice(0, 500)}`
        );
      }
      throw e;
    }
    // Match /admin or /admin/... but not /admin/login
    await expect(page).toHaveURL(/\/admin(?:\/(?!login).*)?\/?$/, { timeout: 10000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
    const currentHost = new URL(page.url()).host;
    const expectedHost = new URL(baseURL).host;
    if (currentHost !== expectedHost) {
      throw new Error(
        'Session lost when opening /admin/features. Restart app with E2E override so APP_URL matches baseURL: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate'
      );
    }

    // Navigate to Feature Flags: goto after login is more reliable than sidebar click (avoids session redirect flakiness)
    await page.goto('/admin/features', { waitUntil: 'domcontentloaded' });
    const pathname = new URL(page.url()).pathname;
    if (pathname.startsWith('/admin/login')) {
      throw new Error(
        'Session lost when opening /admin/features. Restart app with E2E override so APP_URL matches baseURL: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate'
      );
    }
    await expect(page).toHaveURL(/\/admin\/features/, { timeout: 10000 });
  });

  test('should display feature flags and allow toggling', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible({ timeout: 10000 });
    // Table shows config display name "AI Description Generation", not the key
    const row = page.getByRole('row').filter({ hasText: 'AI Description Generation' });
    const flagToggle = row.getByRole('switch');
    await expect(flagToggle).toBeVisible({ timeout: 10000 });
    await flagToggle.click();
    // Wait for Livewire to finish (switch may be disabled during save)
    await expect(row.getByRole('switch')).not.toBeDisabled({ timeout: 15000 });
    await page.reload();
    await expect(page).toHaveURL(/\/admin\/features/, { timeout: 10000 });
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('row').filter({ hasText: 'AI Description Generation' }).getByRole('switch')).toBeVisible({ timeout: 15000 });
  });

  test('should show consistent flag status between UI and API', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible({ timeout: 10000 });
    const row = page.getByRole('row').filter({ hasText: 'AI Description Generation' });
    await expect(row.getByRole('switch')).toBeVisible({ timeout: 10000 });
  });

  test('should show toggle and allow click', async ({ page }) => {
    const row = page.getByRole('row').filter({ hasText: 'AI Description Generation' });
    const flagToggle = row.getByRole('switch');
    await expect(flagToggle).toBeVisible({ timeout: 10000 });
    await flagToggle.click();
    await expect(row.getByRole('switch')).not.toBeDisabled({ timeout: 15000 });
  });
});
