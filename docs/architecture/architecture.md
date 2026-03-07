# WinDeploy — Architectural Overview

> **Stack:** Laravel 11 · React 18 + Vite · Python 3.11 Agent · MySQL 8  
> **Deploy:** Ubuntu 24 LTS + Nginx + Cloudflare Tunnel  
> **Last updated:** 2026-03-07

---

## SECTION 1 — System Overview

WinDeploy è composto da **tre moduli indipendenti** che comunicano esclusivamente tramite API REST JSON.

```
┌─────────────────────────────────────────────────────────────────────┐
│                          WINDEPLOY SYSTEM                           │
│                                                                     │
│  ┌──────────────────┐     REST/JSON     ┌──────────────────────┐   │
│  │  Frontend React  │ ◄────────────────► │   Backend Laravel    │   │
│  │  (SPA browser)   │   Bearer token     │   (API headless)     │   │
│  └──────────────────┘                   └──────────┬───────────┘   │
│                                                     │               │
│                                              REST/JSON              │
│                                           JWT monouso               │
│                                                     │               │
│                                         ┌───────────▼───────────┐  │
│                                         │   Agent Python .exe   │  │
│                                         │  (Windows client)     │  │
│                                         └───────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.1 Backend (Laravel 11 API)

| Aspetto | Dettaglio |
|---|---|
| **Responsabilità** | Gestione wizard, autenticazione duale, cifratura password, generazione codici univoci, logging esecuzioni, produzione report |
| **Confini** | Espone esclusivamente endpoint REST JSON; non ha view Blade in produzione |
| **Non fa** | Rendering HTML per utenti web; nessuna logica di UI; non esegue direttamente operazioni su Windows |
| **Tecnologia auth** | Sanctum (SPA React) + JWT `tymondesigns/jwt-auth` (Agent) |
| **Database** | MySQL 8 tramite Eloquent ORM |

### 1.2 Frontend (React 18 SPA)

| Aspetto | Dettaglio |
|---|---|
| **Responsabilità** | Wizard 8 step, dashboard tecnico, monitor real-time, gestione utenti, visualizzazione report |
| **Confini** | Parla solo con il backend tramite `src/api/`; stato globale in Zustand |
| **Non fa** | Accesso diretto al database; operazioni filesystem/OS; logica di business (validazioni lato client sono accessorie, non canoniche) |
| **Build** | Vite con output in `public/dist/` servito da Nginx |

### 1.3 Agent (Python 3.11 exe)

| Aspetto | Dettaglio |
|---|---|
| **Responsabilità** | Lettura registro Windows, rimozione app tramite winget/PowerShell, configurazione OS, invio step al backend, generazione report HTML |
| **Confini** | Esegue solo sul PC Windows target; riceve configurazione dal backend via JWT; non modifica dati nel DB direttamente |
| **Non fa** | Rendering web; gestione utenti; logica wizard (la riceve già pronta dal backend) |
| **Distribuzione** | Binario `.exe` compilato con PyInstaller, GUI CustomTkinter |

---

## SECTION 2 — Backend Architecture (Laravel 11)

### 2.1 Layer Stack

```
┌──────────────────────────────────────────────────────────┐
│                    HTTP LAYER                            │
│  routes/api.php  →  FormRequest (validazione input)      │
│  Route::middleware(['auth:sanctum']) o ['auth:api'])      │
├──────────────────────────────────────────────────────────┤
│                    CONTROLLER LAYER                      │
│  Orchestrazione pura — nessuna logica business           │
│  WizardController, AgentController, UserController       │
├──────────────────────────────────────────────────────────┤
│                    SERVICE LAYER                         │
│  Logica business testabile in isolamento                 │
│  WizardCodeService    → genera codici WD-XXXX univoci    │
│  EncryptionService    → AES-256-GCM per password admin   │
│  WizardMonitorService → calcolo progresso da JSON log    │
│  AgentAuthService     → validazione wizard + emit JWT    │
├──────────────────────────────────────────────────────────┤
│                    REPOSITORY LAYER                      │
│  Astrazione opzionale; Eloquent usato direttamente       │
│  WizardRepository (se query complesse lo richiedono)     │
├──────────────────────────────────────────────────────────┤
│                    MODEL LAYER                           │
│  Eloquent + cast + relazioni + $fillable                 │
│  Wizard, ExecutionLog, WizardStep, User, Role            │
├──────────────────────────────────────────────────────────┤
│                    EVENT / JOB LAYER                     │
│  Operazioni asincrone via Laravel Queue                  │
│  WizardStepReceived   → Event lanciato su POST step      │
│  CleanExpiredWizards  → Job schedulato (daily)           │
└──────────────────────────────────────────────────────────┘
```

### 2.2 Struttura Directory Backend

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── WizardController.php
│   │   ├── AgentController.php
│   │   ├── UserController.php
│   │   └── ReportController.php
│   ├── Requests/
│   │   ├── StoreWizardRequest.php
│   │   ├── AgentAuthRequest.php
│   │   └── AgentStepRequest.php
│   └── Middleware/
│       └── CheckAgentJwt.php
├── Services/
│   ├── WizardCodeService.php
│   ├── EncryptionService.php
│   ├── WizardMonitorService.php
│   └── AgentAuthService.php
├── Models/
│   ├── Wizard.php
│   ├── ExecutionLog.php
│   └── User.php
├── Events/
│   └── WizardStepReceived.php
└── Jobs/
    └── CleanExpiredWizards.php
```

### 2.3 Autenticazione Duale

```
  GUARD: web (session)         → Non usato in produzione API
  GUARD: sanctum               → React SPA (cookie + token Bearer)
  GUARD: api (JWT)             → Agent .exe (token monouso)

  ┌─────────────┐   POST /api/auth/login    ┌──────────────────┐
  │ React SPA   │ ─────────────────────────► │ AuthController   │
  │             │ ◄──── {token: "..."}  ──── │ Sanctum::guard() │
  │ + Bearer    │                            └──────────────────┘
  └─────────────┘

  ┌─────────────┐   POST /api/agent/auth    ┌──────────────────┐
  │ Agent .exe  │ ─────────────────────────► │ AgentController  │
  │ (codice WD) │ ◄── {jwt, config_json} ─── │ JWT::issue()     │
  │ + Bearer    │       (single-use)         └──────────────────┘
  └─────────────┘
```

**JWT Agent — caratteristiche token monouso:**
- `exp`: 4 ore dall'emissione
- Payload custom: `wizard_id`, `agent_version`, `issued_at`
- Invalidato dopo `POST /api/agent/complete` (revoca in DB)
- Guard configurato in `config/auth.php` → `guards.api.driver = 'jwt'`

### 2.4 Ruoli e Permessi (spatie/laravel-permission)

| Ruolo | Permessi principali |
|---|---|
| `admin` | CRUD wizard, CRUD utenti, accesso tutti i report, configurazione sistema |
| `tecnico` | Crea/vedi wizard propri, scarica agent, vede monitor |
| `viewer` | Sola lettura: lista wizard, report (no dati sensibili) |

---

## SECTION 3 — Frontend Architecture (React 18)

### 3.1 Layer Stack

```
┌──────────────────────────────────────────────────────────┐
│                     PAGES LAYER                          │
│  Una page = una route; composte da componenti            │
│  WizardCreatePage, WizardMonitorPage, DashboardPage      │
├──────────────────────────────────────────────────────────┤
│                   COMPONENTS LAYER                       │
│  Riusabili, nessuna chiamata API diretta                 │
│  WizardStepper, StepCard, ProgressBar, ReportViewer      │
├──────────────────────────────────────────────────────────┤
│                     HOOKS LAYER                          │
│  Logica stateful, testabile in isolamento                │
│  useWizard    → useReducer per stato 8 step              │
│  useMonitor   → polling ogni 5s, stop su completamento   │
│  useAuth      → stato autenticazione + refresh token     │
│  useToast     → notifiche globali                        │
├──────────────────────────────────────────────────────────┤
│                     STORE LAYER                          │
│  Zustand — stato globale cross-route                     │
│  authStore    → user, token, ruolo                       │
│  wizardStore  → wizard attivo, codice univoco            │
├──────────────────────────────────────────────────────────┤
│                    API LAYER (Axios)                     │
│  src/api/ — istanza Axios centralizzata                  │
│  interceptor request  → inietta Bearer token             │
│  interceptor response → gestisce 401/403/422             │
│  api/wizards.js, api/auth.js, api/agent.js               │
├──────────────────────────────────────────────────────────┤
│                    ROUTER LAYER                          │
│  React Router v6 — PrivateRoute con check ruolo          │
│  /login, /dashboard, /wizards/*, /monitor/:id, /admin/*  │
└──────────────────────────────────────────────────────────┘
```

### 3.2 Struttura Directory Frontend

```
src/
├── api/
│   ├── axios.js          # istanza base + interceptor
│   ├── auth.js
│   ├── wizards.js
│   └── agent.js
├── components/
│   ├── wizard/
│   │   ├── WizardStepper.jsx
│   │   └── StepCard.jsx
│   ├── monitor/
│   │   └── ProgressBar.jsx
│   └── ui/               # componenti atomic (Button, Modal, ecc.)
├── hooks/
│   ├── useWizard.js
│   ├── useMonitor.js
│   ├── useAuth.js
│   └── useToast.js
├── pages/
│   ├── LoginPage.jsx
│   ├── DashboardPage.jsx
│   ├── WizardCreatePage.jsx
│   ├── WizardMonitorPage.jsx
│   └── AdminPage.jsx
├── store/
│   ├── authStore.js
│   └── wizardStore.js
└── router/
    ├── index.jsx
    └── PrivateRoute.jsx
```

---

## SECTION 4 — Agent Architecture (Python 3.11)

### 4.1 Layer Stack

```
┌──────────────────────────────────────────────────────────┐
│                     GUI LAYER                            │
│  CustomTkinter — solo presentazione, zero business logic │
│  main_window.py, step_frames.py, progress_frame.py       │
├──────────────────────────────────────────────────────────┤
│                    CONFIG LAYER                          │
│  config.py — unico punto di configurazione               │
│  ENV flag: LOCAL / PROD → cambia base_url API            │
│  Lettura da windeploy.ini se presente                    │
├──────────────────────────────────────────────────────────┤
│                   API CLIENT LAYER                       │
│  api_client.py — tutte le chiamate HTTP centralizzate    │
│  Gestione retry, timeout, header JWT                     │
├──────────────────────────────────────────────────────────┤
│                    SCANNER LAYER                         │
│  scanner.py — lettura registro Windows + winget          │
│  HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall │
│  HKCU stessa path per app utente-specifiche              │
├──────────────────────────────────────────────────────────┤
│                   INSTALLER LAYER                        │
│  installer.py — wrapping PowerShell/winget/msi/exe       │
│  winget uninstall --id, msiexec /x, silent uninstaller   │
├──────────────────────────────────────────────────────────┤
│                  SYSTEM CONFIG LAYER                     │
│  system_config.py — configurazioni OS                    │
│  Rename PC, creazione utenti locali, power plan, RDP     │
├──────────────────────────────────────────────────────────┤
│                   REPORT GEN LAYER                       │
│  report_gen.py — generazione HTML report standalone      │
│  Template Jinja2 embedded, base64 assets, no dipendenze  │
└──────────────────────────────────────────────────────────┘
```

### 4.2 Struttura Directory Agent

```
agent/
├── main.py                  # entry point PyInstaller
├── config.py
├── api_client.py
├── scanner.py
├── installer.py
├── system_config.py
├── report_gen.py
├── gui/
│   ├── main_window.py
│   ├── step_frames.py
│   └── progress_frame.py
├── assets/
│   └── logo.png
└── windeploy.spec           # PyInstaller spec file
```

---

## SECTION 5 — Autenticazione e Autorizzazione

### 5.1 Flusso Web App (Sanctum)

```
  [Browser React]
       │
       │  POST /api/auth/login
       │  {email, password}
       ▼
  [Laravel AuthController]
       │
       ├─ Valida credenziali (Hash::check)
       ├─ $user->createToken('spa') → Sanctum
       │
       ▼
  Response: {token: "1|abc...", user: {...}}
       │
       ▼
  [Axios interceptor]
       │  Authorization: Bearer 1|abc...
       │  su ogni richiesta successiva
       ▼
  [Laravel middleware: auth:sanctum]
       │
       └─ Verifica token in personal_access_tokens table
```

### 5.2 Flusso Agent (JWT monouso)

```
  [Agent .exe]
       │
       │  POST /api/agent/auth
       │  {codice: "WD-XXXX"}
       ▼
  [Laravel AgentController]
       │
       ├─ Trova Wizard per codice_univoco
       ├─ Verifica stato='pronto' E expires_at > now()
       ├─ JWTAuth::fromUser($wizard->tecnico)
       │  payload: {wizard_id, sub, exp: +4h}
       │
       ▼
  Response: {jwt: "eyJ...", config: {...wizard completo...}}
       │
       ▼
  [Agent usa JWT]
       │  Authorization: Bearer eyJ...
       │  su POST /api/agent/start, /step, /complete
       ▼
  [Laravel middleware: auth:api (JWT guard)]
       │
       └─ JWTAuth::parseToken()->authenticate()
```

### 5.3 Matrice Autorizzazione per Endpoint

```
  Route Group            Middleware               Guard
  ─────────────────────────────────────────────────────
  /api/auth/*            (nessuno)                —
  /api/agent/auth        (nessuno)                —
  /api/wizards/*         auth:sanctum + role      sanctum
  /api/users/*           auth:sanctum + admin     sanctum
  /api/agent/*           auth:api                 jwt
  /api/monitor/*         auth:sanctum             sanctum
```

> **Nota Cloudflare Tunnel:** l'IP reale del visitatore arriva in `CF-Connecting-IP`, non in `REMOTE_ADDR`. Rate limiting e log di sicurezza devono leggere `$request->header('CF-Connecting-IP')`.
