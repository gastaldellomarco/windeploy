<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior frontend developer. Progetto: WinDeploy

Stack: React 18 + Vite, Laravel 11 su XAMPP Windows,
backend servito da Apache virtual host (windeploy.local)
oppure php artisan serve (127.0.0.1:8000)

═══ CONTESTO ═══
Il frontend React gira su Vite dev server (localhost:5173).
Il backend Laravel gira su XAMPP Apache (windeploy.local o
windeploy.local.api) oppure su php artisan serve (127.0.0.1:8000).
Senza proxy in vite.config.js, ogni chiamata a /api/* va su
localhost:5173/api → 404. Con origini diverse → CORS error.

L'issue menziona due possibili target:

- [http://windeploy.local.api](http://windeploy.local.api) (virtual host Apache separato)
- [http://127.0.0.1:8000](http://127.0.0.1:8000) (php artisan serve)
Va chiarito quale dei due è quello corretto e attivo.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ frontend/vite.config.js (attuale, anche se incompleto)
→ frontend/src/api/client.js (axios instance — verifica baseURL)
→ frontend/src/api/auth.js (una chiamata API come esempio)
→ frontend/.env o frontend/.env.local (se esistono)
→ C:/xampp/apache/conf/extra/httpd-vhosts.conf
(per vedere i virtual host Apache configurati)
→ backend/config/cors.php
→ backend/.env (solo le righe APP_URL e SANCTUM_STATEFUL_DOMAINS)

═══ COSA VOGLIO ═══

1. CHIARIMENTO TARGET:
Dai file allegati dimmi quale è il target corretto per il proxy:
    - windeploy.local (virtual host semplice)
    - windeploy.local.api (virtual host separato solo per API)
    - 127.0.0.1:8000 (php artisan serve)
    - 127.0.0.1:80 (Apache porta 80)
Spiega quale setup è più pratico per sviluppo locale su XAMPP
e quale consigliesti per evitare problemi in futuro.
2. VITE.CONFIG.JS COMPLETO:
Fornisci il file vite.config.js completo con:
    - Proxy /api → backend Laravel (target corretto)
    - Proxy /ws → WebSocket per monitor realtime
(target ws://... con ws:true)
    - Alias @ → /src per import puliti
    - Porta 5173 esplicita
    - Host 0.0.0.0 se vuoi raggiungere Vite da altri device in LAN
    - build.outDir: 'dist' per la build di produzione
Aggiungi commenti inline che spiegano ogni sezione.
3. AXIOS CLIENT (src/api/client.js):
Verifica che baseURL sia impostato a '/api' (relativo, senza host)
così il proxy Vite intercetta correttamente.
Se è impostato a 'http://windeploy.local/api' in assoluto
il proxy NON funziona — fornisci la versione corretta.
Includi:
    - Interceptor request: aggiunge Authorization: Bearer {token}
letto dallo authStore Zustand
    - Interceptor response: gestisce 401 → logout automatico,
500 → toast errore generico
    - Timeout: 30000ms (30 secondi)
    - withCredentials: true (necessario per Sanctum cookie)
4. CORS BACKEND (config/cors.php):
Verifica che allowed_origins includa:
    - http://localhost:5173 (Vite dev server)
    - [http://windeploy.local](http://windeploy.local) (se si apre direttamente)
Fornisci il file cors.php completo corretto.
Verifica anche che SANCTUM_STATEFUL_DOMAINS in .env
includa localhost:5173.
5. ENDPOINT DI TEST:
Aggiungi in backend/routes/api.php una route ping pubblica
(senza middleware auth) per testare che il proxy funzioni:
Route::get('/ping', fn() => response()->json(['status' => 'ok', 'time' => now()]));

Poi fornisci i comandi per testare in sequenza:
a) curl [http://windeploy.local/api/ping](http://windeploy.local/api/ping) → verifica backend risponde
b) Browser DevTools → fetch('/api/ping') nella console
con Vite in esecuzione → verifica proxy funziona
c) Network tab: verifica che la richiesta vada a
localhost:5173/api/ping (proxy) NON a windeploy.local direttamente
6. .ENV FRONTEND:
Se necessario, crea frontend/.env.local con variabili Vite:
VITE_API_BASE_URL=/api
VITE_APP_NAME=WinDeploy
VITE_ENV=local
Spiega come usarle in React (import.meta.env.VITE_*)
7. CHECKLIST VERIFICA FINALE:
Fornisci una checklist step-by-step da eseguire per
confermare che proxy + CORS + axios funzionino tutti insieme:
[ ] Apache/XAMPP avviato e windeploy.local risponde
[ ] npm run dev → Vite avviato su localhost:5173
[ ] /api/ping risponde via proxy
[ ] Login da React funziona (token salvato in localStorage)
[ ] Network tab non mostra errori CORS
[ ] Token allegato all'header di ogni chiamata successiva
8. COMANDI GIT:
Commit message convenzionale per questa fix.
Nota: .env.local va in .gitignore — verificalo e aggiungilo se manca.

Ogni file con percorso completo in intestazione.
Codice completo, niente abbreviazioni. Commenti in italiano.
vite.config.js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://windeploy.local.api',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
client.js
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// Creiamo una singola instance Axios per tutto il frontend
const client = axios.create({
  baseURL: '/api',
  withCredentials: true, // enable cookies for Sanctum; ensure CORS allows credentials
  timeout: 15000,
});

// Force Accept: application/json for all requests so Laravel treats them as API calls
client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};
    config.headers.Accept = 'application/json';

const { token } = useAuthStore.getState();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor: se 401 → logout e redirect a /login
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      const { logout } = useAuthStore.getState();
      logout();

// During debugging avoid forcing a hard redirect which hides the original response.
  // Comment out the rough redirect so you can inspect the 401 response in DevTools.
  // if (window.location.pathname !== '/login') {
  //   window.location.href = '/login';
  // }
    }

return Promise.reject(error);
  }
);

export default client;

auth.js
// Minimal mock implementations for auth API used in hooks.
// Replace these with real HTTP calls to the backend when available.
export async function loginApi(_credentials) {
    // Simulate successful login
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({ token: 'fake-token', user: { id: 1, name: 'Demo User', role: 'admin' } });
        }, 200);
    });
}

export async function logoutApi() {
    return new Promise((resolve) => setTimeout(resolve, 100));
}

export async function meApi() {
    return new Promise((resolve) => setTimeout(() => resolve({ id: 1, name: 'Demo User', role: 'admin' }), 100));
}

export default {
    loginApi,
    logoutApi,
    meApi,
};
cors.php
<?php

return [

/*
    |--------------------------------------------------------------------------
    | Paths abilitati al CORS
    |--------------------------------------------------------------------------
    |
    | Includiamo tutte le API e la route di Sanctum per il CSRF cookie.
    |
    */

'paths' => ['api/*', 'sanctum/csrf-cookie'],

/*
    |--------------------------------------------------------------------------
    | Metodi HTTP consentiti
    |--------------------------------------------------------------------------
    */

'allowed_methods' => ['*'],

/*
    |--------------------------------------------------------------------------
    | Origini consentite
    |--------------------------------------------------------------------------
    |
    | Ambiente XAMPP locale:
    | - Vite React:  http://localhost:5173
    | - Virtual host: [http://windeploy.local](http://windeploy.local)
    |
    */

'allowed_origins' => [
        'http://localhost:5173',
        'http://windeploy.local',
    ],

'allowed_origins_patterns' => [],

/*
    |--------------------------------------------------------------------------
    | Header consentiti
    |--------------------------------------------------------------------------
    */

'allowed_headers' => ['*'],

/*
    |--------------------------------------------------------------------------
    | Header esposti
    |--------------------------------------------------------------------------
    */

'exposed_headers' => [],

/*
    |--------------------------------------------------------------------------
    | Cache CORS (secondi)
    |--------------------------------------------------------------------------
    */

'max_age' => 0,

/*
    |--------------------------------------------------------------------------
    | Invio credenziali (cookie, Authorization header)
    |--------------------------------------------------------------------------
    |
    | Necessario per SPA + Sanctum con cookie di sessione.
    |
    */

'supports_credentials' => true,
];
.env

# File: C:\xampp\htdocs\windeploy\backend\.env

APP_NAME=WinDeploy
APP_ENV=local
APP_KEY=            \# php artisan key:generate
APP_DEBUG=true
APP_TIMEZONE=Europe/Rome

# Allinea con il virtual host del backend in httpd-vhosts.conf

APP_URL=http://windeploy.local.api
ASSET_URL=http://windeploy.local.api

# ── DATABASE ──

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=windeploy
DB_USERNAME=
DB_PASSWORD=

# ── CACHE / SESSIONI ──

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=false

# ── QUEUE ──

QUEUE_CONNECTION=sync

# ── LOGGING ──

LOG_CHANNEL=daily
LOG_LEVEL=debug

# ── JWT (agent Windows) ──

JWT_SECRET=          \# php artisan jwt:secret
JWT_TTL=240
JWT_REFRESH_TTL=20160   \# 2 settimane; non mettere 0

# ── SANCTUM (SPA React) ──

# windeploy.local = virtual host frontend, localhost:5173 = Vite dev server

SANCTUM_STATEFUL_DOMAINS=localhost:5173,windeploy.local

# Disabilita DebugBar per le richieste API (evita HTML injection)

DEBUGBAR_ENABLED=false

# Broadcasting: usa 'log' in locale (evita eccezioni Pusher/Redis non configurati)

BROADCAST_DRIVER=log

# ── MAIL ──

MAIL_MAILER=log
[MAIL_FROM_ADDRESS=noreply@windeploy.local](mailto:MAIL_FROM_ADDRESS=noreply@windeploy.local)
MAIL_FROM_NAME=WinDeploy

Ho tutto il contesto necessario. Ecco la risposta completa e operativa.

***

## 1. Chiarimento Target del Proxy

**Target corretto per WinDeploy: `http://windeploy.local.api`**

Dal tuo `.env` risulta chiaramente `APP_URL=http://windeploy.local.api`, il che conferma che il Virtual Host Apache è configurato su quel dominio per Laravel. La struttura del progetto prevede due VHost separati: `windeploy.local` → frontend (React statici), `windeploy.local.api` → `backend/public` (Laravel). Il `vite.config.js` che hai allegato è già corretto su questo punto — punta a `http://windeploy.local.api` e non al frontend.[^1_1][^1_2]


| Opzione | Quando usarla | Pro | Contro |
| :-- | :-- | :-- | :-- |
| `http://windeploy.local.api` ✅ | VHost Apache attivo (raccomandato) | Replica produzione, non blocca terminali, stabile | Richiede hosts Windows configurato |
| `http://127.0.0.1:8000` | `php artisan serve` in backup | Zero config Apache | Blocca un terminale, instabile per WebSocket |
| `http://windeploy.local` | Mai — è il frontend | — | Loop o 404 silenzioso |
| `http://127.0.0.1:80` | Solo se Apache non ha VHost | Semplice | Non separa frontend/backend |

**Prerequisito una-tantum**: verifica che `C:\Windows\System32\drivers\etc\hosts` contenga (aprire Notepad come Amministratore):[^1_2]

```
127.0.0.1  windeploy.local
127.0.0.1  windeploy.local.api
```


***

## 2. `vite.config.js` Completo

**File:** `C:\xampp\htdocs\windeploy\frontend\vite.config.js`

```js
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
```

> ⚠️ **Sicurezza**: `host: '0.0.0.0'` espone Vite su tutta la LAN locale. Accettabile in sviluppo, ma assicurati che il firewall Windows blocchi la porta 5173 dall'esterno. In produzione non usi mai il dev server.

***

## 3. Axios Client Aggiornato

**File:** `C:\xampp\htdocs\windeploy\frontend\src\api\client.js`

Il tuo `client.js` attuale è quasi corretto: `baseURL: '/api'` è giusto (relativo = proxy Vite intercetta). Ho aggiunto la gestione 500 con toast, aumentato il timeout a 30s e riattivato il redirect 401 con guard.[^1_3]

```js
import axios from 'axios';
import { useAuthStore } from '../store/authStore';

// ── NOTA ARCHITETTURALE ────────────────────────────────────────────────────
// baseURL è '/api' (relativo, senza host).
// In DEV: Vite intercetta /api/* e lo proxy verso http://windeploy.local.api
// In PROD: Nginx serve React e fa reverse proxy /api → Laravel backend
// NON usare mai 'http://windeploy.local.api/api' qui —
// bypassa il proxy e causa CORS in sviluppo.
// ──────────────────────────────────────────────────────────────────────────

const client = axios.create({
  baseURL: '/api',

  // withCredentials: true → invia cookie di sessione Sanctum
  // Necessario per il flusso SPA con CSRF token
  withCredentials: true,

  // Timeout aumentato a 30s per operazioni lunghe (es. avvio wizard)
  timeout: 30000,
});

// ── INTERCEPTOR REQUEST ───────────────────────────────────────────────────
// Aggiunge automaticamente a ogni chiamata:
// 1. Accept: application/json → Laravel risponde sempre JSON, mai HTML
// 2. Authorization: Bearer <token> → letto da Zustand authStore
client.interceptors.request.use(
  (config) => {
    config.headers = config.headers ?? {};

    // Forza risposta JSON — evita che Laravel restituisca redirect HTML
    config.headers['Accept'] = 'application/json';

    // Legge il token dallo store Zustand (non da React hook — siamo fuori da component tree)
    const { token } = useAuthStore.getState();
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// ── INTERCEPTOR RESPONSE ──────────────────────────────────────────────────
// Gestione centralizzata degli errori HTTP:
// - 401 Unauthorized → logout automatico + redirect a /login
// - 422 Unprocessable → errori di validazione Laravel (gestiti nei singoli hook)
// - 500 Server Error → toast errore generico
client.interceptors.response.use(
  (response) => response, // Risposta OK: pass-through senza modifiche

  (error) => {
    const status = error?.response?.status;

    if (status === 401) {
      // Token scaduto o non valido → invalida la sessione locale
      const { logout } = useAuthStore.getState();
      logout();

      // Redirect grezzo (fuori da React Router) per evitare dipendenze circolari
      // Guard: non fare redirect se siamo già su /login (evita loop)
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }

    if (status === 500) {
      // Errore interno Laravel — mostra toast generico
      // Importa dinamicamente react-hot-toast per non creare dipendenza circolare
      import('react-hot-toast').then(({ default: toast }) => {
        toast.error('Errore del server. Riprova o contatta il supporto.');
      });
    }

    // Per 422 (validazione) i singoli hook gestiscono error.response.data.errors
    return Promise.reject(error);
  }
);

export default client;
```


***

## 4. `auth.js` — Implementazione Reale

Il tuo `auth.js` attuale usa mock (`fake-token`, timeout simulati). Ecco la versione reale:[^1_3]

**File:** `C:\xampp\htdocs\windeploy\frontend\src\api\auth.js`

```js
import client from './client';

/**
 * Login utente via Sanctum Bearer Token.
 * POST /api/auth/login
 * Response attesa: { token, token_expires_at, user: { id, name, email, role } }
 */
export async function loginApi(credentials) {
  const response = await client.post('/auth/login', credentials);
  return response.data;
}

/**
 * Logout — invalida il token Sanctum lato server.
 * POST /api/auth/logout
 * Richiede Authorization: Bearer header (aggiunto dall'interceptor).
 */
export async function logoutApi() {
  const response = await client.post('/auth/logout');
  return response.data;
}

/**
 * Recupera il profilo dell'utente autenticato.
 * GET /api/auth/me
 * Usato all'avvio app per verificare che il token in localStorage sia ancora valido.
 */
export async function meApi() {
  const response = await client.get('/auth/me');
  return response.data;
}

export default { loginApi, logoutApi, meApi };
```


***

## 5. `cors.php` — Backend Verificato

**File:** `C:\xampp\htdocs\windeploy\backend\config\cors.php`

Il tuo `cors.php` attuale è già corretto. Verifico e completo:

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths abilitati al CORS
    |--------------------------------------------------------------------------
    | 'api/*'              → tutte le route REST di WinDeploy
    | 'sanctum/csrf-cookie' → necessario per il flusso SPA Sanctum
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Metodi HTTP consentiti
    |--------------------------------------------------------------------------
    | '*' consente GET, POST, PUT, PATCH, DELETE, OPTIONS
    | OPTIONS è critico — è il preflight CORS del browser
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Origini consentite
    |--------------------------------------------------------------------------
    | SVILUPPO LOCALE (XAMPP):
    | - http://localhost:5173     → Vite dev server
    | - http://windeploy.local    → Se si apre il frontend via Apache VHost
    |
    | ATTENZIONE: NON aggiungere http://windeploy.local.api qui.
    | Le chiamate partono sempre da localhost:5173 (Vite), non dall'API stessa.
    |--------------------------------------------------------------------------
    */
    'allowed_origins' => [
        'http://localhost:5173',
        'http://windeploy.local',
    ],

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Header consentiti
    |--------------------------------------------------------------------------
    | '*' consente Authorization, Content-Type, Accept, X-Requested-With, ecc.
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Header esposti al browser
    |--------------------------------------------------------------------------
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache preflight CORS (secondi)
    |--------------------------------------------------------------------------
    | 0 = nessuna cache (utile in sviluppo per vedere subito i cambiamenti)
    | In produzione imposta a 3600 per ridurre le richieste OPTIONS
    |--------------------------------------------------------------------------
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Credenziali (cookie, Authorization header)
    |--------------------------------------------------------------------------
    | true → obbligatorio per Sanctum + withCredentials: true in Axios
    | ATTENZIONE: quando supports_credentials è true, allowed_origins
    | NON può contenere '*' — deve essere una lista esplicita (già così sopra)
    |--------------------------------------------------------------------------
    */
    'supports_credentials' => true,

];
```

**File:** `C:\xampp\htdocs\windeploy\backend\.env` — righe Sanctum da verificare:

```ini
# Dominio del frontend che può fare richieste stateful Sanctum
# Separa con virgola, SENZA spazi
SANCTUM_STATEFUL_DOMAINS=localhost:5173,windeploy.local

# Il domain della sessione cookie
SESSION_DOMAIN=windeploy.local
```

> ⚠️ **Sicurezza**: `SESSION_DOMAIN=windeploy.local` fa sì che il cookie di sessione venga inviato SOLO a `windeploy.local` e subdomini. Questo è corretto. Non usare `.local` (con punto iniziale) in sviluppo — causerebbe problemi con i browser moderni.

***

## 6. Endpoint di Test `/api/ping`

**File:** `C:\xampp\htdocs\windeploy\backend\routes\api.php` — aggiungi in cima, prima dei gruppi auth:

```php
<?php

use Illuminate\Support\Facades\Route;

// ── PING PUBBLICO (no auth) ───────────────────────────────────────────────
// Usato per verificare che il backend risponda e che il proxy Vite funzioni.
// Da rimuovere o proteggere prima del deploy in produzione.
Route::get('/ping', function () {
    return response()->json([
        'status'  => 'ok',
        'time'    => now()->toIso8601String(),
        'app'     => config('app.name'),
        'env'     => config('app.env'),
    ]);
});

// ... resto delle route (auth, agent, ecc.)
```

**Sequenza di test:**

```bash
# a) Verifica che Apache/Laravel risponda direttamente
curl http://windeploy.local.api/api/ping
# Atteso: {"status":"ok","time":"2026-03-04T...","app":"WinDeploy","env":"local"}

# b) Verifica proxy Vite da PowerShell (Vite deve essere in esecuzione)
# Apri il browser su localhost:5173, poi nella DevTools Console:
fetch('/api/ping').then(r => r.json()).then(console.log)
# Atteso: {status: 'ok', time: '...', app: 'WinDeploy', env: 'local'}

# c) In PowerShell (alternativa a curl)
Invoke-RestMethod -Uri http://windeploy.local.api/api/ping
```

Nel **Network tab** di DevTools:

- La richiesta deve mostrare URL: `localhost:5173/api/ping`
- Response Headers devono **non** contenere `Access-Control-Allow-Origin` (perché il proxy Vite ha già risolto il CORS — la richiesta arriva al backend come se fosse same-origin)
- Se vedi la richiesta andare direttamente a `windeploy.local.api`, significa che `baseURL` di Axios è assoluto — errore nel `client.js`

***

## 7. `.env.local` Frontend

**File:** `C:\xampp\htdocs\windeploy\frontend\.env.local`

```ini
# Variabili Vite per sviluppo locale
# ATTENZIONE: questo file NON va in git (già in .gitignore con questo setup)

# Base URL delle API — relativo, intercettato dal proxy Vite
# In produzione su Nginx il proxy è lato server, quindi il valore rimane lo stesso
VITE_API_BASE_URL=/api

# Nome dell'applicazione (usato nei title, breadcrumb, ecc.)
VITE_APP_NAME=WinDeploy

# Ambiente corrente
VITE_ENV=local

# URL del frontend (utile per link condivisi, QR code wizard, ecc.)
VITE_APP_URL=http://localhost:5173
```

**Come usarle in React** — solo le variabili con prefisso `VITE_` sono esposte al browser:

```jsx
// In qualsiasi componente o file JS/JSX:
const appName = import.meta.env.VITE_APP_NAME   // "WinDeploy"
const apiUrl  = import.meta.env.VITE_API_BASE_URL // "/api"
const isLocal = import.meta.env.VITE_ENV === 'local'

// Esempio pratico nel client.js (opzionale, già gestito dal proxy):
// baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api'
```

> ⚠️ Non mettere mai segreti (JWT secret, password) nelle variabili `VITE_*` — sono incorporate nel bundle JS e visibili chiunque faccia `view-source`.

***

## 8. Checklist Verifica Finale

```
PRE-REQUISITI (una-tantum)
[ ] hosts Windows ha:  127.0.0.1  windeploy.local
                       127.0.0.1  windeploy.local.api
[ ] httpd-vhosts.conf ha 2 VHost: windeploy.local → frontend/
                                   windeploy.local.api → backend/public/
[ ] backend/public/.htaccess esiste e mod_rewrite è attivo (AllowOverride All)
[ ] php artisan key:generate eseguito (APP_KEY nel .env)
[ ] php artisan migrate eseguito (tabelle DB esistono)

AVVIO SESSIONE DI SVILUPPO
[ ] XAMPP Control Panel → Apache: verde | MySQL: verde
[ ] Apri http://windeploy.local.api/api/ping nel browser
    → deve rispondere JSON, non pagina Apache di default
[ ] cd frontend && npm run dev
    → Vite avviato su http://localhost:5173 (port 5173)

TEST PROXY E CORS
[ ] DevTools Console su localhost:5173:
    fetch('/api/ping').then(r=>r.json()).then(console.log)
    → risposta JSON (non errore CORS, non 404)
[ ] Network tab: richiesta mostra "localhost:5173/api/ping"
    NON "windeploy.local.api/api/ping"
[ ] Network tab: nessun header "Access-Control-Allow-Origin"
    nella risposta del ping (proxy = same-origin, CORS non serve)

TEST AUTH
[ ] Login da React con credenziali admin → risponde token
[ ] localStorage ha chiave "windeploy_token" con valore non null
[ ] Network tab → request a /api/auth/login ha header:
    Accept: application/json
    Content-Type: application/json
[ ] Dopo login → chiamata a /api/auth/me ha header:
    Authorization: Bearer <token>
[ ] Token allegato a TUTTE le chiamate successive (verifica /api/ping
    da componente autenticato — deve avere Authorization header)

TEST EDGE CASE
[ ] Logout → localStorage svuotato, redirect a /login
[ ] Token manomesso manualmente in localStorage → prossima call
    ritorna 401 → logout automatico (interceptor response)
[ ] Ricarica pagina con token valido → authStore riletto da
    localStorage → utente rimane loggato
[ ] Network tab → nessun errore CORS in nessuna richiesta
```


***

## 9. Comandi Git

```bash
# Verifica che .env.local sia in .gitignore (deve esserci per Vite)
grep -n ".env.local" frontend/.gitignore

# Se manca, aggiungilo:
echo ".env.local" >> frontend/.gitignore

# Verifica anche il .gitignore radice del monorepo:
grep -n ".env.local" .gitignore

# Commit convenzionale (Conventional Commits)
git add frontend/vite.config.js \
        frontend/src/api/client.js \
        frontend/src/api/auth.js \
        frontend/.env.local \        # Solo se vuoi trackare il template (senza valori segreti)
        backend/config/cors.php \
        backend/routes/api.php \
        frontend/.gitignore

git commit -m "fix(frontend): configure Vite proxy and Axios client for Laravel backend

- Set proxy /api → http://windeploy.local.api (Apache VHost)
- Add proxy /ws → ws://windeploy.local.api (WebSocket Reverb)
- Fix Axios baseURL to relative '/api' to ensure proxy intercepts requests
- Add request interceptor: Authorization Bearer token from authStore
- Add response interceptor: 401 → auto-logout, 500 → generic toast
- Increase Axios timeout to 30000ms
- Replace mock auth.js with real HTTP calls to /api/auth/*
- Add /api/ping test route (public, no auth) for proxy verification
- Verify cors.php: allowed_origins includes localhost:5173
- Add .env.local template with VITE_* variables
- Ensure .env.local in .gitignore

Refs: windeploy.local.api VHost must be active in XAMPP httpd-vhosts.conf"
```

> **Nota**: se `.env.local` contiene solo template senza valori reali sensibili puoi committarlo come documentazione. Se contiene valori reali (URL interni, chiavi), mettilo in `.gitignore` e crea un `.env.local.example` da committare al suo posto.
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0006-viteconfigjs.md

[^1_2]: 0003-setupiniziale.md

[^1_3]: 0013-SetupprogettoReact.md

[^1_4]: 0102-generazione bulk di codice CRUD.md

[^1_5]: 0103-configurazione auth e sicurezza.md

[^1_6]: 0101-auth e sicurezza.md

[^1_7]: 0100-File temporanei tmp_.php esposti nella root.md

[^1_8]: 0021-Installer-configurazione sistema.md

[^1_9]: 0020-Scansione app installate.md

[^1_10]: 0019-Struttura agent.md

[^1_11]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_12]: 0017-motorpage.md

[^1_13]: 0016-wizards.md

[^1_14]: 0015-dashboard.md

[^1_15]: 0014-Login.md

[^1_16]: 0012-apiendpointwindows.md

[^1_17]: 0011-controllercrud.md

[^1_18]: 0010-autenticazione.md

[^1_19]: 0009-scaffoldprogetto.md

[^1_20]: 0008-db.md

[^1_21]: 0007-cheatsheet.md

[^1_22]: 0002-ricerca-iniziale.md

