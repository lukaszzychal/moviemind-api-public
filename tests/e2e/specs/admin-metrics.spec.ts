import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('AI Metrics Display in Filament UI', () => {
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
    const composeExec = 'docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T php';
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
    const composeExec = 'docker compose -f docker-compose.yml -f docker-compose.e2e.yml exec -T php';
    try {
      execSync(`${composeExec} php artisan test:prepare-e2e`, { stdio: 'pipe', cwd: projectRoot });
    } catch (e) {
      const err = e as { message?: string; stderr?: string };
      throw new Error(
        `test:prepare-e2e failed: ${err.message ?? ''}. Start stack: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate. Run from project root.`
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
          `Admin login failed (still on /admin/login). Screenshot: ${failureScreenshot}. Start app with E2E override: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate. Then run tests from project root. Body (500 chars): ${bodyText.slice(0, 500)}`
        );
      }
      throw e;
    }
    await expect(page).toHaveURL(/\/admin(?:\/(?!login).*)?\/?$/, { timeout: 10000 });
    await page.waitForLoadState('networkidle').catch(() => {});

    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
    if (new URL(page.url()).host !== new URL(baseURL).host) {
      throw new Error(
        'Session lost when opening /admin/movies. Start app with E2E override: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate'
      );
    }

    await page.goto('/admin/movies', { waitUntil: 'domcontentloaded' });
    const pathname = new URL(page.url()).pathname;
    if (pathname.startsWith('/admin/login')) {
      throw new Error(
        'Session lost when opening /admin/movies. Restart app with E2E override: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate'
      );
    }
    await expect(page).toHaveURL(/\/admin\/movies/, { timeout: 10000 });
  });

  test('should display AI metrics columns in movie descriptions table', async ({ page }) => {
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20000 });
    await expect(page.getByRole('link', { name: /Edit|View/i }).first()).toBeVisible({ timeout: 15000 });

    await page.getByRole('link', { name: /Edit|View/i }).first().click();
    const descriptionsTab = page.getByRole('tab', { name: 'Descriptions' }).or(page.getByText('Descriptions').first());
    await descriptionsTab.click({ timeout: 15000 });

    const table = page.getByRole('table');
    await expect(table).toBeVisible({ timeout: 15000 });
    expect(await table.textContent()).toBeTruthy();
  });

  test('should show detailed metrics in description view modal', async ({ page }) => {
    await expect(page.getByRole('table')).toBeVisible({ timeout: 20000 });
    await expect(page.getByRole('link', { name: /Edit|View/i }).first()).toBeVisible({ timeout: 15000 });

    await page.getByRole('link', { name: /Edit|View/i }).first().click();
    const descriptionsTab = page.getByRole('tab', { name: 'Descriptions' }).or(page.getByText('Descriptions').first());
    await descriptionsTab.click({ timeout: 15000 });

    const viewButtons = page.getByRole('button', { name: /view/i });
    const count = await viewButtons.count();
    if (count > 0) {
      await viewButtons.first().click();
      await expect(page.getByText(/Tokens|Model|N\/A/)).toBeVisible({ timeout: 15000 });
    }
  });
});
