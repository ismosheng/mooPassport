import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 3000,
    proxy: {
      '/admin/v1': 'http://127.0.0.1:8787',
      '/passport': 'http://127.0.0.1:8787',
      '/oauth': 'http://127.0.0.1:8787',
      '/.well-known': 'http://127.0.0.1:8787',
    },
  },
})
