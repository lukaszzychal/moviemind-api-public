import { execSync } from 'child_process';
import * as path from 'path';

async function globalSetup() {
  const apiDir = path.resolve(__dirname, '../../api');
  execSync('php artisan db:seed', { cwd: apiDir, stdio: 'inherit' });
}

export default globalSetup;
