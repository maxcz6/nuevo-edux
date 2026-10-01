import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    allowedHosts: ['localhost', '127.0.0.1', 'nginx'],
    watch: {
      usePolling: true,
      interval: 1000
    },
    proxy: {
      '/api': {
        target: 'http://nginx:80',
        changeOrigin: true
      }
    }
  },

  build: {
    target: 'esnext',
    cssCodeSplit: true,
    // Minificación agresiva para producción
    minify: 'esbuild',
    // Separar sourcemaps del bundle
    sourcemap: false,
    chunkSizeWarningLimit: 700,
    rollupOptions: {
      output: {
        // Chunking manual para mejor caché
        manualChunks: {
          'vendor-core':     ['vue', 'vue-router', 'pinia'],
          'vendor-security': ['axios', 'jose'],
        },
        // Nombres con hash para cache-busting en producción
        chunkFileNames:  'assets/js/[name]-[hash].js',
        entryFileNames:  'assets/js/[name]-[hash].js',
        assetFileNames:  'assets/[ext]/[name]-[hash].[ext]',
      }
    }
  },

  // Optimizaciones de pre-bundling
  optimizeDeps: {
    include: ['vue', 'vue-router', 'pinia', 'axios', 'jose']
  }
})
