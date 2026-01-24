import { test, expect } from '@playwright/test';

test.describe('Documentation Routes', () => {
  test('should serve Swagger UI at /api/doc', async ({ page }) => {
    // WHEN: Accessing the documentation route
    const response = await page.goto('/api/doc');

    // THEN: Should return 200 OK
    expect(response?.status()).toBe(200);

    // AND: Should serve HTML content
    const contentType = response?.headers()['content-type'];
    expect(contentType).toContain('text/html');

    // AND: Should contain Swagger UI content
    await expect(page.locator('body')).toContainText(/swagger|openapi/i);
  });

  test('should serve OpenAPI YAML at /api/docs/openapi.yaml', async ({ request }) => {
    // WHEN: Accessing the OpenAPI YAML route
    const response = await request.get('/api/docs/openapi.yaml');

    // THEN: Should return 200 OK
    expect(response.status()).toBe(200);

    // AND: Should have correct Content-Type
    const contentType = response.headers()['content-type'];
    expect(contentType).toContain('application/x-yaml') || expect(contentType).toContain('text/yaml');

    // AND: Should contain OpenAPI content
    const body = await response.text();
    expect(body).toContain('openapi:');
    expect(body).toContain('paths:');
  });

  test('should be accessible without API key authentication', async ({ request }) => {
    // WHEN: Accessing documentation routes without API key
    const docResponse = await request.get('/api/doc');
    const yamlResponse = await request.get('/api/docs/openapi.yaml');

    // THEN: Both should return 200 OK (not 401 Unauthorized)
    expect(docResponse.status()).toBe(200);
    expect(yamlResponse.status()).toBe(200);
  });
});
