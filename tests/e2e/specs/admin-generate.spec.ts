import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('Admin AI Generation', () => {
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

    // Seed database (admin + movies)
    try {
      console.log('Seeding database...');
      const projectRoot = path.resolve(__dirname, '../../..');
      // Run full seed to ensure movies exist
      execSync('docker compose exec -T php php artisan db:seed', { 
        stdio: 'inherit',
        cwd: projectRoot 
      });
      console.log('Database seeded successfully.');
    } catch (error) {
      console.error('Failed to seed database:', error);
      throw error;
    }
  });

  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill('admin@moviemind.local');
    await page.getByLabel('Password').fill('password123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
  });

  test('should trigger AI generation for a movie', async ({ page }) => {
    // Navigate to movies list
    await page.getByRole('link', { name: 'Movies' }).click();
    await expect(page).toHaveURL(/\/admin\/movies$/);

    // Wait for table to load
    await expect(page.getByRole('table')).toBeVisible();

    // Click the "Generate AI" button on the first row
    await page.getByRole('button', { name: 'Generate AI' }).first().click();

    // Wait for modal to appear
    // We look for a dialog that contains a heading with "Generate AI"
    // This is more specific than just hasText which might match hidden elements
    const modal = page.getByRole('dialog').filter({ 
      has: page.getByRole('heading', { name: 'Generate AI' }) 
    });
    
    await expect(modal).toBeVisible();

    // Fill the form inside the modal
    await modal.getByLabel('Locale').click();
    await page.getByRole('option', { name: 'Polish' }).click();

    await modal.getByLabel('Context tag').click();
    await page.getByRole('option', { name: 'Short' }).click();

    // Submit
    await modal.getByRole('button', { name: 'Generate AI' }).click();

    // Verify notification
    await expect(page.getByText('Generation queued successfully')).toBeVisible();
  });
});
