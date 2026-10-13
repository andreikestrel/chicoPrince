import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', '')

  return {
    plugins: [vue()],
    server: {
      port: 5173,
      strictPort: true,
      watch: { usePolling: true },
      proxy: {
        '/api': {
          target: env.API_PROXY_TARGET || 'http://127.0.0.1:8081',
          // Preserva o host público nos links de paginação gerados pela API.
          changeOrigin: false,
        },
      },
    },
  }
})
