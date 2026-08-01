import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    // backend/(Laravel) serves this app from the same origin as its API -
    // build straight into its public/ dir. emptyOutDir must stay false or
    // every build wipes out Laravel's own public/index.php and .htaccess.
    outDir: '../backend/public',
    emptyOutDir: false,
  },
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
      // Sanctum SPA 인증의 CSRF 쿠키 발급 엔드포인트 - /api와 같은 백엔드로.
      '/sanctum': 'http://localhost:8000',
    },
  },
})
