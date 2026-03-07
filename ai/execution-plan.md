# WinDeploy — Execution Plan

> **Versione:** 1.0 — Marzo 2026  
> **Scopo:** Piano di esecuzione con dipendenze esplicite tra le fasi. Ogni fase specifica: prerequisiti, output attesi, AI assegnata, file prodotti, e criteri di completamento.

---

## Grafo delle dipendenze

```
0A → 0B → 0C → 0D → 0E
                          ↓
               0E → 1 (Ricerca) → 2 (XAMPP)
                                        ↓
                               2 → 3 (DB) → 4 (Backend) → 5 (Frontend) → 6 (Agent)
                                                  ↓
                                         4 → 7 (Security) → 8 (DB Opt) → 9 (Testing)
                                                                                 ↓
                                                                   9 → 10 (Performance) → 11 (Refactoring)
                                                                                                   ↓
                                                                                    11 → 12 (Deploy) → 13 (Tunnel)
```

> Le fasi **7–11** possono girare **in parallelo** rispetto allo sviluppo di moduli già stabili.

---

## Stato globale

| Fase | Nome                    | Stato       | AI              | Completata il |
|------|-------------------------|-------------|-----------------|---------------|
| 0A   | AI Workspace Init       | ✅ Fatto    | Claude          | 2026-03-07    |
| 0B   | Git & Branch Strategy   | ⬜ Todo     | Claude          | —             |
| 0C   | Architecture Decisions  | ⬜ Todo     | Claude          | —             |
| 0D   | Dev Environment Check   | ⬜ Todo     | Sonar           | —             |
| 0E   | Conventions Lock        | ⬜ Todo     | Claude          | —             |
| 1    | Ricerca iniziale        | ⬜ Todo     | Sonar           | —             |
| 2    | XAMPP Setup             | ⬜ Todo     | Claude          | —             |
| 3    | Database Schema         | ⬜ Todo     | Claude          | —             |
| 4    | Backend Laravel         | ⬜ Todo     | DeepSeek        | —             |
| 5    | Frontend React          | ⬜ Todo     | GPT 5.2         | —             |
| 6    | Agent Python            | ⬜ Todo     | Gemini 2.5 Pro  | —             |
| 7    | Security Layer          | ⬜ Todo     | Claude          | —             |
| 8    | DB Optimization         | ⬜ Todo     | Claude          | —             |
| 9    | Testing                 | ⬜ Todo     | Claude          | —             |
| 10   | Performance             | ⬜ Todo     | Claude          | —             |
| 11   | Refactoring             | ⬜ Todo     | Gemini 2.5 Pro  | —             |
| 12   | Deploy Ubuntu           | ⬜ Todo     | Claude          | —             |
| 13   | Cloudflare Tunnel       | ⬜ Todo     | Claude          | —             |

---

## Dettaglio per fase

---

### Fase 0A — AI Workspace Init

| Campo         | Valore                                                                 |
|---------------|------------------------------------------------------------------------|
| Prerequisiti  | Nessuno — è la fase radice                                             |
| AI            | Claude Sonnet 4.6                                                      |
| Blocca        | Tutte le fasi successive                                               |
| Criterio OK   | Struttura `ai/` presente, conventions.md ratificato, .gitignore valido |

**Output file:**
- [x] `ai/README.md` — Indice del sistema AI Dev OS
- [x] `ai/ai-task-router.md` — Questo file
- [x] `ai/execution-plan.md` — Piano di esecuzione
- [ ] `ai/conventions.md` — Naming, commit format, pattern obbligatori
- [ ] `ai/decisions_log.md` — ADR (Architecture Decision Records)
- [ ] `ai/ai_notes.md` — Lessons learned e note operative
- [ ] `ai/project_state.md` — Stato corrente del progetto (aggiornato a ogni fase)
- [ ] `.gitignore` — Include: `.env`, `storage/logs`, `node_modules`, `__pycache__`, `dist/`

**Commit atteso:** `docs(ai): initialize AI workspace structure and conventions`

---

### Fase 0B — Git & Branch Strategy

| Campo         | Valore                                                    |
|---------------|-----------------------------------------------------------|
| Prerequisiti  | 0A completato                                             |
| AI            | Claude Sonnet 4.6                                         |
| Blocca        | 0C, 0D, 0E                                               |
| Criterio OK   | Branch strategy documentata, protezioni main configurate  |

**Output file:**
- [ ] `ai/git-strategy.md` — Branch model (main/develop/feature/hotfix)
- [ ] `.github/PULL_REQUEST_TEMPLATE.md`
- [ ] `.github/ISSUE_TEMPLATE/bug_report.md`
- [ ] `.github/ISSUE_TEMPLATE/feature_request.md`

**Commit atteso:** `docs(git): define branch strategy and GitHub templates`

---

### Fase 0C — Architecture Decisions

| Campo         | Valore                                                              |
|---------------|---------------------------------------------------------------------|
| Prerequisiti  | 0B completato                                                       |
| AI            | Claude Sonnet 4.6                                                   |
| Blocca        | 0E                                                                  |
| Criterio OK   | ADR scritti per: dual-auth, tunnel, agent protocol, DB schema v1    |

**Output file:**
- [ ] `ai/decisions_log.md` — ADR-001: Dual auth Sanctum+JWT; ADR-002: Cloudflare Tunnel outbound; ADR-003: Agent .exe PyInstaller
- [ ] `docs/architecture.md` — Diagramma testuale del sistema

**Commit atteso:** `docs(arch): record architecture decisions ADR-001 to ADR-003`

---

### Fase 0D — Dev Environment Check

| Campo         | Valore                                                                |
|---------------|-----------------------------------------------------------------------|
| Prerequisiti  | 0A completato                                                         |
| AI            | Sonar (Perplexity)                                                    |
| Blocca        | 0E (in parallelo con 0C)                                              |
| Criterio OK   | Versioni verificate e compatibilità confermata per tutto lo stack     |

**Task Sonar:** Verificare versioni ESATTE e compatibilità di:
- Laravel 11.x (ultima patch stabile)
- PHP 8.3.x (versione XAMPP bundled attuale)
- MySQL 8.0.x vs 8.4.x — breaking changes in produzione Ubuntu 24
- React 18.x + Vite 6.x — peer dependency conflicts noti
- Python 3.11.x + CustomTkinter + PyInstaller — versione .exe stabile
- `tymondesigns/jwt-auth` — compatibilità con Laravel 11
- `spatie/laravel-permission` v6 — compatibilità con Sanctum

**Output file:**
- [ ] `ai/stack-versions.md` — Tabella versioni pinned con source e data verifica

**Commit atteso:** `docs(stack): pin verified dependency versions for all layers`

---

### Fase 0E — Conventions Lock

| Campo         | Valore                                                     |
|---------------|------------------------------------------------------------||
| Prerequisiti  | 0A, 0B, 0C, 0D completati                                 |
| AI            | Claude Sonnet 4.6                                          |
| Blocca        | Fase 1 e tutte le fasi di sviluppo                         |
| Criterio OK   | conventions.md ratificato e accettato dal team             |

**Output file:**
- [ ] `ai/conventions.md` — Naming PHP/Python/React, commit format, structure regole, pattern vietati
- [ ] `.editorconfig` — Indent, charset, line endings per ogni file type
- [ ] `phpcs.xml` — Ruleset PHP CodeSniffer progetto

**Commit atteso:** `chore(conventions): lock project-wide coding standards and tooling`

---

### Fase 1 — Ricerca iniziale

| Campo         | Valore                                                              |
|---------------|---------------------------------------------------------------------|
| Prerequisiti  | 0A, 0B completati                                                   |
| AI            | Sonar (Perplexity)                                                  |
| Blocca        | Fase 2                                                              |
| Criterio OK   | `stack-versions.md` compilato e validato (da 0D) + CVE check fatto |

**Task Sonar:**
- CVE attivi per Laravel 11, JWT-auth, Sanctum (ultimi 6 mesi)
- Breaking changes MySQL 8.0 → 8.4 rilevanti per schema WinDeploy
- Problemi noti PyInstaller + CustomTkinter su Windows 11 ARM/x64
- Cloudflare Tunnel cloudflared: versione daemon stabile attuale

**Output file:**
- [ ] `ai/security-research.md` — CVE trovati, mitigazioni, versioni patched

**Commit atteso:** `docs(security): initial CVE and compatibility research`

---

### Fase 2 — XAMPP Local Setup

| Campo         | Valore                                                              |
|---------------|---------------------------------------------------------------------|
| Prerequisiti  | Fase 1 completata                                                   |
| AI            | Claude Sonnet 4.6                                                   |
| Blocca        | Fase 3                                                              |
| Criterio OK   | `windeploy.local` raggiungibile, `.env` configurato, DB connesso    |

**Output file:**
- [ ] `docs/setup-local.md` — Guida step-by-step XAMPP + virtual host
- [ ] `.env.example` — Template variabili ambiente (nessun segreto)
- [ ] `config/database.php` — Configurazione MySQL con charset utf8mb4

**Commit atteso:** `chore(env): configure XAMPP virtual host and local environment`

---

### Fase 3 — Database Schema

| Campo         | Valore                                                               |
|---------------|----------------------------------------------------------------------|
| Prerequisiti  | Fase 2 completata                                                    |
| AI            | Claude Sonnet 4.6                                                    |
| Blocca        | Fase 4                                                               |
| Criterio OK   | Tutte le migration girano senza errori; seeders popolano dati test  |

**Moduli da coprire:**
- `users` — con ruoli (spatie/permission), `is_active`, `last_seen_at`
- `clients` — macchine Windows gestite, `uuid`, `hostname`, `ip_snapshot`
- `agent_tokens` — JWT monouso con `used_at`, `expires_at`, `client_id`
- `deployments` — job inviati all'agent, stato, log, esito
- `audit_logs` — ogni azione critica loggata con `cf_ip`, `user_id`, `payload`

**Output file:**
- [ ] `database/migrations/XXXX_create_users_table.php`
- [ ] `database/migrations/XXXX_create_clients_table.php`
- [ ] `database/migrations/XXXX_create_agent_tokens_table.php`
- [ ] `database/migrations/XXXX_create_deployments_table.php`
- [ ] `database/migrations/XXXX_create_audit_logs_table.php`
- [ ] `database/seeders/DatabaseSeeder.php`
- [ ] `docs/db-schema.md` — ERD testuale e descrizione relazioni

**Commit atteso:** `feat(db): create initial schema migrations and seeders`

---

### Fase 4 — Backend Laravel

| Campo         | Valore                                                               |
|---------------|----------------------------------------------------------------------|
| Prerequisiti  | Fase 3 completata                                                    |
| AI            | DeepSeek (CRUD) + Claude (logica auth/token)                        |
| Blocca        | Fase 5, Fase 7                                                       |
| Criterio OK   | Tutti gli endpoint rispondono correttamente via Postman/curl         |

**Suddivisione prompt:**
1. **DeepSeek** — CRUD standard: ClientController, DeploymentController, Resource classes
2. **Claude** — Auth: AgentTokenService (emissione JWT monouso), Sanctum guards
3. **DeepSeek** — API routes, middleware stack, FormRequest validators

**Output file:**
- [ ] `app/Models/{User,Client,AgentToken,Deployment,AuditLog}.php`
- [ ] `app/Http/Controllers/Api/{ClientController,DeploymentController,AuthController}.php`
- [ ] `app/Http/Controllers/Api/Agent/TokenController.php`
- [ ] `app/Http/Requests/{StoreClientRequest,StoreDeploymentRequest}.php`
- [ ] `app/Http/Resources/{ClientResource,DeploymentResource}.php`
- [ ] `app/Services/AgentTokenService.php`
- [ ] `routes/api.php`
- [ ] `config/jwt.php`

**Commit atteso:** `feat(backend): implement Laravel API controllers, models and JWT agent service`

---

### Fase 5 — Frontend React

| Campo         | Valore                                                                |
|---------------|-----------------------------------------------------------------------|
| Prerequisiti  | Fase 4 completata (API disponibili)                                   |
| AI            | GPT 5.2                                                               |
| Blocca        | Fase 6 (UI agent status), Fase 11 (refactoring UI)                   |
| Criterio OK   | Dashboard funzionante, TanStack Query integrato, login Sanctum OK     |

**Suddivisione prompt GPT:**
1. Layout + routing React Router v6 + Tailwind setup
2. Auth flow (login, logout, sessione Sanctum SPA)
3. Dashboard clienti (lista, dettaglio, stato agent)
4. Form deployment + feedback real-time

**Output file:**
- [ ] `resources/js/components/{Layout,Navbar,Sidebar}.tsx`
- [ ] `resources/js/pages/{Dashboard,Clients,Deployments,Login}.tsx`
- [ ] `resources/js/hooks/{useAuth,useClients,useDeployments}.ts`
- [ ] `resources/js/lib/api.ts` — Axios instance con CSRF + baseURL
- [ ] `vite.config.ts` — Proxy verso windeploy.local
- [ ] `tailwind.config.ts`

**Commit atteso:** `feat(frontend): implement React dashboard with TanStack Query and Sanctum auth`

---

### Fase 6 — Agent Python

| Campo         | Valore                                                                |
|---------------|-----------------------------------------------------------------------|
| Prerequisiti  | Fase 4 completata (endpoint JWT disponibile)                          |
| AI            | Gemini 2.5 Pro                                                        |
| Blocca        | Fase 9 (testing agent), Fase 13 (tunnel agent)                       |
| Criterio OK   | .exe compila, si connette all'API, esegue comando test, log visibile  |

**Suddivisione prompt Gemini:**
1. Struttura progetto Python + CustomTkinter UI (finestra principale, tray icon)
2. JWT fetch + validazione monouso + chiamata API backend
3. Esecuzione comandi PowerShell/winget con output capture
4. PyInstaller spec file per build .exe one-file

**Output file:**
- [ ] `agent/main.py`
- [ ] `agent/ui/main_window.py`
- [ ] `agent/core/api_client.py`
- [ ] `agent/core/command_runner.py`
- [ ] `agent/core/jwt_handler.py`
- [ ] `agent/windeploy-agent.spec` — PyInstaller config
- [ ] `agent/requirements.txt`
- [ ] `docs/agent-build.md` — Istruzioni build e distribuzione

**Commit atteso:** `feat(agent): implement Python agent with CustomTkinter UI and JWT auth`

---

### Fase 7 — Security Layer

| Campo         | Valore                                                                |
|---------------|-----------------------------------------------------------------------|
| Prerequisiti  | Fase 4 completata                                                     |
| AI            | Claude Sonnet 4.6                                                     |
| Blocca        | Fase 9 (security testing)                                             |
| Può girare in parallelo con | Fase 5, Fase 6 (se Fase 4 è stabile)               |
| Criterio OK   | Middleware audit attivo, rate limiting configurato, CF-IP validato    |

**Task Claude:**
- Middleware `TrustCloudflareProxy` — legge `CF-Connecting-IP`, rifiuta se header assente
- Middleware `ValidateSingleUseJwt` — verifica token, segna `used_at`, blocca replay
- Rate limiting per IP su endpoint agent (max 10 req/min)
- Audit logging automatico su ogni azione critica
- Policy Spatie Permission per ruoli: `admin`, `operator`, `agent`

**Output file:**
- [ ] `app/Http/Middleware/{TrustCloudflareProxy,ValidateSingleUseJwt,AuditLogger}.php`
- [ ] `app/Policies/{ClientPolicy,DeploymentPolicy}.php`
- [ ] `app/Providers/AuthServiceProvider.php` (aggiornato)
- [ ] `database/seeders/RoleSeeder.php`
- [ ] `docs/security-model.md`

**Commit atteso:** `security(auth): implement CF-IP trust, single-use JWT validation and audit middleware`

---

### Fase 8 — DB Optimization

| Campo         | Valore                                                              |
|---------------|---------------------------------------------------------------------|
| Prerequisiti  | Fase 4 completata, Fase 3 stabile                                  |
| AI            | Claude Sonnet 4.6                                                   |
| Può girare in parallelo con | Fase 5, Fase 6                                      |
| Criterio OK   | EXPLAIN ANALYZE su query critiche: nessun full table scan           |

**Task Claude:**
- Indici su `agent_tokens.expires_at`, `deployments.client_id`, `audit_logs.created_at`
- Eager loading nei Resource (N+1 eliminato)
- Query scope su `Deployment::pending()`, `Client::active()`
- Strategia purge `agent_tokens` scaduti (command schedulato)

**Output file:**
- [ ] `database/migrations/XXXX_add_performance_indexes.php`
- [ ] `app/Console/Commands/PurgeExpiredTokens.php`
- [ ] `docs/db-optimization.md` — EXPLAIN output e motivazioni indici

**Commit atteso:** `perf(db): add indexes, eliminate N+1 queries and schedule token purge`

---

### Fase 9 — Testing

| Campo         | Valore                                                               |
|---------------|----------------------------------------------------------------------|
| Prerequisiti  | Fase 4 + Fase 7 completate                                           |
| AI            | Claude Sonnet 4.6 (Pest/PHPUnit) + Gemini (agent Python)            |
| Blocca        | Fase 10, Fase 12                                                     |
| Criterio OK   | Coverage > 80% su moduli critici; 0 test falliti in CI              |

**Suddivisione:**
1. **Claude** — Feature test Laravel: auth, CRUD endpoint, JWT single-use, rate limit
2. **Claude** — Unit test: AgentTokenService, AuditLogger, CF-IP Middleware
3. **Gemini** — Test Python agent: mock API, command runner, JWT handler

**Output file:**
- [ ] `tests/Feature/{AuthTest,ClientTest,DeploymentTest,AgentTokenTest}.php`
- [ ] `tests/Unit/{AgentTokenServiceTest,AuditLoggerTest,TrustCloudflareProxyTest}.php`
- [ ] `agent/tests/{test_api_client.py,test_command_runner.py,test_jwt_handler.py}`
- [ ] `.github/workflows/ci.yml` — GitHub Actions: PHP + Python test suite

**Commit atteso:** `test(coverage): add feature and unit tests for backend and agent`

---

### Fase 10 — Performance

| Campo         | Valore                                                              |
|---------------|---------------------------------------------------------------------|
| Prerequisiti  | Fase 9 completata                                                   |
| AI            | Claude Sonnet 4.6                                                   |
| Blocca        | Fase 11                                                             |
| Criterio OK   | Response time API < 200ms p95 in locale; OPcache abilitato          |

**Task Claude:**
- Laravel route caching e config caching per produzione
- OPcache configuration Nginx/PHP-FPM
- Queue per deployment jobs (non bloccanti su HTTP)
- Response caching su endpoint read-only con `Cache-Control`

**Output file:**
- [ ] `app/Jobs/ExecuteDeploymentJob.php`
- [ ] `config/queue.php` (aggiornato per database driver)
- [ ] `database/migrations/XXXX_create_jobs_table.php`
- [ ] `docs/performance.md`

**Commit atteso:** `perf(queue): move deployment execution to async jobs`

---

### Fase 11 — Refactoring

| Campo         | Valore                                                               |
|---------------|----------------------------------------------------------------------|
| Prerequisiti  | Fase 9 completata (test verdi prima di refactoring)                  |
| AI            | Gemini 2.5 Pro                                                       |
| Blocca        | Fase 12                                                              |
| Criterio OK   | Nessun test rotto; PSR-12 su tutto il codebase; 0 code smell critici |

**Task Gemini (analisi multi-file):**
- Code review completo del backend Laravel
- Estrazione logica duplicata in Service layer
- Refactoring Controller: thin controller pattern
- Rimozione dead code e TODO non risolti
- Review nomenclatura vs conventions.md

**Output file:**
- [ ] File modificati (Gemini produce diff da applicare)
- [ ] `ai/decisions_log.md` — Aggiornato con scelte di refactoring

**Commit atteso:** `refactor(backend): extract service layer, apply thin controller pattern`

---

### Fase 12 — Deploy Ubuntu

| Campo         | Valore                                                                |
|---------------|-----------------------------------------------------------------------|
| Prerequisiti  | Fase 11 completata + Fase 9 (tutti test verdi)                       |
| AI            | Claude Sonnet 4.6                                                     |
| Blocca        | Fase 13                                                               |
| Criterio OK   | App risponde su windeploy.mavcoo.it, .env produzione configurato      |

**Task Claude:**
- Script deploy Ubuntu 24: clone repo, `composer install --no-dev`, `npm run build`
- Nginx config: server block con PHP-FPM, gzip, headers sicurezza
- Systemd service per Laravel queue worker
- Cron per `schedule:run`
- Permessi corretti `storage/` e `bootstrap/cache/`

**Output file:**
- [ ] `docs/deploy-ubuntu.md` — Runbook completo
- [ ] `scripts/deploy.sh` — Script idempotente di deploy
- [ ] `nginx/windeploy.conf` — Config Nginx produzione
- [ ] `systemd/windeploy-worker.service`

**Commit atteso:** `chore(deploy): add Ubuntu production deploy scripts and Nginx config`

---

### Fase 13 — Cloudflare Tunnel

| Campo         | Valore                                                                 |
|---------------|------------------------------------------------------------------------|
| Prerequisiti  | Fase 12 completata                                                     |
| AI            | Claude Sonnet 4.6                                                      |
| Blocca        | —  (ultima fase)                                                       |
| Criterio OK   | Tutti i sottodomini raggiungibili pubblicamente via tunnel; CF-IP valido|

**Task Claude:**
- Config `cloudflared` per tunnel `32e9943d-d2d3-41e1-9776-94a684aaec30`
- Ingress rules per: `windeploy.mavcoo.it`, `api.`, `dev.`, `remote.`, `test.`
- Systemd service cloudflared con auto-restart
- Validazione header `CF-Connecting-IP` end-to-end
- Firewall Ubuntu: blocca accesso diretto a porta 80/443 (solo tunnel)

**Output file:**
- [ ] `cloudflare/config.yml` — Tunnel ingress config
- [ ] `cloudflare/tunnel-setup.md` — Guida configurazione e troubleshooting
- [ ] `systemd/cloudflared.service`
- [ ] `docs/network-architecture.md` — Schema traffico: Client → CF Edge → Tunnel → Nginx → Laravel

**Commit atteso:** `chore(tunnel): configure Cloudflare Tunnel ingress for all subdomains`

---

## Regole operative generali

1. **Prerequisiti bloccanti** — Non iniziare una fase se i prerequisiti non sono tutti ✅ nella tabella di stato.
2. **File allegati obbligatori** — Non inviare prompt a un'AI senza referenziare i file prerequisiti della fase.
3. **Aggiornamento stato** — Aggiornare `ai/project_state.md` immediatamente dopo ogni fase completata.
4. **Gestione problemi a ritroso** — Se una fase rivela un problema in una fase precedente:
   - Documenta il problema in `ai/decisions_log.md` con data e impatto
   - Torna alla fase incriminata e segna come 🔄 In revisione
   - Aggiorna `ai/ai_notes.md` con la lesson learned
   - Ri-esegui i test della fase riparata prima di procedere
5. **Parallelismo** — Le fasi 7–11 possono girare in parallelo rispetto allo sviluppo se il modulo target è già stabile e testato.
6. **Sicurezza non negoziabile** — Qualsiasi modifica che tocca auth, token, permessi o IP richiede obbligatoriamente Claude e una revisione di Fase 7 prima del deploy.

---

## Template aggiornamento project_state.md

Dopo ogni fase completata, aggiungere una voce:

```markdown
## Aggiornamento [DATA]

**Fase completata:** [numero e nome]
**AI usata:** [nome AI]
**File prodotti:** [lista]
**Problemi riscontrati:** [nessuno / descrizione]
**Prossima fase:** [numero e nome]
**Blocchi aperti:** [nessuno / descrizione]
```
