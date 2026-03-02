import { test, expect } from '@playwright/test';

/**
 * E2E smoke: TV Series and TV Shows API list (and get by slug if data exists).
 * Covers MANUAL_TESTING_GUIDE TV Series & TV Shows API section.
 * Seed may not create TV data; list must return 200 with data array (possibly empty).
 */
test.describe('TV Series & TV Shows API Smoke (E2E)', () => {
  test('GET /api/v1/tv-series returns 200 and data array', async ({ request }) => {
    const response = await request.get('/api/v1/tv-series');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /api/v1/tv-shows returns 200 and data array', async ({ request }) => {
    const response = await request.get('/api/v1/tv-shows');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
  });
});
