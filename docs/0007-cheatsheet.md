<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# \# ════════════════════════════════════════════════════════════════════

# CHEATSHEET — Comandi quotidiani sviluppo locale WinDeploy

# ════════════════════════════════════════════════════════════════════

# ── AVVIO SESSIONE DI SVILUPPO ──

# 1. Apri XAMPP Control Panel → clicca Start su Apache e MySQL

# 2. Apri due terminali (PowerShell o CMD):

# TERMINALE 1 — Backend Laravel

cd C:\xampp\htdocs\windeploy\backend
php artisan serve              \# server su [http://127.0.0.1:8000](http://127.0.0.1:8000)

# Oppure Apache già serve → windeploy.local

# TERMINALE 2 — Frontend React

cd C:\Users\{utente}\windeploy-react
npm run dev                    \# Vite su http://localhost:5173

# ── COMANDI LARAVEL UTILI IN SVILUPPO ──

php artisan migrate                    \# esegui nuove migration
php artisan migrate:fresh --seed       \# resetta DB e popola con dati fake
php artisan make:model NomeModello -m  \# crea modello + migration
php artisan make:controller NomeCtrl --api  \# crea controller API
php artisan make:request NomeRequest   \# crea Form Request
php artisan route:list                 \# lista tutte le route
php artisan tinker                     \# REPL interattivo per testare
php artisan cache:clear                \# svuota cache
php artisan config:clear               \# svuota cache config

# ── AGGIUNGERE PACKAGE LARAVEL ──

composer require nome/pacchetto

# ── COMANDI REACT UTILI ──

npm run dev          \# avvia Vite dev server
npm run build        \# build produzione (output in /dist)
npm install          \# installa dipendenze da package.json
npm install nome-pkg \# aggiunge un package

# ── TEST RAPIDO API (senza browser) ──

# Con PowerShell:

Invoke-WebRequest -Uri "http://windeploy.local/api/auth/login" -Method POST -ContentType "application/json" -Body '{"email":"admin@windeploy.local","password":"password"}'

# ── RESET COMPLETO DATABASE IN LOCALE ──

php artisan migrate:fresh --seed

# Attenzione: cancella tutti i dati e ricrea le tabelle da zero

Eccoti il cheatsheet ufficiale per lo sviluppo quotidiano di WinDeploy, aggiornato con lo stack reale del progetto (Laravel 12, React 19, Vite 6, Tailwind v4).[^1_1][^1_2][^1_3]

***

# 🚀 WinDeploy — Cheatsheet Sviluppo Locale

## Avvio sessione

```
1. XAMPP Control Panel → Start Apache + MySQL
2. Apri DUE terminali separati
```

**Terminale 1 — Backend Laravel**

```bash
cd C:\xampp\htdocs\windeploy\backend
php artisan serve
# oppure: Apache già serve su http://windeploy.local (se virtual host attivo)
```

**Terminale 2 — Frontend React (Vite)**

```bash
cd C:\xampp\htdocs\windeploy\frontend
npm run dev
# Vite disponibile su http://localhost:5173
# Proxy /api → http://windeploy.local (configurato in vite.config.js)
```


***

## Struttura cartelle locale

```
C:\xampp\htdocs\windeploy\
├── backend\          → Laravel 12 (php artisan serve / Apache)
│   ├── public\       → document root esposto
│   └── .env          → DB_DATABASE=windeploy_db, APP_URL=http://windeploy.local
└── frontend\         → React 19 + Vite 6 + Tailwind v4
    └── vite.config.js → proxy /api → windeploy.local
```


***

## Comandi Laravel (Artisan)

```bash
# === MIGRAZIONI ===
php artisan migrate                       # esegui nuove migration pending
php artisan migrate:fresh --seed          # ⚠️ RESET COMPLETO: cancella tutto e re-seed
php artisan migrate:status                # stato di ogni migration

# === GENERATORI ===
php artisan make:model NomeModello -m     # modello + migration
php artisan make:controller NomeCtrl --api    # controller resource API
php artisan make:request NomeRequest      # Form Request (validation)
php artisan make:seeder NomeSeeder        # crea seeder
php artisan make:policy NomePolicy --model=NomeModello  # policy autorizzazione

# === ROUTE E DEBUG ===
php artisan route:list                    # elenca tutte le route
php artisan route:list --path=api         # filtra solo route /api
php artisan tinker                        # REPL interattivo per testare modelli/query
php artisan about                         # versione, driver, environment

# === CACHE ===
php artisan cache:clear                   # svuota cache applicazione
php artisan config:clear                  # svuota cache config (.env)
php artisan route:clear                   # svuota cache route
php artisan view:clear                    # svuota cache blade
php artisan optimize:clear               # tutto in uno ✅

# === SANCTUM (auth SPA/agent) ===
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

> ⚠️ **Sicurezza:** `migrate:fresh --seed` distrugge tutti i dati. Usalo solo in locale. Mai su server produzione.[^1_1]

***

## Comandi Composer

```bash
composer require nome/pacchetto           # aggiungi dipendenza
composer require nome/pacchetto --dev     # solo dev (es. debugbar)
composer install                          # installa da composer.lock
composer update                           # aggiorna alle versioni permesse
composer dump-autoload                    # rigenera autoload (dopo nuovi file)
```

**Package raccomandati per WinDeploy:**

```bash
composer require laravel/sanctum          # auth API per React + agent (v4.3.1)
composer require spatie/laravel-permission  # gestione ruoli admin/tecnico/viewer
composer require barryvdh/laravel-debugbar --dev  # debug in sviluppo
```


***

## Comandi React / npm

```bash
npm run dev          # avvia Vite dev server (http://localhost:5173)
npm run build        # build produzione → dist/ (deploy su server)
npm run preview      # preview della build produzione in locale
npm install          # installa tutte le dipendenze da package.json
npm install nome-pkg # aggiunge un package

# Package raccomandati WinDeploy
npm install react-router-dom@7            # routing SPA
npm install axios                         # chiamate API REST
npm install @tailwindcss/vite            # Tailwind v4 plugin Vite
```


***

## Test API rapido (PowerShell)

```powershell
# Login
Invoke-WebRequest -Uri "http://windeploy.local/api/auth/login" `
  -Method POST `
  -ContentType "application/json" `
  -Body '{"email":"admin@windeploy.local","password":"password"}'

# Con token (sostituisci <TOKEN>)
$headers = @{ Authorization = "Bearer <TOKEN>" }
Invoke-WebRequest -Uri "http://windeploy.local/api/wizards" `
  -Method GET -Headers $headers

# Alternativa più leggibile: usa curl (disponibile in PowerShell 7+)
curl -X POST http://windeploy.local/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{"email":"admin@windeploy.local","password":"password"}'
```


***

## Reset DB completo

```bash
php artisan migrate:fresh --seed
# Ricrea le tabelle: users, templates, wizards, software_library,
# wizard_software, execution_logs, reports
# Poi riesegui il seeder con utenti admin/tecnico/viewer di test
```


***

## `vite.config.js` — Proxy verso Laravel

```js
// frontend/vite.config.js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),  // Tailwind v4: plugin Vite, no tailwind.config.js
  ],
  server: {
    proxy: {
      '/api': {
        target: 'http://windeploy.local',  // Apache su windeploy.local
        changeOrigin: true,
        secure: false,
      }
    }
  }
})
```

> ⚠️ **Tailwind v4** non usa più `tailwind.config.js` né `content: []` — la detection delle classi è automatica.[^1_2]

***

## `.env` Laravel — Configurazione locale

```ini
APP_NAME=WinDeploy
APP_ENV=local
APP_KEY=base64:...  # generato con php artisan key:generate
APP_DEBUG=true
APP_URL=http://windeploy.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=windeploy_db
DB_USERNAME=windeploy_user
DB_PASSWORD=wdeploy123!

SANCTUM_STATEFUL_DOMAINS=localhost:5173,windeploy.local
SESSION_DOMAIN=windeploy.local
```


***

## Hosts Windows (una-tantum)

```
# C:\Windows\System32\drivers\etc\hosts (come Amministratore)
127.0.0.1  windeploy.local
127.0.0.1  windeploy.local.api
```


***

## Checklist avvio rapido

- [ ] XAMPP → Apache ✅ MySQL ✅
- [ ] `php artisan serve` o virtual host attivo
- [ ] `npm run dev` sul frontend
- [ ] `.env` punta a `windeploy_db`
- [ ] `php artisan migrate` (se ci sono migration nuove)
- [ ] Test login API con PowerShell o browser su `http://localhost:5173`

<div align="center">⁂</div>

[^1_1]: 0003-setupiniziale.md

[^1_2]: 0002-ricerca-iniziale.md

[^1_3]: 0001-projectdescription.txt

