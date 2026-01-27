import { execSync } from 'child_process';
import * as path from 'path';

async function globalSetup() {
  const projectRoot = path.resolve(__dirname, '../../');
  // Use -T to avoid TTY issues in CI/Script execution
  try {
    execSync('docker compose exec -T php php artisan db:seed', { cwd: projectRoot, stdio: 'inherit' });
  } catch (e) {
    console.error('Seeding failed (non-critical if duplicate entries):', e.message);
  }
}

export default globalSetup;
