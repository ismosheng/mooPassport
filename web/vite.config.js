import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: '127.0.0.1',
    port: 3000,
    proxy: {
      '/admin/v1': 'http://127.0.0.1:8787',
      '/passport': 'http://127.0.0.1:8787',
      '/oauth': {
        target: 'http://127.0.0.1:8787',
        // /oauth 既承载 API，也保留历史页面路径；浏览器导航必须交给 SPA，
        // 否则会把 login_required 的协议 JSON 直接显示给用户。
        bypass(request) {
          return request.method === 'GET' && request.headers.accept?.includes('text/html')
            ? '/index.html'
            : undefined
        },
      },
      '/.well-known': 'http://127.0.0.1:8787',
    },
  },
})
