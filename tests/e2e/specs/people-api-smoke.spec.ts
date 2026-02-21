import { test, expect } from '@playwright/test';

/**
 * E2E smoke: People API list and get by slug (public endpoints, no API key).
 * Covers MANUAL_TESTING_GUIDE People API section.
 */
test.describe('People API Smoke (E2E)', () => {
  test('GET /api/v1/people returns 200 and data array', async ({ request }) => {
    const response = await request.get('/api/v1/people');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /api/v1/people/keanu-reeves returns 200 and person fields', async ({ request }) => {
    const response = await request.get('/api/v1/people/keanu-reeves');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('slug');
    expect(body).toHaveProperty('name');
    expect(body).toHaveProperty('birth_date');
  });
});
