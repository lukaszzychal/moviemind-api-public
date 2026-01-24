import { test, expect } from '@playwright/test';

test.describe('Horizon Dashboard Access', () => {
  test('should be accessible in local environment without authentication', async ({ page }) => {
    // WHEN: Accessing Horizon dashboard
    const response = await page.goto('/horizon');

    // THEN: Should return 200 OK (not 403 Forbidden)
    expect(response?.status()).toBe(200);

    // AND: Should load Horizon dashboard
    await expect(page.locator('body')).toContainText(/horizon|queue|jobs/i);
  });

  test('should not require Basic Auth in local environment', async ({ request }) => {
    // WHEN: Accessing Horizon without Basic Auth credentials
    const response = await request.get('/horizon');

    // THEN: Should return 200 OK (not 401 Unauthorized)
    expect(response.status()).toBe(200);
  });

  test('should display Horizon dashboard content', async ({ page }) => {
    // WHEN: Accessing Horizon dashboard
    await page.goto('/horizon');

    // THEN: Should display Horizon interface elements
    // Note: Exact elements may vary, but should contain Horizon-specific content
    const bodyText = await page.locator('body').textContent();
    expect(bodyText).toBeTruthy();
    // Horizon typically has navigation or dashboard elements
    expect(bodyText?.toLowerCase()).toMatch(/horizon|dashboard|queue/i);
  });
});
