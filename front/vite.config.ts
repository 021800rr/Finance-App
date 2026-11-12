/// <reference types="vitest/config" />
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // The browser asks the front for /api; Vite passes it on to the backend, so no CORS is needed.
    proxy: {
      '/api': 'http://finance-nginx-dev',
    },
  },
  // npm test: the *.test.ts(x) files next to the code, in a browser simulated by jsdom.
  test: {
    environment: 'jsdom',
    setupFiles: ['src/test/setup.ts'],
    // At most 4 test files at once. By default there is one per CPU core: on a 16-core machine 15 simulated browsers
    // start together, and the slowest interface tests then miss their 5 s limit though nothing is wrong.
    maxWorkers: 4,
    // Each worker builds its simulated browser (jsdom) once and runs every test file in a fresh context of it, so the
    // files stay isolated but no longer pay for a new browser each: the whole run takes about a third of the time.
    pool: 'vmThreads',
    // npm run coverage: which code the tests run, as a summary in the terminal and as coverage/index.html. Every source
    // file counts, also one that no test opens; the generated API types and the tests themselves do not.
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,tsx}'],
      exclude: ['src/generated/**', 'src/test/**', 'src/**/*.test.{ts,tsx}'],
      reporter: ['text-summary', 'html'],
    },
  },
})
