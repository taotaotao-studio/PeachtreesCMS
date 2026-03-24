import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: '/',
  server: {
    port: 5173,
    proxy: {
      '/pt_api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/pt_api/, '/PeachtreesCMS/api')
      },
      '/upload': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/upload/, '/PeachtreesCMS/upload')
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
        manualChunks: {
          // React 核心库单独分块
          'react-vendor': ['react', 'react-dom', 'react-router-dom'],
          // Tiptap 编辑器相关 - 按需加载
          'tiptap': [
            '@tiptap/react',
            '@tiptap/starter-kit',
            '@tiptap/extension-image',
            '@tiptap/extension-link',
            '@tiptap/extension-table',
            '@tiptap/extension-table-cell',
            '@tiptap/extension-table-header',
            '@tiptap/extension-table-row'
          ],
          // Swiper 单独分块
          'swiper': ['swiper']
        }
      }
    },
    // 启用代码分割
    target: 'esnext',
    cssCodeSplit: true
  }
})
