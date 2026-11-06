import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // The browser asks the front for /api; Vite passes it on to the backend, so no CORS is needed.
    proxy: {
      '/api': 'http://finance-nginx-dev',
    },
  },
})