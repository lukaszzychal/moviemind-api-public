
import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('API Security & Authentication', () => {
    let apiKey: string;
    let apiKeyId: string;
    let unauthorizedKey = 'mm_invalid_key_12345';
    let limitedKey: string; // Key for a plan without 'ai_generate' feature

    test.beforeAll(() => {
        // Setup: Ensure Pro plan has 'ai_generate' and create a valid key
        // Also create a Limited key (Free plan? or just remove feature)
        // Run external PHP script to setup keys
        const command = 'docker compose exec -T php php tests/setup_keys.php';

        try {
            const output = execSync(command).toString();
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
                throw new Error('Could not parse JSON from tinker output: ' + output);
            }
        } catch (error) {
            console.error('Setup failed:', error);
            throw error;
        }
    });

    test.afterAll(() => {
        // Cleanup keys
        if (apiKeyId) {
            // Simple cleanup using tinker is fine for single line deletion
            const cleanupCmd = `docker compose exec -T php php artisan tinker --execute="\\App\\Models\\ApiKey::where('id', '${apiKeyId}')->delete();"`;
            try {
                execSync(cleanupCmd);
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

        // It might be 202 (Accepted/Queued) or 429 (Rate Limit if test runs fast or limits are low)
        // Since we created a fresh key, it should ideally be 202.
        expect([202, 429, 200]).toContain(response.status());

        if (response.status() === 202) {
            const body = await response.json();
            expect(body).toHaveProperty('job_id');
        }
    });
});
