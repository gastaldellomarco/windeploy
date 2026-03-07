# WinDeploy — Note per AI

> Questo file contiene istruzioni specifiche per le AI che lavorano sul progetto.
> Aggiornare dopo ogni sessione in cui si scopre un pattern ricorrente o si corregge un errore.

---

## Note per Claude (backend / security)

### Guard e autenticazione
- Il guard JWT si chiama **`api`**, NON `jwt` — non cambiarlo mai. Il middleware corretto è `auth:api`.
- Sanctum è usato **solo** per la SPA web (`auth:sanctum`). Mai mixare i due guard sulle stesse route.
- Il token JWT per l'agent è **monouso**: viene invalidato settando `wizards.used_at = now()` al primo utilizzo.
- L'IP reale del visitatore arriva nell'header **`CF-Connecting-IP`**, NON in `REMOTE_ADDR` (Cloudflare Tunnel filtra tutto). Usare sempre `request()->header('CF-Connecting-IP') ?? request()->ip()`.

### EncryptionService
- Formato del ciphertext: `base64(iv).base64(tag).base64(cipher)` — separatore punto `.`
- Algoritmo: `aes-256-gcm` via `openssl_encrypt()`
- La chiave si ottiene così: `base64_decode(substr(config('app.key'), 7))` (toglie prefisso `base64:`)
- Non usare mai `Crypt::encrypt()` di Laravel per le password wizard (CBC non autentica)

### Pattern architetturali
- **Mai** aggiungere logica business nei Controller — delegare sempre ai Service
- I Controller devono: validare (via FormRequest) → chiamare Service → restituire Resource
- Tutti i JSON di risposta API usano snake_case (non camelCase)
- I campi `password` nel campo `configurazione` del wizard sono **sempre cifrati** prima del salvataggio — mai salvare in chiaro

### Convenzioni naming
- Modelli: `PascalCase` (es. `WizardExecution`)
- Metodi: `camelCase` (es. `generateCode()`)
- JSON API: `snake_case` (es. `wizard_code`, `used_at`)
- Commit: `<type>(<scope>): <description>` — types: feat/fix/refactor/docs/test/chore/security/perf

### Dipendenze installate
- `tymon/jwt-auth` — autenticazione agent
- `spatie/laravel-permission` — gestione ruoli (admin/tecnico/viewer)
- PHP 8.3, Laravel 11

---

## Note per GPT (frontend React)

### Axios e routing
- `baseURL` axios è **`/api`** relativo — il proxy Vite gestisce il routing verso il backend
- Non hardcodare mai URL assoluti nel codice — usare `import.meta.env.VITE_API_BASE_URL`
- In locale Vite usa proxy: `/api` → `http://windeploy.local.api`
- In produzione la build statica è servita dallo stesso dominio del backend (no CORS)

### State management
- **Non usare `localStorage`** per token — usare **Zustand** (già configurato in `src/store/authStore.js`)
- Il token Sanctum è gestito via **cookie HttpOnly** — non è accessibile in JS, non cercarlo
- Per i token JWT agent: non gestiti nel frontend (sono nell'agent Python)

### Convenzioni
- Componenti: `PascalCase` (es. `WizardBuilder.jsx`)
- Hook: `camelCase` con prefisso `use` (es. `useWizardAutosave`)
- Non usare `class components` — solo functional components con hook
- React 18, Vite 5

### Badge di stato wizard — mappa colori Tailwind

| Stato         | Classe Tailwind                      |
|---------------|--------------------------------------|
| `bozza`       | `bg-gray-100 text-gray-600`          |
| `attivo`      | `bg-blue-100 text-blue-700`          |
| `in_esecuzione` | `bg-yellow-100 text-yellow-700`    |
| `completato`  | `bg-green-100 text-green-700`        |
| `fallito`     | `bg-red-100 text-red-700`            |
| `scaduto`     | `bg-orange-100 text-orange-600`      |

### Pattern query TanStack Query
- Query key convention: `['wizards', wizardId]` per singolo, `['wizards']` per lista
- Sempre usare `staleTime: 30_000` per dati wizard (non cambiano ogni secondo)
- Il polling monitor usa `refetchInterval: 10_000` (10s) — non scendere sotto

---

## Note per Gemini (agent Python)

### Configurazione e ambiente
- `config.py` è l'**unico file da modificare** per cambiare ENV (`local` o `production`)
- `ssl_verify=False` **SOLO** quando `ENV='local'` — mai in produzione
- L'URL base API viene letto da `config.py`: `API_BASE_URL`
- Non hardcodare URL nel codice — sempre usare costanti da `config.py`

### Thread safety GUI (CustomTkinter)
- **TUTTI** gli aggiornamenti UI dal thread worker devono usare `self.after(0, callback)` o `self.after(delay_ms, callback)`
- Non chiamare mai metodi widget direttamente da thread non-main — causa crash silenzioso su Windows
- Pattern corretto: `self.after(0, lambda: self.progress_label.configure(text=msg))`

### Struttura schermate
- Navigazione tra schermate via `app.show_screen(name)` — mai istanziare schermate direttamente
- Ogni schermata eredita da `BaseScreen` e implementa `on_enter()` e `on_leave()`
- Flusso: `screen_connect` → `screen_overview` → `screen_uninstall` → `screen_progress` → `screen_complete`

### Chiamate API
- Usa sempre `api_client.send_step(step_name, status, message, progress)` per ogni operazione critica
- Il JWT token è passato nell'header `Authorization: Bearer {token}` (già gestito da `ApiClient`)
- Timeout default per chiamate API: 30s (operazioni normale), 600s (installazione software)

### PyInstaller
- Il file spec è `agent/windeploy.spec` — non usare `--onefile` da CLI, usa sempre lo spec
- `console=False` nel spec — la console CMD non deve apparire in produzione
- Includere sempre: `customtkinter` data files, `certifi` data files, `assets/icon.ico`

### Convenzioni naming
- Tutto `snake_case` (variabili, funzioni, file, classi anche)
- Eccezione: classi CustomTkinter-derived usano `PascalCase` per compatibilità con il framework

---

## Errori già corretti (non rifare)

> Questa sezione previene che le AI ripropongano soluzioni già sbagliate.

| # | Errore | Soluzione adottata | Modulo |
|---|--------|--------------------|---------|
| 1 | Usare `auth:jwt` come middleware — non esiste in questo progetto | Usare `auth:api` (guard JWT si chiama `api`) | Backend |
| 2 | Chiamare `request()->ip()` per audit log — restituisce IP Cloudflare | Usare `request()->header('CF-Connecting-IP')` | Backend |
| 3 | Aggiornare widget CTk direttamente da thread worker | Wrappare tutto in `self.after(0, lambda: ...)` | Agent |
| 4 | Usare `localStorage` per token Sanctum | I cookie sono HttpOnly — non accessibili in JS, gestiti automaticamente da browser | Frontend |
| 5 | Hardcodare `http://windeploy.local/api` in axios | Usare `baseURL: '/api'` + proxy Vite | Frontend |
| 6 | Usare `Crypt::encrypt()` per password wizard | Usare `EncryptionService` con AES-256-GCM | Backend |
| 7 | Definire guard JWT come `jwt` in `config/auth.php` | Il guard deve chiamarsi `api` con driver `jwt` | Backend |

<!-- NUOVI ERRORI CORRETTI: aggiungere sotto questa linea -->
