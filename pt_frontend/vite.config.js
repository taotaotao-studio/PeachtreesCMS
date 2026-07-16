import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(() => ({
  plugins: [react()],
  base: '/PeachtreesCMS/',
  server: {
    port: 5173,
    proxy: {
      // API 代理：/PeachtreesCMS/pt_api/xxx → http://localhost/PeachtreesCMS/pt_api/xxx
      '/PeachtreesCMS/pt_api/': {
        target: 'http://localhost',
        changeOrigin: true,
        configure: (proxy) => {
          proxy.on('proxyRes', (proxyRes) => {
            proxyRes.headers['cache-control'] = 'no-store, no-cache, must-revalidate'
            delete proxyRes.headers['etag']
            delete proxyRes.headers['last-modified']
          })
        }
      },
      // 上传文件代理：/PeachtreesCMS/upload/xxx → http://localhost/PeachtreesCMS/upload/xxx
      '/PeachtreesCMS/upload/': {
        target: 'http://localhost',
        changeOrigin: true
      },
      // 主题资源代理：/PeachtreesCMS/theme/xxx → http://localhost/PeachtreesCMS/theme/xxx
      '/PeachtreesCMS/theme/': {
        target: 'http://localhost',
        changeOrigin: true
      }
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        home: 'index.html',
        admin: 'admin.html'
      },
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            // React 核心库单独分块
            if (['react', 'react-dom', 'react-router-dom'].some(pkg => id.includes(pkg))) {
              return 'react-vendor'
            }
            // Tiptap 编辑器相关
            if (id.includes('@tiptap')) {
              return 'tiptap'
            }
            // Swiper
            if (id.includes('swiper')) {
              return 'swiper'
            }
          }
        }
      }
    },
    // 启用代码分割
    target: 'esnext',
    cssCodeSplit: true
  }
}))
