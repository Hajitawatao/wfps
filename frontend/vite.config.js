// Vite 7 + Vue + Tailwind v4. `npm run dev` at the project root starts this together with php -S on 127.0.0.1:8001.
// 127.0.0.1, not localhost: on Windows "localhost" can resolve to ::1 while php -S listens on IPv4 only.
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    host: true,
    port: 5173,
    proxy: { '/api': 'http://127.0.0.1:8001' }
  },
  build: { outDir: 'dist', emptyOutDir: true }
})
