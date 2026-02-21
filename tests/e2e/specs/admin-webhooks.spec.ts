import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('TC-UI-010: Admin Outgoing Webhooks', () => {
  test.beforeAll(async () => {
    try {
      const projectRoot = path.resolve(__dirname, '../../..');
      execSync('docker compose exec -T php php artisan test:prepare-e2e', {
        stdio: 'inherit',
        cwd: projectRoot,
      });
    } catch (error) {
      console.error('Failed to seed admin user:', error);
      throw error;
    }
  });

  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/login');
    await page.getByLabel('Email address').fill('admin@moviemind.local');
    await page.getByLabel('Password').fill('password123');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
  });

  test('TC-UI-010: webhooks list, create subscription, back to list', async ({ page }) => {
    // Navigate to Outgoing Webhooks list
    await page.getByRole('link', { name: 'Outgoing Webhooks' }).click();
    await expect(page).toHaveURL(/\/admin\/outgoing-webhooks$/);

    // List page: main heading "Outgoing Webhooks"
    await expect(page.getByRole('heading', { name: 'Outgoing Webhooks', level: 1 })).toBeVisible();

    // Open Create (add webhook endpoint / subscription)
    await page.getByRole('link', { name: 'New Outgoing Webhook' }).click();
    await expect(page).toHaveURL(/\/admin\/outgoing-webhooks\/create/);

    // Create form: Event type + Webhook URL only (saves to webhook_subscriptions)
    const eventTypeTrigger = page.locator('div.choices:has(select#data\\.event_type) .choices__inner');
    await eventTypeTrigger.click();
    await page.getByRole('option', { name: 'movie.generation.completed' }).click();
    await page.getByLabel('Webhook URL').fill('https://example.com/test-webhook-e2e');

    await page.getByRole('button', { name: 'Create', exact: true }).click();

    // Create redirects to list (index), not to view/edit
    await expect(page).toHaveURL(/\/admin\/outgoing-webhooks$/);
    await expect(page.getByText('Webhook endpoint added').first()).toBeVisible();
  });
});
