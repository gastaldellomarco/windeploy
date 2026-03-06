# 📚 WinDeploy — Documentazione Completa

**Progetto:** Piattaforma di automazione configurazione PC Windows  
**Stack:** Laravel 12 (PHP 8.2) | React 18 + Vite | Python 3.11 Agent | MySQL 8  
**Ambiente:** XAMPP (locale) | Ubuntu 24 + Nginx + Cloudflare Tunnel (produzione)

---

## 📖 Indice Principale

### 🎯 **Inizio Rapido**

- **[0003-setupiniziale.md](0003-setupiniziale.md)** ✅ — Setup XAMPP + PHP 8.3 per Windows
- **[0004-Strutturacartelle.md](0004-Strutturacartelle.md)** ✅ — Struttura cartelle del progetto
- **[0005-filedotenv.md](0005-filedotenv-UPDATED.md)** ⚠️ **AGGIORNATO** — Configurazione `.env` Laravel
- **[0009-scaffoldprogetto.md](0009-scaffoldprogetto.md)** ✅ — Scaffold Laravel + React iniziale

---

### 🔐 **Autenticazione e Sicurezza**

- **[0010-autenticazione.md](0010-autenticazione.md)** ✅ — Flusso auth Sanctum (web) + JWT (agent)
- **[0101-auth e sicurezza.md](0101-auth%20e%20sicurezza.md)** ✅ — Implementazione completa controller + middleware
- **[0108-Zustand authStore Axios.md](0108-Zustand%20authStoreAxios%20Interceptor.md)** ✅ — Store auth frontend + Axios interceptor
- **[0111-Backend token monousoattempt_countrate limiter.md](0111-Backend%20token%20monousoattempt_countrate%20limiter.md)** ✨ **NUOVO** — Hardening `/api/agent/auth`: token monouso, lockout MAC, rate limiter

---

## 🔐 Modello di Sicurezza Agent

> Questo documento descrive il modello di sicurezza adottato per l'autenticazione dell'agent Windows nel contesto MVP di WinDeploy.  
> Per i dettagli implementativi completi vedi [0010-autenticazione.md](0010-autenticazione.md) e [0111-Backend token monouso...](0111-Backend%20token%20monousoattempt_countrate%20limiter.md).

---

### Protezioni attive (MVP)

- ✅ **Codice wizard monouso (`used_at`)** — Il codice `WD-XXXXX` viene invalidato dopo il primo utilizzo riuscito. Un secondo utilizzo dello stesso codice è rifiutato con `409 Conflict`.
- ✅ **Scadenza 24h (`expires_at`)** — Ogni codice wizard ha una finestra di validità di 24 ore dalla creazione. Oltre tale limite, l'autenticazione è rifiutata con `410 Gone`.
- ✅ **Lockout dopo 3 tentativi MAC errati (`attempt_count`)** — Se il MAC address fornito dall'agent non corrisponde a quello registrato nel wizard per 3 volte consecutive, il codice viene bloccato definitivamente (`locked`). L'IP dell'ultimo tentativo è tracciato in `last_attempt_ip`.
- ✅ **Rate limiting su `/api/agent/auth`** — Doppio livello: 10 richieste/minuto per IP (`agent_auth_ip`), 5 richieste/minuto per codice wizard (`agent_auth_code`). Restituisce `429 Too Many Requests` al superamento.
- ✅ **JWT firmato con `APP_KEY`** — Il token restituito dopo l'auth è un JWT HS256 firmato con la chiave applicativa Laravel. Senza accesso al server, il token non è falsificabile né modificabile.
- ✅ **HTTPS obbligatorio** — Tutta la comunicazione agent ↔ backend avviene su TLS. In produzione Nginx rifiuta le connessioni HTTP in chiaro sull'endpoint `/api/agent/*`.

---

### Limiti noti e rischi residui

> ⚠️ **Il MAC address non è un'autenticazione hardware affidabile.**  
> Il MAC è verificato lato backend come controllo di coerenza tra il PC target registrato nel wizard e l'agent che si autentica, ma **può essere modificato via software** da un attaccante che abbia accesso fisico o di rete al PC target prima dell'esecuzione del wizard.  
> In scenari ad alto rischio (ambienti non sorvegliati, PC con accesso pubblico o condiviso), **NON fare affidamento sul MAC come unico fattore di binding hardware**.

| Scenario di attacco                                     | Probabilità    | Impatto  | Mitigazione attuale                                                                     |
| ------------------------------------------------------- | -------------- | -------- | --------------------------------------------------------------------------------------- |
| Replay con codice intercettato                          | 🟡 Bassa       | 🔴 Alto  | `used_at` monouso — il codice è già consumato al primo uso legittimo                    |
| MAC spoofing + codice intercettato                      | 🟢 Molto bassa | 🔴 Alto  | Rate limit + lockout dopo 3 tentativi — richiede intercettazione in-flight su HTTPS     |
| Brute force codice `WD-XXXXX` (~100k combinazioni)      | 🟡 Bassa       | 🟠 Medio | Rate limit 5 req/min per codice + lockout 3 errori MAC                                  |
| Insider threat (tecnico malevolo con accesso legittimo) | 🟠 Media       | 🔴 Alto  | Log audit (`last_attempt_ip`, `used_at`) + scadenza 24h che limita la finestra di abuso |

**Note interpretative:**

- "Bassa" indica che l'exploit richiede condizioni non banali (MITM su HTTPS, accesso fisico al PC, ecc.).
- "Media" per l'insider threat riflette il fatto che un tecnico con accesso al portale potrebbe creare wizard ad hoc — da mitigare con log di audit e approvazione admin.

---

### Roadmap sicurezza post-MVP

Il limite principale del binding MAC è la sua modificabilità software. La soluzione a lungo termine è un **challenge-response hardware**, che funziona così:

1. **Backend genera un challenge** — stringa casuale monouso (nonce) associata al wizard.
2. **Agent firma il challenge** con una chiave privata hardware-bound (es. TPM, chiave derivata da hardware ID Windows: UUID BIOS + numero seriale disco).
3. **Backend verifica la firma** con la chiave pubblica registrata al momento della creazione del wizard.

Questo approccio è più robusto perché:

- La chiave privata non lascia mai il dispositivo (se TPM-backed).
- Il challenge è monouso e con scadenza: un replay è impossibile.
- Anche con accesso fisico temporaneo al PC, un attaccante non può estrarre la chiave privata da un TPM 2.0.

**Quando implementarlo:** post-MVP, quando il parco macchine target è noto e omogeneo. Su PC consumer senza TPM 2.0 si può usare un hardware fingerprint misto (BIOS UUID + serial disco + MAC) come degraded fallback.

> 📌 Vedi issue #XX — Challenge-response hardware auth

---

### 🌐 **Backend API**

- **[0120-agent-api-reference.md](0120-agent-api-reference.md)** ✨ **NUOVO** — Documentazione endpoint `/api/agent/*`
  - POST `/api/agent/auth` — Autenticazione wizard
  - POST `/api/agent/start` — Avvia esecuzione
  - POST `/api/agent/step` — Registra passo
  - POST `/api/agent/complete` — Chiudi sessione
- **[0110-BackendLaravel AgentStep...](0110-BackendLaravelAgentStepControllerStepRequest.md)** ✅ — AgentStepController implementation

---

### 🎨 **Frontend React**

- **[0013-SetupprogettoReact.md](0013-SetupprogettoReact.md)** ✅ — Setup Vite + React 18
- **[0104-frontend React e tooling Vite.md](0104-frontend%20React%20e%20tooling%20Vite.md)** ✅ — Tooling, structure, best practices
- **[0014-Login.md](0014-Login.md)** ✅ — Pagina Login (form + autenticazione)
- **[0015-dashboard.md](0015-dashboard.md)** ✅ — Dashboard principale (stats + charts)
- **[0016-wizards.md](0016-wizards.md)** ⚠️ — Wizard builder multi-step (payload check needed)
- **[0107-componenti React e UX.md](0107-componenti%20React%20e%20UX.MD)** ⚠️ — Componenti UI generici

---

### 🤖 **Agent Windows (Python)**

- **[0019-Struttura agent.md](0019-Struttura%20agent.md)** ✅ — Struttura agent GUI + CustomTkinter
- **[0020-Scansione app installate.md](0020-Scansione%20app%20installate.md)** ✅ — System scanning (installed apps)
- **[0021-Installer-configurazione sistema.md](0021-Installer-configurazione%20sistema.md)** ✅ — Software installation + system config
- **[0109-agent Python CustomTkinter.md](0109-agent%20Python%2C%20CustomTkinter%2C%20file%20lunghi%2C%20flusso%20multi-schermata.md)** ⚠️ — Agent GUI flow (screen_overview, screen_progress)

---

### 📊 **Schema Dati**

- **[docs/schemas/wizard-config.schema.json](schemas/wizard-config.schema.json)** ✅ **CANONICO** — JSON Schema v7 WizardConfig v1.0
- **[docs/schemas/wizard-config-example.json](schemas/wizard-config-example.json)** ✅ — Payload di esempio valido
- **[0008-db-schema.md](0008-db-schema.md)** ✅ **FUSIONE 0008+0105** — Database design MySQL (6 tabelle + migrations Laravel)

---

### 🎯 **Report e Analytics**

- **[0018-userReports SoftwareLibrary TemplateManager.md](0018-userReportsSoftwareLibraryTemplateManager.md)** ⚠️ — Reports, Software Library, Templates

---

### 🚀 **Deployment e Produzione**

- **[0001-projectdescription.txt](0001-projectdescription.txt)** ✅ — Descrizione progetto generale + architettura
- **[0100-File temporanei tmp\_.php.md](0100-File%20temporanei%20tmp_.php%20esposti%20nella%20root.md)** ⚠️ — Sicurezza (temp files esposti)

---

## 📌 Stato Aggiornamenti

### ✅ Aggiornato / In Linea

| File                             | Stato | Note                               |
| -------------------------------- | ----- | ---------------------------------- |
| 0001-projectdescription.txt      | ✅    | Overview OK                        |
| 0003-setupiniziale.md            | ✅    | XAMPP setup OK                     |
| 0004-Strutturacartelle.md        | ✅    | Cartelle OK                        |
| 0005-filedotenv.md (UPDATED)     | ✅    | .env con variabili complete        |
| 0008-db-schema.md                | ✅    | Database MySQL (FUSIONE 0008+0105) |
| 0009-scaffoldprogetto.md         | ✅    | Laravel scaffold OK                |
| 0010-autenticazione.md           | ✅    | Auth flow OK                       |
| 0013-SetupprogettoReact.md       | ✅    | React setup OK                     |
| 0014-Login.md                    | ✅    | LoginPage OK                       |
| 0015-dashboard.md                | ✅    | Dashboard OK                       |
| 0016-wizards.md                  | ✅    | Payload snake_case OK              |
| 0019-Struttura agent.md          | ✅    | Agent structure OK                 |
| 0020-Scansione app.md            | ✅    | System scanning OK                 |
| 0021-Installer-configurazione.md | ✅    | Installation OK                    |
| 0101-auth e sicurezza.md         | ✅    | Controllers OK                     |
| 0104-frontend React e tooling.md | ✅    | Frontend tools OK                  |
| 0108-Zustand authStore.md        | ✅    | Auth store OK                      |
| 0110-BackendLaravel AgentStep.md | ✅    | AgentStepController OK             |
| 0120-agent-api-reference.md      | ✅    | Agent API endpoints OK             |

### ⚠️ Parzialmente Aggiornati

| File                     | Stato | Motivo                      | Azione                              |
| ------------------------ | ----- | --------------------------- | ----------------------------------- |
| 0018-userReports...md    | ⚠️    | Coverage incompleto         | ⏳ Da completare reports detail     |
| 0107-componenti React.md | ⚠️    | Generico                    | ⏳ Da dettagliare con real examples |
| 0100-File temporanei.md  | ⚠️    | Security concern temp files | ⏳ Da revisionare                   |

### 🔀 Deprecati / Uniti

| File Originale                | Stato        | Dove Unito                    |
| ----------------------------- | ------------ | ----------------------------- |
| 0014-PaginaLogin.md           | 🔀 Deprecato | → 0014-Login.md               |
| 0103-configurazione auth...md | 🔀 Deprecato | → 0101-auth e sicurezza.md    |
| 0105-schema DB.md             | 🔀 Deprecato | → 0008-db-schema.md (FUSIONE) |

### ✨ Nuovi

| File                                    | Contenuto                                                                |
| --------------------------------------- | ------------------------------------------------------------------------ |
| 0120-agent-api-reference.md             | Documentazione completa endpoint agent (/auth, /start, /step, /complete) |
| docs/schemas/wizard-config.schema.json  | JSON Schema v7 canonico WizardConfig                                     |
| docs/schemas/wizard-config-example.json | Payload di esempio                                                       |
| docs/README.md                          | Questo indice navigabile                                                 |

---

## 🎯 Quick Links per Ruoli

### 👨‍💻 **Developer Frontend**

1. [0013-SetupprogettoReact.md](0013-SetupprogettoReact.md) — Setup Vite
2. [0104-frontend React e tooling.md](0104-frontend%20React%20e%20tooling%20Vite.md) — Tooling
3. [0014-Login.md](0014-Login.md) — Auth UI
4. [0016-wizards.md](0016-wizards.md) — Wizard builder
5. [0120-agent-api-reference.md](0120-agent-api-reference.md) — API contracts

### 👨‍💼 **Developer Backend**

1. [0005-filedotenv-UPDATED.md](0005-filedotenv-UPDATED.md) — .env setup
2. [0009-scaffoldprogetto.md](0009-scaffoldprogetto.md) — Laravel scaffold
3. [0010-autenticazione.md](0010-autenticazione.md) — Auth flows
4. [0101-auth e sicurezza.md](0101-auth%20e%20sicurezza.md) — Implementation
5. [0120-agent-api-reference.md](0120-agent-api-reference.md) — API endpoints

### 🐍 **Developer Agent Python**

1. [0019-Struttura agent.md](0019-Struttura%20agent.md) — Agent structure
2. [0020-Scansione app.md](0020-Scansione%20app%20installate.md) — System scanning
3. [0021-Installer-configurazione.md](0021-Installer-configurazione%20sistema.md) — Installer
4. [0120-agent-api-reference.md](0120-agent-api-reference.md) — API auth + endpoints
5. [docs/schemas/wizard-config.schema.json](schemas/wizard-config.schema.json) — Data contract

### 🔐 **Security / DevOps**

1. [0003-setupiniziale.md](0003-setupiniziale.md) — Hardening XAMPP
2. [0101-auth e sicurezza.md](0101-auth%20e%20sicurezza.md) — Implementazione auth
3. [0100-File temporanei.md](0100-File%20temporanei%20tmp_.php%20esposti%20nella%20root.md) — Temp files exposure
4. [0005-filedotenv-UPDATED.md](0005-filedotenv-UPDATED.md) — .env produzione

---

## 📚 Struttura Cartelle Documentazione

```
docs/
├── README.md                                    ← Sei qui
├── schemas/
│   ├── wizard-config.schema.json                (JSON Schema v1.0)
│   └── wizard-config-example.json               (Payload di esempio)
├── 0001-projectdescription.txt                  (Panoramica)
├── 0003-setupiniziale.md                        (XAMPP setup)
├── 0005-filedotenv.md                           (← vedi 0005-UPDATED)
├── 0005-filedotenv-UPDATED.md                   (✅ NUOVO)
├── 0010-autenticazione.md                       (Auth flows)
├── 0014-Login.md                                (Login page)
├── 0015-dashboard.md                            (Dashboard)
├── 0016-wizards.md                              (Wizard builder)
├── 0101-auth e sicurezza.md                     (Auth impl)
├── 0104-frontend React e tooling Vite.md        (Frontend)
├── 0108-Zustand authStore...md                  (Auth store)
├── 0110-BackendLaravel AgentStep...md           (Backend agent)
├── 0120-agent-api-reference.md                  (✨ NUOVO)
└── ... (altri file)
```

---

## 🔗 Referenze Esterne

- **Laravel 11**: https://laravel.com/docs/11.x
- **Sanctum**: https://laravel.com/docs/11.x/sanctum
- **JWT (tymon)**: https://github.com/tymondesigns/jwt-auth
- **React 18**: https://react.dev/
- **Vite**: https://vitejs.dev/
- **Tailwind CSS**: https://tailwindcss.com/

---

## ✏️ Come Contribuire alla Documentazione

1. **Aggiornamento**: Se cambi il codice, aggiorna la documentazione corrispondente
2. **Badge di stato**: Usa `✅ Aggiornato | ⚠️ Parziale | 🔀 Deprecato | ✨ Nuovo`
3. **Data**: Aggiungi data aggiornamento all'inizio di ogni file
4. **Commit message**: `docs(0XXX): brief description`

---

## 📞 Troubleshooting

### "Non trovo la documentazione per X"

→ Usa Ctrl+F su questo file o cerca in `grep -r "X" docs/`

### "La documentazione è outdated"

→ Apri un issue o PR con la sezione che deve essere aggiornata

### "Voglio aggiungere una nuova sezione"

→ Crea un file `0NNN-nomefile.md` e aggiungi l'entry a questo README.md

---

**Ultimo aggiornamento:** 2026-03-06  
**Versione schema WizardConfig:** 1.0
