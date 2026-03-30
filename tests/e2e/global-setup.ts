import { execSync } from 'child_process';
import * as path from 'path';

async function globalSetup() {
  const projectRoot = path.resolve(__dirname, '../../');
  // Use -T to avoid TTY issues in CI/Script execution
  const composeExec = 'docker compose -f compose.yml -f compose.e2e.yml exec -T php';
  try {
    execSync(`${composeExec} php artisan db:seed`, { cwd: projectRoot, stdio: 'inherit' });
  } catch (e) {
    console.error('Seeding failed (non-critical if duplicate entries):', (e as Error).message);
  }
  try {
    execSync(`${composeExec} php artisan test:prepare-e2e`, { cwd: projectRoot, stdio: 'inherit' });
  } catch (e) {
    console.error('E2E prepare failed:', (e as Error).message);
  }
  try {
    execSync(`${composeExec} php artisan config:clear`, { cwd: projectRoot, stdio: 'inherit' });
  } catch (e) {
    console.warn('config:clear failed (non-critical). Ensure app is running with e2e override for admin login:', (e as Error).message);
  }

  const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';
  const expectedAppUrl = 'http://127.0.0.1:8000';
  let res: Response;
  try {
    res = await fetch(`${baseURL}/api/v1/health/db`);
  } catch (e) {
    throw new Error(
      `App not reachable at ${baseURL}. Start the stack with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate`
    );
  }
  if (!res.ok) {
    throw new Error(
      `App health check failed (${res.status}) at ${baseURL}. Start the stack with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate`
    );
  }
  try {
    const data = (await res.json()) as { app_url?: string };
    if (data.app_url != null && data.app_url !== expectedAppUrl) {
      throw new Error(
        `E2E requires APP_URL to match Playwright baseURL. Current app_url is "${data.app_url}". Start the stack with: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate`
      );
    }
  } catch (e) {
    if (e instanceof Error && e.message.includes('E2E requires APP_URL')) {
      throw e;
    }
    throw new Error(
      `Could not verify app_url from ${baseURL}/api/v1/health/db. Start the stack with E2E override: docker compose -f compose.yml -f compose.e2e.yml up -d --force-recreate`,
      { cause: e }
    );
  }
}

export default globalSetup;
