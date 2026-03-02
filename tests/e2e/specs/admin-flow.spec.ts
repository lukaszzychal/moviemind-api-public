import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('Admin Panel Flow', () => {
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
      console.log('Seeding admin user...');
      execSync(`${composeExec} php artisan test:prepare-e2e`, { stdio: 'inherit', cwd: projectRoot });
      console.log('Admin user seeded successfully.');
    } catch (error) {
      console.error('Failed to seed admin user:', error);
      throw error;
    }
  });

  test('should redirect guest to login page', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/admin\/login/);
    await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();
  });

  test('should login successfully with valid credentials', async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill('admin@moviemind.local');
    await page.getByLabel('Password').fill('password123');
    await Promise.all([
      page.waitForURL(/\/admin\/?$/, { timeout: 25000 }),
      page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
    await expect(page).toHaveURL(/\/admin\/?$/, { timeout: 5000 });
    const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
    if (new URL(page.url()).host !== new URL(baseURL).host) {
      throw new Error(
        'Login redirected to wrong host. Start app with E2E override: docker compose -f docker-compose.yml -f docker-compose.e2e.yml up -d --force-recreate'
      );
    }
    await expect(
      page.getByText(/MovieMind Admin|Total Movies|Total People|Pending Jobs|System Status|Environment/).first()
    ).toBeVisible({ timeout: 10000 });
  });

  test('should show validation error for invalid credentials', async ({ page }) => {
    await page.goto('/admin/login');
    
    await page.getByLabel('Email address').fill('wrong@example.com');
    await page.getByLabel('Password').fill('wrongpassword');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
  });
});
