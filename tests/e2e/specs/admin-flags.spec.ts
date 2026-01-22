import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('Admin Feature Flags Management', () => {
  test.beforeAll(async () => {
    // Seed admin user
    try {
      const projectRoot = path.resolve(__dirname, '../../..');
      execSync('docker compose exec -T php php artisan test:prepare-e2e', { 
        stdio: 'inherit',
        cwd: projectRoot 
      });
    } catch (error) {
      console.error('Failed to seed admin user:', error);
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
    
    // Navigate to feature flags page
    await page.getByRole('link', { name: 'Feature Flags' }).click();
    await expect(page).toHaveURL(/\/admin\/feature-flags$/);
  });

  test('should display feature flags and allow toggling', async ({ page }) => {
    // GIVEN: The feature flags page is loaded
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible();
    
    // Find a togglable feature flag (e.g., ai_description_generation)
    const flagToggle = page.getByLabel('ai_description_generation');
    await expect(flagToggle).toBeVisible();
    const initialState = await flagToggle.isChecked();

    // WHEN: The user toggles the flag
    await flagToggle.setChecked(!initialState);
    await page.getByRole('button', { name: 'Save Changes' }).click();

    // THEN: A success notification should appear
    await expect(page.getByText('Feature flags updated successfully.')).toBeVisible();

    // AND: The new state should be persisted after a page reload
    await page.reload();
    await expect(page.getByLabel('ai_description_generation')).toBeChecked(!initialState);
  });

  test('should reset flags to default', async ({ page }) => {
    // GIVEN: A flag is in a non-default state
    const flagToggle = page.getByLabel('ai_description_generation');
    await flagToggle.setChecked(false); // Assuming default is true
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.getByText('Feature flags updated successfully.')).toBeVisible();
    await page.reload();
    await expect(flagToggle).not.toBeChecked();

    // WHEN: The user clicks "Reset All to Default"
    page.on('dialog', dialog => dialog.accept()); // Auto-accept confirmation dialog
    await page.getByRole('button', { name: 'Reset All to Default' }).click();

    // THEN: A success notification should appear
    await expect(page.getByText('All flags have been reset to their default values.')).toBeVisible();

    // AND: The flag should be back to its default state (true)
    await page.reload();
    await expect(page.getByLabel('ai_description_generation')).toBeChecked();
  });
});
