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

    // Seed admin user using custom artisan command
    try {
      console.log('Seeding admin user...');
      const projectRoot = path.resolve(__dirname, '../../..');
      execSync('docker compose exec -T php php artisan test:prepare-e2e', { 
        stdio: 'inherit',
        cwd: projectRoot 
      });
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
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByText('MovieMind Admin')).toBeVisible();
    
    // Check for new System Status widget
    await expect(page.getByRole('heading', { name: 'System Status' })).toBeVisible();
    await expect(page.getByText('Environment')).toBeVisible();
    await expect(page.getByText('AI Service')).toBeVisible();

    // Check for dashboard widgets
    await expect(page.getByText('Total Movies')).toBeVisible();
    await expect(page.getByText('Total People')).toBeVisible();
    await expect(page.getByText('Pending Jobs')).toBeVisible();
  });

  test('should show validation error for invalid credentials', async ({ page }) => {
    await page.goto('/admin/login');
    
    await page.getByLabel('Email address').fill('wrong@example.com');
    await page.getByLabel('Password').fill('wrongpassword');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByText('These credentials do not match our records.')).toBeVisible();
  });
});
