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

  test('should display feature flags and allow toggling', async ({ page, request }) => {
    // GIVEN: The feature flags page is loaded
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible();
    
    // Find a togglable feature flag (e.g., ai_description_generation)
    const flagToggle = page.getByLabel('ai_description_generation');
    await expect(flagToggle).toBeVisible();
    const initialState = await flagToggle.isChecked();

    // Verify initial state matches API
    const initialApiResponse = await request.get('/api/v1/admin/flags', {
      headers: {
        'Authorization': 'Basic ' + Buffer.from('admin@moviemind.local:password123').toString('base64'),
      },
    });
    expect(initialApiResponse.status()).toBe(200);
    const initialApiData = await initialApiResponse.json();
    const initialApiFlag = initialApiData.data?.find((f: any) => f.name === 'ai_description_generation');
    expect(initialApiFlag).toBeTruthy();
    expect(initialApiFlag.active).toBe(initialState);

    // WHEN: The user toggles the flag
    await flagToggle.setChecked(!initialState);
    await page.getByRole('button', { name: 'Save Changes' }).click();

    // THEN: A success notification should appear
    await expect(page.getByText('Feature flags updated successfully.')).toBeVisible();

    // AND: The new state should be persisted after a page reload
    await page.reload();
    await expect(page.getByLabel('ai_description_generation')).toBeChecked(!initialState);

    // AND: API should reflect the same state
    const updatedApiResponse = await request.get('/api/v1/admin/flags', {
      headers: {
        'Authorization': 'Basic ' + Buffer.from('admin@moviemind.local:password123').toString('base64'),
      },
    });
    expect(updatedApiResponse.status()).toBe(200);
    const updatedApiData = await updatedApiResponse.json();
    const updatedApiFlag = updatedApiData.data?.find((f: any) => f.name === 'ai_description_generation');
    expect(updatedApiFlag).toBeTruthy();
    expect(updatedApiFlag.active).toBe(!initialState);
  });

  test('should show consistent flag status between UI and API', async ({ page, request }) => {
    // GIVEN: User is on the feature flags page
    await expect(page.getByRole('heading', { name: 'Feature Flags' })).toBeVisible();

    // WHEN: Checking flag status in UI
    const flagToggle = page.getByLabel('ai_description_generation');
    await expect(flagToggle).toBeVisible();
    const uiState = await flagToggle.isChecked();

    // THEN: API should show the same status
    const apiResponse = await request.get('/api/v1/admin/flags', {
      headers: {
        'Authorization': 'Basic ' + Buffer.from('admin@moviemind.local:password123').toString('base64'),
      },
    });
    expect(apiResponse.status()).toBe(200);
    const apiData = await apiResponse.json();
    const apiFlag = apiData.data?.find((f: any) => f.name === 'ai_description_generation');
    expect(apiFlag).toBeTruthy();
    expect(apiFlag.active).toBe(uiState);
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
