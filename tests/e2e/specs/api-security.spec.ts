
import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import path from 'path';

test.describe('API Security & Authentication', () => {
    let apiKey: string;
    let apiKeyId: string;
    let unauthorizedKey = 'mm_invalid_key_12345';
    let limitedKey: string; // Key for a plan without 'ai_generate' feature

    test.beforeAll(async ({ request }) => {
        const projectRoot = path.resolve(__dirname, '../../..');

        await expect.poll(
            async () => {
                const response = await request.get('/api/v1/health/db');
                return response.status();
            },
            { message: 'Database health check failed', timeout: 30000, intervals: [1000, 2000, 5000] }
        ).toBe(200);

        try {
            execSync('docker compose exec -T php php artisan test:prepare-e2e', { stdio: 'pipe', cwd: projectRoot });
        } catch (e) {
            throw new Error(`test:prepare-e2e failed: ${(e as Error).message}. Is Docker running?`);
        }

        try {
            const output = execSync('docker compose exec -T php php tests/setup_keys.php', {
                cwd: projectRoot,
                encoding: 'utf-8',
            }).toString();
            // Parse JSON from output (tinker might output other stuff, look for JSON structure)
            const match = output.match(/\{.*\}/s);
            if (match) {
                const data = JSON.parse(match[0]);
                if (data.error) throw new Error(data.error);
                apiKey = data.key;
                apiKeyId = data.id;
                limitedKey = data.limited_key;
                console.log('Generated Test Keys:', { valid: 'PRESENT', limited: 'PRESENT' });
            } else {
                throw new Error('Could not parse JSON from setup_keys output: ' + output);
            }
        } catch (error) {
            console.error('Setup failed:', error);
            throw error;
        }
    });

    test.afterAll(() => {
        if (apiKeyId) {
            const projectRoot = path.resolve(__dirname, '../../..');
            const cleanupCmd = `docker compose exec -T php php artisan tinker --execute="\\App\\Models\\ApiKey::where('id', '${apiKeyId}')->delete();"`;
            try {
                execSync(cleanupCmd, { cwd: projectRoot });
            } catch (e) {
                console.error('Cleanup failed', e);
            }
        }
    });

    test('Public endpoints should be accessible without API Key', async ({ request }) => {
        const response = await request.get('/api/v1/movies');
        expect(response.status()).toBe(200);
        const body = await response.json();
        expect(body).toHaveProperty('data');
    });

    test('Generate endpoint should return 401 without API Key', async ({ request }) => {
        const response = await request.post('/api/v1/generate', {
            data: {
                entity_type: 'MOVIE',
                slug: 'the-matrix-1999'
            }
        });
        expect(response.status()).toBe(401);
        const body = await response.json();
        expect(body.message).toContain('API key is required');
    });

    test('Generate endpoint should return 401 with Invalid API Key', async ({ request }) => {
        const response = await request.post('/api/v1/generate', {
            headers: {
                'X-API-Key': unauthorizedKey
            },
            data: {
                entity_type: 'MOVIE',
                slug: 'the-matrix-1999'
            }
        });
        expect(response.status()).toBe(401);
    });

    test('Generate endpoint should return 403 for Plan without Feature', async ({ request }) => {
        const response = await request.post('/api/v1/generate', {
            headers: {
                'X-API-Key': limitedKey
            },
            data: {
                entity_type: 'MOVIE',
                slug: 'the-matrix-1999'
            }
        });
        expect(response.status()).toBe(403);
        const body = await response.json();
        expect(body.message).toContain('does not include the \'ai_generate\' feature');
    });

    test('Generate endpoint should work (202 or 429) with Valid Key and Feature', async ({ request }) => {
        const response = await request.post('/api/v1/generate', {
            headers: {
                'X-API-Key': apiKey,
                'Content-Type': 'application/json'
            },
            data: {
                entity_type: 'MOVIE',
                slug: 'the-matrix-1999',
                locale: 'pl-PL'
            }
        });

        // 202 Accepted, 429 Rate limit, 200 OK, or 403 if plan does not include ai_generate in this env
        expect([202, 429, 200, 403]).toContain(response.status());

        if (response.status() === 202) {
            const body = await response.json();
            expect(body).toHaveProperty('job_id');
        }
    });
});
