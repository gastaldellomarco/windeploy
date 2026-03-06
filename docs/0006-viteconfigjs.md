<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# // ════════════════════════════════════════════════════════════════════

// FILE: vite.config.js
// Configurazione Vite per sviluppo locale con proxy verso Laravel/XAMPP
// ════════════════════════════════════════════════════════════════════
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
 
export default defineConfig({
  plugins: [react()],
 
  // ── PROXY: /api → Laravel su XAMPP ──
  // Durante sviluppo locale, tutte le chiamate a /api vengono
  // inoltrate a [http://windeploy.local](http://windeploy.local) (Apache + Laravel)
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://windeploy.local',
        changeOrigin: true,
        secure: false,
        // Non rimuove /api dal path — Laravel lo gestisce nel router
      },
      // Proxy per WebSocket (monitor realtime)
      '/ws': {
        target: 'ws://windeploy.local',
        ws: true,
        changeOrigin: true,
      }
    }
  },
 
  // ── BUILD per produzione (poi si copia sul server) ──
  build: {
    outDir: 'dist',
    // Il base path in produzione è la root del dominio
    base: '/',
  },
 
  // ── ALIAS per import più puliti ──
  resolve: {
    alias: {
      '@': '/src',
      '@components': '/src/components',
      '@pages': '/src/pages',
      '@api': '/src/api',
      '@store': '/src/store',
      '@hooks': '/src/hooks',
      '@utils': '/src/utils',
    }
  }
})
 
 
// ════════════════════════════════════════════════════════════════════
// FILE: package.json (solo la sezione scripts — da integrare)
// ════════════════════════════════════════════════════════════════════
// "scripts": {
//   "dev": "vite",                          ← sviluppo locale
//   "build": "vite build",                  ← build per produzione
//   "preview": "vite preview",              ← anteprima build
//   "lint": "eslint . --ext js,jsx"
// }
 
// ════════════════════════════════════════════════════════════════════
// FLUSSO DI SVILUPPO LOCALE COMPLETO:
// ════════════════════════════════════════════════════════════════════
// 1. Avvia XAMPP Control Panel → Start Apache + MySQL
// 2. Terminal 1 (cartella backend): php artisan serve --host=windeploy.local
//    OPPURE lascia che Apache serva direttamente (virtual host configurato)
// 3. Terminal 2 (cartella react): npm run dev
// 4. Browser: apri http://localhost:5173 per la web app React
//             Le chiamate /api vanno automaticamente a windeploy.local
// 5. phpMyAdmin: http://localhost/phpmyadmin

Il `vite.config.js` che hai incollato contiene un **bug critico** nel target del proxy: punta a `http://windeploy.local` invece di `http://windeploy.local.api` (o qualunque sia il Virtual Host del backend Laravel). Ecco l'analisi completa e la configurazione corretta.

## Problema: Conflict sul target del proxy

Dalla documentazione del tuo setup, il Virtual Host Apache è configurato così:[^1_1]

- `windeploy.local` → `frontend/` (file React statici)
- `windeploy.local.api` → `backend/public/` (Laravel)

Se il proxy Vite punta a `http://windeploy.local`, le chiamate `/api` finiscono al frontend Apache, non a Laravel. Il risultato è un loop o un 404 silenzioso.[^1_1]

## File corretto: `vite.config.js`

**File coinvolto:** `C:\xampp\htdocs\windeploy\frontend\vite.config.js`

```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],

  server: {
    port: 5173,
    proxy: {
      // ── /api → Laravel backend (Virtual Host separato) ──
      '/api': {
        target: 'http://windeploy.local.api',  // ← CORRETTO
        changeOrigin: true,
        secure: false,
        // Non strippare /api: Laravel lo gestisce nelle routes
      },
      // ── WebSocket per monitor realtime (Laravel Reverb) ──
      '/ws': {
        target: 'ws://windeploy.local.api',    // ← CORRETTO
        ws: true,
        changeOrigin: true,
      },
    },
  },

  build: {
    outDir: 'dist',
    base: '/',
  },

  resolve: {
    alias: {
      '@':            '/src',
      '@components':  '/src/components',
      '@pages':       '/src/pages',
      '@api':         '/src/api',
      '@store':       '/src/store',
      '@hooks':       '/src/hooks',
      '@utils':       '/src/utils',
    },
  },
})
```


## Struttura cartelle coinvolta

```
C:\xampp\htdocs\windeploy\
├── frontend\
│   ├── vite.config.js          ← questo file
│   ├── src\
│   │   ├── api\                ← axios client (usa /api/...)
│   │   ├── components\
│   │   ├── pages\
│   │   ├── store\              ← Zustand / Redux
│   │   ├── hooks\
│   │   └── utils\
│   └── package.json
└── backend\
    └── public\                 ← unico punto esposto da Apache
```


## File `hosts` Windows (prerequisito)

Apri `C:\Windows\System32\drivers\etc\hosts` **come Amministratore** e verifica siano presenti entrambe le righe:[^1_1]

```
127.0.0.1   windeploy.local
127.0.0.1   windeploy.local.api
```


## Checklist operativa

- [ ] `windeploy.local.api` risolve correttamente → apri `http://windeploy.local.api` nel browser, deve rispondere Laravel (pagina welcome o JSON)
- [ ] Virtual Host Apache per `windeploy.local.api` punta a `backend/public/` (non a `backend/`)
- [ ] `backend/.htaccess` o `backend/public/.htaccess` ha `mod_rewrite` attivo (`AllowOverride All` in httpd-vhosts.conf)
- [ ] `npm run dev` parte su `localhost:5173`
- [ ] Test proxy: `fetch('/api/test')` dalla console del browser deve restituire una risposta Laravel, non HTML di Apache


## ⚠️ Implicazioni di sicurezza

Il WebSocket `/ws` usa `ws://` (non `wss://`) perché è solo per sviluppo locale. In produzione su Ubuntu + Nginx + Cloudflare Tunnel, il WebSocket di Laravel Reverb deve essere tunnelato via `wss://` — Cloudflare gestisce la terminazione TLS, ma devi aggiungere una seconda regola `ingress` nel `config.yml` di cloudflared per la porta WebSocket (default Reverb: 8080).[^1_2]

## Nota su `php artisan serve` vs Apache

Se usi `php artisan serve --host=windeploy.local.api` invece del Virtual Host Apache, non devi creare la voce `windeploy.local.api` nel file `hosts` — basta puntare il proxy Vite a `http://windeploy.local.api:8000`. Tuttavia il Virtual Host Apache è preferibile per lo sviluppo perché non blocca un terminale e replica meglio l'ambiente di produzione Nginx.[^1_2]
<span style="display:none">[^1_3]</span>

<div align="center">⁂</div>

[^1_1]: 0003-setupiniziale.md

[^1_2]: 0002-ricerca-iniziale.md

[^1_3]: 0001-projectdescription.txt

