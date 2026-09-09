import { defineConfig } from '@playwright/test';

const backendDir = process.env.FAMTASTIC_RUNTIME_BACKEND_DIR;
const frontendDir = process.env.FAMTASTIC_RUNTIME_FRONTEND_DIR;
const backendUrl = process.env.FAMTASTIC_RUNTIME_BACKEND_URL || 'http://127.0.0.1:18081';

if (!backendDir || !frontendDir) {
  throw new Error('Set FAMTASTIC_RUNTIME_BACKEND_DIR and FAMTASTIC_RUNTIME_FRONTEND_DIR to isolated runtime copies.');
}

const webServer = [
  {
    command: 'npm run dev -- --host 127.0.0.1 --port 18173',
    cwd: frontendDir,
    env: { ...process.env, VITE_DRUPAL_PROXY_TARGET: backendUrl },
    url: 'http://127.0.0.1:18173/',
    reuseExistingServer: false,
  },
];

if (process.env.FAMTASTIC_RUNTIME_SKIP_DRUPAL !== '1') {
  webServer.unshift({
    // Drupal service definitions may contain docroot-relative paths, so the
    // built-in server must run from web/ rather than backend/.
    command: 'php -S 127.0.0.1:18081 .ht.router.php',
    cwd: `${backendDir}/web`,
    url: `${backendUrl}/user/login`,
    reuseExistingServer: false,
  });
}

export default defineConfig({
  testDir: './e2e',
  testMatch: 'isolated-command-center-runtime.spec.js',
  timeout: 60_000,
  reporter: [['list'], ['html', { outputFolder: '../.artifacts/runtime-command-center-report', open: 'never' }]],
  outputDir: '../.artifacts/runtime-command-center-results',
  use: {
    baseURL: 'http://127.0.0.1:18173',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  webServer,
});
