import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import basicSsl from '@vitejs/plugin-basic-ssl'

export default defineConfig({
  plugins: [
    basicSsl(),
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['logo.jpeg'],
      manifest: {
        name: 'RocadaMed — Выездные сотрудники',
        short_name: 'RocadaMed',
        description: 'Приложение для выездных сотрудников RocadaMed',
        theme_color: '#0A1628',
        background_color: '#0A1628',
        display: 'standalone',
        scope: '/',
        start_url: '/',
        icons: [
          {
            src: 'logo.jpeg',
            sizes: '192x192',
            type: 'image/jpeg'
          },
          {
            src: 'logo.jpeg',
            sizes: '512x512',
            type: 'image/jpeg',
            purpose: 'any maskable'
          }
        ]
      }
    })
  ],
  server: {
    host: true,
    port: 5173,
    proxy: {
      // Наш модуль API (для визитов, данных CRM и т.д.)
      '/b24api': {
        target: 'https://office.rocadatech.ru',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => {
          const [pathname, qs] = path.split('?')
          const route = pathname.replace(/^\/b24api\/?/, '')
          const query = qs ? `route=${route}&${qs}` : `route=${route}`
          return `/local/modules/rocada.visits/lib/router.php?${query}`
        }
      },
      // OAuth2 token exchange → центральный сервер Битрикс (НЕ портал!)
      '/b24oauth': {
        target: 'https://oauth.bitrix.info',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/b24oauth/, '/oauth')
      },
      // REST API Битрикс24 — для получения профиля пользователя
      '/b24rest': {
        target: 'https://office.rocadatech.ru',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/b24rest/, '/rest')
      }
    }
  }
})
