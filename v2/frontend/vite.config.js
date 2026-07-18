import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Dev server proxies the Drupal-backed paths (/jsonapi for content, /api for
// the prospect pipeline, /oauth for admin login) so the SPA is same-origin and
// needs no CORS. In production the client uses the absolute VITE_DRUPAL_BASE_URL
// instead (see src/api/drupal.js and src/api/pipeline.js).
const backend = process.env.VITE_DRUPAL_PROXY_TARGET || 'http://localhost:8080';
const proxyOpts = { target: backend, changeOrigin: true, secure: false };

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/jsonapi': proxyOpts,
      '/api': proxyOpts,
      '/oauth': proxyOpts,
    },
  },
  preview: {
    port: 4173,
  },
});
