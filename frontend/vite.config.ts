import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  base: mode === 'production' ? 'https://spin2pay.com/' : '/',
  server: {
    port: 3000,
    host: '0.0.0.0', // Permitir conexiones externas
    strictPort: true,
    hmr: {
      overlay: false,
    },
    proxy: {
      '/api': {
        // Apuntar al servidor PHP 8.2 público en el puerto 8000
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
        // Mantener el prefijo /api para que public/index.php enrute correctamente
        rewrite: (path) => path.replace(/^\/api/, '/api'),
        configure: (proxy, options) => {
          proxy.on('proxyReq', (proxyReq, req, res) => {
            if ((req as any).headers.authorization) {
              proxyReq.setHeader('Authorization', (req as any).headers.authorization)
            }
            const xAuth = (req as any).headers['x-auth-token']
            if (xAuth) {
              proxyReq.setHeader('X-Auth-Token', xAuth)
            }
          })
        },
      },
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
}))