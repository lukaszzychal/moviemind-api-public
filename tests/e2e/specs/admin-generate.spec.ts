import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('Admin AI Generation', () => {
  test.setTimeout(90_000);

  test.beforeAll(async ({ request }) => {
    // Wait for database to be ready
    await expect.poll(async () => {
      const response = await request.get('/api/v1/health/db');
      return response.status();
    }, {
      message: 'Database health check failed',
      timeout: 30000,
      intervals: [1000, 2000, 5000],
    }).toBe(200);

    const projectRoot = path.resolve(__dirname, '../../..');
    const composeExec = 'docker compose -f compose.yml -f compose.e2e.yml exec -T php';
    try {
      console.log('Seeding database...');
      execSync(`${composeExec} php artisan db:seed`, { stdio: 'inherit', cwd: projectRoot });
      console.log('Database seeded successfully.');
    } catch (error) {
      console.error('Failed to seed database:', error);
      throw error;
    }
  });

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
        'Session lost when opening /admin/movies. Start app with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate'
      );
    }

    await page.goto('/admin/movies', { waitUntil: 'domcontentloaded' });
    const pathname = new URL(page.url()).pathname;
    if (pathname.startsWith('/admin/login')) {
      throw new Error(
        'Session lost when opening /admin/movies. Restart app with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate'
      );
    }
    await expect(page).toHaveURL(/\/admin\/movies/, { timeout: 10000 });
  });

  test('should trigger AI generation for a movie', async ({ page }) => {
    await expect(page.getByRole('table')).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('button', { name: 'Generate AI' }).first()).toBeVisible({ timeout: 15000 });

    await page.getByRole('button', { name: 'Generate AI' }).first().click();
    await expect(page.getByLabel('Locale')).toBeVisible({ timeout: 25000 });

    const localeField = page.getByLabel('Locale');
    await localeField.click();
    await page.getByRole('option', { name: /Polish|pl-PL/i }).first().click();

    const contextField = page.getByLabel('Context tag');
    await contextField.click();
    await page.getByRole('option', { name: /Modern|Critical|Default/i }).first().click();

    await page.getByRole('button', { name: /Generate AI/i }).click();
    await expect(page.getByText(/Generation queued|queued successfully/i)).toBeVisible({ timeout: 15000 });
  });
});
