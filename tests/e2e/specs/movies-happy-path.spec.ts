import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

/**
 * E2E happy path: list movies -> search -> get by slug -> generate -> get job status.
 * Covers MANUAL_TESTING_GUIDE Movies API + Generate + Jobs (smoke on live API).
 */
test.describe('Movies API Happy Path (E2E)', () => {
  let apiKey: string;
  let apiKeyId: string;

    test.beforeAll(async ({ request }) => {
    await expect
      .poll(
        async () => {
          const response = await request.get('/api/v1/health/db');
          return response.status();
        },
        { message: 'Database health check failed', timeout: 30000, intervals: [1000, 2000, 5000] }
      )
      .toBe(200);

    const projectRoot = path.resolve(__dirname, '../../..');
    try {
      execSync('docker compose exec -T php php artisan test:prepare-e2e', { stdio: 'pipe', cwd: projectRoot });
    } catch (e) {
      throw new Error(`test:prepare-e2e failed: ${(e as Error).message}. Is Docker running?`);
    }
    try {
      const output = execSync('docker compose exec -T php php tests/setup_keys.php', {
        cwd: projectRoot,
        encoding: 'utf-8',
      });
      const match = output.match(/\{.*\}/s);
      if (!match) throw new Error('Could not parse JSON from setup_keys output');
      const data = JSON.parse(match[0]);
      if (data.error) throw new Error(data.error);
      apiKey = data.key;
      apiKeyId = data.id;
    } catch (error) {
      console.error('Setup keys failed:', error);
      throw error;
    }
  });

  test.afterAll(() => {
    if (apiKeyId) {
      try {
        execSync(
          `docker compose exec -T php php artisan tinker --execute="\\App\\Models\\ApiKey::where('id', '${apiKeyId}')->delete();"`,
          { cwd: path.resolve(__dirname, '../../..') }
        );
      } catch (e) {
        console.error('Cleanup key failed', e);
      }
    }
  });

  test('GET /api/v1/movies returns 200 and data array', async ({ request }) => {
    const response = await request.get('/api/v1/movies');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('data');
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /api/v1/movies/search?q=matrix returns 200', async ({ request }) => {
    const response = await request.get('/api/v1/movies/search?q=matrix');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('results');
    expect(Array.isArray(body.results)).toBe(true);
  });

  test('GET /api/v1/movies/the-matrix-1999 returns 200 and movie fields', async ({ request }) => {
    const response = await request.get('/api/v1/movies/the-matrix-1999');
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body).toHaveProperty('slug', 'the-matrix-1999');
    expect(body).toHaveProperty('title');
    expect(body).toHaveProperty('release_year');
  });

  test('POST /api/v1/generate returns 202 and job_id', async ({ request }) => {
    const response = await request.post('/api/v1/generate', {
      headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
      data: {
        entity_type: 'MOVIE',
        slug: 'the-matrix-1999',
        locale: 'pl-PL',
      },
    });
    // 202 Accepted, 429 Rate limit, or 403 if plan does not include ai_generate in this env
    expect([202, 429, 403]).toContain(response.status());
    if (response.status() === 202) {
      const body = await response.json();
      expect(body).toHaveProperty('job_id');
    }
  });

  test('GET /api/v1/jobs/{id} returns 200 and status', async ({ request }) => {
    const genResponse = await request.post('/api/v1/generate', {
      headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' },
      data: { entity_type: 'MOVIE', slug: 'inception-2010', locale: 'en-US' },
    });
    if (genResponse.status() !== 202) {
      test.skip(true, 'Generate returned non-202 (e.g. 429), skip job check');
      return;
    }
    const genBody = await genResponse.json();
    const jobId = genBody.job_id;
    const jobResponse = await request.get(`/api/v1/jobs/${jobId}`, {
      headers: { 'X-API-Key': apiKey },
    });
    expect(jobResponse.status()).toBe(200);
    const jobBody = await jobResponse.json();
    expect(jobBody).toHaveProperty('status');
    const allowed = ['pending', 'processing', 'done', 'failed', 'PENDING', 'PROCESSING', 'DONE', 'FAILED'];
    expect(allowed).toContain(jobBody.status);
  });
});
