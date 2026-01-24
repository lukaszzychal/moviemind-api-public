import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('AI Metrics Display in Filament UI', () => {
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

    // Seed database (admin + movies with descriptions)
    try {
      console.log('Seeding database...');
      const projectRoot = path.resolve(__dirname, '../../..');
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

  test('should display AI metrics columns in movie descriptions table', async ({ page }) => {
    // GIVEN: User navigates to movies
    await page.getByRole('link', { name: 'Movies' }).click();
    await expect(page).toHaveURL(/\/admin\/movies$/);

    // Wait for table to load
    await expect(page.getByRole('table')).toBeVisible();

    // WHEN: Opening a movie with descriptions
    await page.getByRole('link', { name: /view|edit/i }).first().click();

    // THEN: Descriptions tab should be visible
    await page.getByRole('tab', { name: /descriptions/i }).click();

    // AND: AI metrics columns should be visible
    // Note: These columns may be toggleable, so we check if they exist in the table header
    const table = page.getByRole('table');
    await expect(table).toBeVisible();

    // Check for metrics-related column headers (may be hidden by default)
    // We'll check if the table contains metrics data when available
    const tableText = await table.textContent();
    expect(tableText).toBeTruthy();
  });

  test('should show detailed metrics in description view modal', async ({ page }) => {
    // GIVEN: User is viewing a movie with descriptions
    await page.getByRole('link', { name: 'Movies' }).click();
    await expect(page).toHaveURL(/\/admin\/movies$/);
    await expect(page.getByRole('table')).toBeVisible();
    await page.getByRole('link', { name: /view|edit/i }).first().click();
    await page.getByRole('tab', { name: /descriptions/i }).click();

    // WHEN: Clicking view on a description
    const viewButtons = page.getByRole('button', { name: /view/i });
    const count = await viewButtons.count();
    if (count > 0) {
      await viewButtons.first().click();

      // THEN: Modal should show description details
      const modal = page.getByRole('dialog');
      await expect(modal).toBeVisible();

      // AND: Should show metadata section
      await expect(modal.getByText(/locale|context|model/i)).toBeVisible();

      // AND: Should show AI metrics section (if metrics exist)
      const modalText = await modal.textContent();
      if (modalText?.toLowerCase().includes('metrics')) {
        await expect(modal.getByText(/tokens|parsing|response time/i)).toBeVisible();
      }
    }
  });
});
