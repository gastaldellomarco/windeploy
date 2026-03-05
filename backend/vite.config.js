import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'path'

export default defineConfig({
  // ── PLUGIN ──────────────────────────────────────────────────────────────
  plugins: [
    react(), // Abilita JSX transform + Fast Refresh in sviluppo
  ],

  // ── ALIAS ───────────────────────────────────────────────────────────────
  resolve: {
    alias: {
      // Permette import come: import client from '@/api/client'
      // invece di: import client from '../../api/client'
      '@': resolve(__dirname, 'src'),
      '@components': resolve(__dirname, 'src/components'),
      '@pages':      resolve(__dirname, 'src/pages'),
      '@api':        resolve(__dirname, 'src/api'),
      '@store':      resolve(__dirname, 'src/store'),
      '@hooks':      resolve(__dirname, 'src/hooks'),
      '@utils':      resolve(__dirname, 'src/utils'),
    },
  },

  // ── DEV SERVER ──────────────────────────────────────────────────────────
  server: {
    // Porta esplicita — evita che Vite usi 5174 se 5173 è occupata
    port: 5173,

    // 0.0.0.0 = raggiungibile da altri device in LAN (es. test su tablet)
    // Cambia in 'localhost' se non hai bisogno di accesso LAN
    host: '0.0.0.0',

    // Apre automaticamente il browser all'avvio di `npm run dev`
    open: false,

    // ── PROXY ─────────────────────────────────────────────────────────────
    // Tutte le chiamate a /api/* vengono silenziosamente inoltrate al
    // backend Laravel su Apache. Il browser vede solo localhost:5173
    // → nessun errore CORS in sviluppo.
    proxy: {
      // ── REST API ──────────────────────────────────────────────────────
      '/api': {
        // Target: Virtual Host Apache separato per Laravel
        // Corrisponde a APP_URL nel backend/.env
        target: 'http://windeploy.local.api',

        // changeOrigin: true → riscrive l'header Host nella richiesta
        // al backend come se venisse da windeploy.local.api
        // Necessario quando target ≠ localhost
        changeOrigin: true,

        // secure: false → ignora errori SSL (ok in HTTP locale)
        secure: false,

        // NON aggiungiamo rewrite: Laravel gestisce /api/* nelle routes
        // grazie al prefix 'api' in routes/api.php
      },

      // ── WEBSOCKET (Monitor Realtime) ───────────────────────────────────
      // Proxy per Laravel Reverb (WebSocket nativo Laravel 11+)
      // Il monitor di WinDeploy usa polling o WS per aggiornamenti live
      '/ws': {
        target: 'ws://windeploy.local.api',

        // ws: true → attiva il protocollo WebSocket nel proxy Vite
        ws: true,

        changeOrigin: true,
        secure: false,
      },
    },
  },

  // ── BUILD PRODUZIONE ────────────────────────────────────────────────────
  build: {
    // Output nella cartella dist/ — copiata su Ubuntu/Nginx in produzione
    outDir: 'dist',

    // base path relativo: la build funziona in qualsiasi sottocartella
    // Cambia in '/windeploy/' se il deploy è su un subpath Nginx
    // base: '/',

    // Svuota outDir prima di ogni build
    emptyOutDir: true,

    // Source map solo in sviluppo locale (non esporre in produzione)
    sourcemap: false,
  },
})
