# WinDeploy — Project State

Ultimo aggiornamento: 2026-03-07
Versione: 0.3.0 (pre-MVP)

---

## Stato per modulo

| Modulo   | Completamento | Ultimo lavoro                                      | Prossimo step                                      |
|----------|---------------|----------------------------------------------------|----------------------------------------------------|
| Backend  | 35%           | Route API complete, controller stub, auth guard JWT| Implementare controller core (Auth, Wizard, Agent) |
| Frontend | 30%           | Setup React+Vite, route, login page, Zustand stub  | Verificare authStore, configurare proxy Vite /api  |
| Agent    | 40%           | screen_connect, scansione app, installer base      | screen_overview, screen_complete, UAC check        |
| Deploy   | 10%           | Cloudflare Tunnel operativo, Nginx assente         | Config Nginx prod, script deploy.sh Ubuntu 24 LTS  |

---

## Feature completate (con data e commit)

| Data       | Feature                                         | Commit/Branch                  |
|------------|-------------------------------------------------|--------------------------------|
| 2026-03-03 | Setup iniziale monorepo (backend/frontend/agent)| branch: add-agent-backend-frontend |
| 2026-03-04 | Route API complete `routes/api.php`             | add-agent-backend-frontend     |
| 2026-03-04 | Struttura cartelle agent (screen_connect, api_client stub) | add-agent-backend-frontend |
| 2026-03-04 | 39 issue GitHub create per tracciamento MVP     | —                              |
| 2026-03-07 | Sistema memoria AI creato (`ai/memory/`)        | main                           |

---

## Feature in corso

- [ ] **AuthController** — login/logout/me con Sanctum (`#2`)
- [ ] **AgentController** — auth JWT + start/step/complete (`#2`, `#3`, `#12`)
- [ ] **WizardController** — CRUD + generateCode + monitor (`#2`, `#9`)
- [ ] **Eloquent Models** — Wizard, WizardExecution, Report, SoftwareLibrary, Template (`#35`)
- [ ] **Migration core** — wizards, templates, software_library, execution_logs, reports (`#7`)
- [ ] **Zustand authStore** — login/logout/persist (`#10`)
- [ ] **Vite proxy /api** — punta a windeploy.local.api (`#4`)

---

## Feature bloccate (con motivo blocco)

| Feature                        | Bloccata da                                              | Issue  |
|--------------------------------|----------------------------------------------------------|--------|
| Monitor real-time frontend     | `POST /api/agent/step` non implementato                  | #12    |
| Report HTML generazione        | `ReportGeneratorService` mancante + Models assenti       | #22    |
| Deploy produzione Ubuntu       | Nginx config + script deploy.sh assenti                  | #19    |
| Test suite PHPUnit             | Controller non implementati (nulla da testare)           | #15    |
| Agent build .exe PyInstaller   | `windeploy.spec` + `requirements.txt` assenti            | #26    |
| CORS produzione frontend       | `config/cors.php` non configurato per domini separati    | #39    |

---

## Issue GitHub aperte per priorità

### 🔴 MVP Blocker (priorità assoluta)

| # | Modulo    | Titolo breve                                           |
|---|-----------|--------------------------------------------------------|
| 2 | Backend   | Controller API non implementati — solo stub route      |
| 3 | Backend   | Guard JWT `api` non configurato in `config/auth.php`   |
| 4 | Frontend  | Proxy Vite `/api` non configurato — 404 su tutte API   |
| 7 | Database  | Migration mancanti per entità core                     |
| 8 | Integrazione | Contratto JSON `wizardConfig` non definito           |
| 9 | Backend   | Services mancanti (WizardCodeService, EncryptionService)|
| 10| Frontend  | Zustand authStore non verificabile                     |
| 11| Agent     | Schermate GUI incomplete (mancano overview/complete)   |
| 12| Agent+BE  | Endpoint `POST /api/agent/step` non implementato       |
| 13| Sicurezza | MAC address spoofable — implementare token monouso     |
| 14| Backend   | FormRequest validazione assenti                        |
| 19| Deploy    | Config Nginx mancante per produzione Ubuntu 24         |
| 20| Database  | Policy scadenza codici wizard + cleanup job assenti    |
| 21| Agent     | Rilevamento hardware non implementato                  |
| 22| Backend   | ReportGeneratorService non implementato                |
| 23| Agent     | Gestione UAC PowerShell elevata                        |
| 27| Sicurezza | Rate limiting non configurato                          |
| 35| Backend   | Eloquent Models non trovati                            |
| 36| Frontend  | Axios baseURL hardcoded                                |
| 37| Agent     | Gestione concorrenza winget install multipli           |
| 38| Integrazione | Schema JSON wizardConfig non validato              |
| 39| Deploy    | CORS produzione non configurata                        |

### 🟠 Alta priorità (post-MVP blocker)

| # | Modulo   | Titolo breve                                            |
|---|----------|---------------------------------------------------------|
| 1 | Backend  | File `tmp_*.php` esposti nella root — sicurezza         |
| 17| Agent    | Logging strutturato su file per troubleshooting         |
| 18| Deploy   | Script `deploy.sh` per Ubuntu Server 24 LTS             |
| 25| Database | Migration `wizards_software` many-to-many               |
| 29| Database | Migration rollback strategy per produzione              |
| 31| Backend  | ENV variables non criptate — rischio leak               |
| 33| Backend  | Audit log per azioni sensibili                          |
| 34| Database | Tabella `software_library` manca `file_path`            |

### 🟡 Media/Bassa priorità (post-MVP)

| # | Modulo   | Titolo breve                                            |
|---|----------|---------------------------------------------------------|
| 5 | Agent    | File `tmp_test_start.py` in produzione                  |
| 6 | Agent    | `__pycache__` tracciata in Git                          |
| 15| Testing  | Test suite PHPUnit + Feature tests                      |
| 16| Frontend | Error Boundary React                                    |
| 24| Frontend | Polling monitor non gestisce disconnessioni             |
| 26| Agent    | Build PyInstaller non documentato                       |
| 28| Agent    | Gestione offline fallback — no retry su timeout rete    |
| 30| Frontend | Wizard Builder non salva bozze intermedie               |
| 32| Agent    | PyInstaller .exe dimensioni eccessive                   |

---

## Prossime 3 azioni da fare

1. **Implementare AuthController + configurare guard JWT** (`#2`, `#3`) — blocca tutto il resto
2. **Creare Migration core + Eloquent Models** (`#7`, `#35`) — necessari per qualsiasi test backend
3. **Configurare proxy Vite + Zustand authStore** (`#4`, `#10`) — sblocca frontend-backend integration

---

> **Come aggiornare questo file:**
> Alla fine di ogni sessione esegui il prompt di aggiornamento in `ai/memory/README.md`.
