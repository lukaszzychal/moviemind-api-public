import { test, expect } from '@playwright/test';

test.describe('User Management', () => {
    test.beforeEach(async ({ page }) => {
        // Login
        await page.goto('http://localhost:8000/admin/login');
        await page.fill('input[type="email"]', 'admin@moviemind.local');
        await page.fill('input[type="password"]', 'password123');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL('http://localhost:8000/admin');
    });

    test('can create a user and assign a feature flag scope', async ({ page }) => {
        const uniqueId = Date.now();
        const userName = `Test User ${uniqueId}`;
        const userEmail = `testuser${uniqueId}@example.com`;
        const featureName = `test_feature_${uniqueId}`;

        // 1. Create User
        await page.click('text=Users');
        await page.click('text=New user');

        await page.getByLabel('Name').fill(userName);
        await page.getByLabel('Email').fill(userEmail);
        await page.getByLabel('Password').fill('password');

        await page.click('button:has-text("Create")'); // "Create" or "Create & create another" usually. "Create" button in modal footer.

        // Verify user exists in table
        await expect(page.getByText(userName)).toBeVisible();

        // 2. Create Feature Flag with User Scope
        await page.goto('http://localhost:8000/admin/features/create');
        await page.getByLabel('Name').fill(featureName);

        // Default scope is usually Global, we need to remove it or override.
        // Assuming Select component behaves like standard Filament Select.
        // Based on browser agent: remove Global, open dropdown, search.

        // Try to find the scope select. It might be labeled 'Scope'.
        // The browser agent clicked 'X' to remove 'Global'.
        // Let's assume the Select is searchable.

        // Filament Selects are tricky in E2E.
        // Click the select box
        const scopeSelect = page.locator('label:has-text("Scope")').locator('..').locator('button'); // usually the trigger
        // Or just click the area.
        // Let's try to clear existing if any provided. 
        // Browser agent clicked X at 937,254. 

        // Better approach for Filament Select:
        // 1. Click the field to open options.
        // 2. Type to search.
        // 3. Select option.

        // Force click the label or try to find the button more aggressively
        await page.getByLabel('Scope').click({ force: true });
        await page.waitForTimeout(500); // Wait for dropdown animation
        await page.keyboard.press('ArrowDown'); // Ensure list is active
        await page.keyboard.type(userName);
        await page.getByRole('option', { name: userName }).first().click();

        // Enable
        await page.getByLabel('Enabled').check(); // it's a toggle, check() works for checkbox/switch usually

        await page.click('button:has-text("Create")');

        // Verify
        await expect(page).toHaveURL(/.*features/);
        await expect(page.getByText(featureName)).toBeVisible();
        await expect(page.getByText(userName)).toBeVisible(); // Scope column should show user name
    });
});
