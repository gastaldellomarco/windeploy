# WinDeploy — AI Task Router

> **Versione:** 1.0 — Marzo 2026  
> **Scopo:** Sistema di routing automatico per selezionare l'AI corretta per ogni tipo di task nel progetto WinDeploy.

---

## Tabella di routing per tipo di task

| Tipo task                           | AI primaria         | AI fallback        | Motivazione primaria                                  |
|-------------------------------------|---------------------|--------------------|-------------------------------------------------------|
| Sicurezza, auth, cifratura          | Claude Sonnet 4.6   | —                  | Ragionamento formale su trust boundary e threat model |
| Config server, infrastruttura       | Claude Sonnet 4.6   | Sonar              | Output preciso per Nginx/systemd/cloudflared          |
| Schema DB, migration, relazioni     | Claude Sonnet 4.6   | DeepSeek           | Coerenza vincoli FK, indici, rollback strategy        |
| Controller CRUD, logica business    | DeepSeek            | Kimi K2.5          | Velocità su PHP Laravel boilerplate denso             |
| API REST, endpoint, middleware      | DeepSeek            | Claude             | Ottimo su OpenAPI / Resource / FormRequest            |
| Frontend React, componenti, UI      | GPT 5.2             | —                  | Superiore su JSX, Tailwind, accessibilità             |
| State management, hooks, router     | GPT 5.2             | Claude             | TanStack Query, React Router v6, custom hooks         |
| Agent Python, PowerShell, Windows   | Gemini 2.5 Pro      | —                  | Window handle, WinAPI, PyInstaller edge case          |
| File lunghi, analisi codebase       | Gemini 2.5 Pro      | —                  | Context window 1M token                               |
| Scaffold, boilerplate bulk          | Kimi K2.5           | Gemini 2.5 Pro     | Velocità massima su output ripetitivo strutturato     |
| Versioni, best practice, CVE        | Sonar (Perplexity)  | —                  | Accesso web real-time, fonti verificate               |
| Troubleshooting errori specifici    | Sonar (Perplexity)  | Claude             | Cerca issue GitHub/StackOverflow aggiornati           |
| Documentazione, README, guide       | Gemini 2.5 Pro      | Claude             | Markdown lungo coerente, ottima struttura             |
| Refactoring, code review            | Gemini 2.5 Pro      | Claude             | Analisi multi-file, suggerimenti architetturali       |
| Testing, test cases, TDD            | Claude Sonnet 4.6   | DeepSeek           | PHPUnit, Pest, test cases edge, mocking Sanctum/JWT   |
| Performance, ottimizzazione query   | Claude Sonnet 4.6   | DeepSeek           | EXPLAIN ANALYZE, eager loading, cache strategy        |

---

## Algoritmo di selezione (flowchart testuale)

Rispondi a queste domande in ordine — fermati alla prima risposta affermativa:

```
1. Il task riguarda sicurezza, auth, token, cifratura, credenziali?
   → Claude Sonnet 4.6

2. Il task ha file > 500 righe OPPURE richiede analisi di più file contemporaneamente?
   → Gemini 2.5 Pro

3. Il task è puro frontend React (componenti JSX, Tailwind, UX, routing client)?
   → GPT 5.2

4. Il task è backend CRUD PHP/Laravel senza logica di sicurezza?
   → DeepSeek

5. Il task è boilerplate ripetitivo da generare in bulk (es: 10+ model/migration/seeder)?
   → Kimi K2.5

6. Il task richiede informazioni aggiornate (versioni pacchetti, CVE, changelog, doc ufficiale)?
   → Sonar (Perplexity)

7. In tutti gli altri casi:
   → Claude Sonnet 4.6
```

---

## Quando dividere un task in prompt separati

Dividi **obbligatoriamente** se si verifica almeno una di queste condizioni:

- Il task tocca **più di 2 moduli distinti** (es: backend + frontend + agent nello stesso prompt)
- Il task genera **più di 5 file nuovi**
- Una parte richiede **sicurezza** e un'altra richiede **boilerplate** (AI diverse, non mixare)
- Il task include sia **logica di dominio** che **query di ottimizzazione DB**
- Il task mescola **codice da produrre** e **ricerca di versioni/CVE**

> **Regola aurea:** ogni prompt deve essere indirizzabile a **una sola AI**. Se non riesci a scegliere un'AI unica, il task va diviso.

---

## Template prompt universale

Struttura obbligatoria per OGNI prompt inviato a qualsiasi AI del progetto:

```
[AI scelta] — [tipo task]

Stack rilevante: [solo le parti dello stack che impattano questo task]
  Esempio: Laravel 11, Sanctum, MySQL 8 — escludi frontend se non coinvolto

Contesto: [1-2 frasi su cosa fa questo pezzo nel sistema WinDeploy]
  Esempio: "Questo middleware valida il JWT monouso emesso dall'agent prima
  di eseguire comandi remoti sul client Windows."

File correlati:
  - [percorso/file1.php] — [cosa fa e perché impatta questo task]
  - [percorso/file2.tsx] — [...]

Task: [descrizione tecnica precisa di cosa implementare]
  - Includi: nomi metodi, firme API, comportamento atteso
  - Includi: casi edge da gestire
  - Escludi: tutto ciò che non è in scope

Vincoli:
  - Naming: PHP snake_case JSON, PascalCase modelli, camelCase metodi
  - IP reale visitatore: leggere CF-Connecting-IP (non REMOTE_ADDR)
  - [altri vincoli specifici del task]
  - NON fare: [cosa evitare esplicitamente]

Output atteso:
  - [ ] percorso/file1.php
  - [ ] percorso/file2.php
  - [ ] percorso/test1.php (se testing)

Git commit atteso:
  <type>(<scope>): <description>
  Esempio: feat(auth): implement single-use JWT validation middleware
```

---

## Vincoli globali del progetto (da allegare a ogni prompt critico)

```yaml
ambiente_locale:
  os: Windows 11
  server: XAMPP (Apache, non artisan serve)
  virtual_host: windeploy.local
  php: 8.3
  mysql: 8.x

ambiente_produzione:
  os: Ubuntu 24 LTS
  server: Nginx
  tunnel: cloudflared (outbound, NON proxy DNS classico)
  tunnel_id: 32e9943d-d2d3-41e1-9776-94a684aaec30
  dominio: windeploy.mavcoo.it
  sottodomini: [api, dev, remote, test]

sicurezza:
  ip_reale_header: CF-Connecting-IP
  auth_web: Laravel Sanctum
  auth_agent: JWT monouso

naming:
  php_json: snake_case
  php_models: PascalCase
  php_methods: camelCase
  python: snake_case (tutto)
  react_components: PascalCase
  react_hooks: camelCase con prefisso "use"

commit_format: "<type>(<scope>): <description>"
commit_types: [feat, fix, refactor, docs, test, chore, security, perf]
```

---

## Log utilizzo AI (da aggiornare manualmente)

| Data       | Fase | AI usata          | Task                        | Esito   | Note                          |
|------------|------|-------------------|-----------------------------|---------|-------------------------------|
| 2026-03-07 | 0A   | Claude Sonnet 4.6 | AI Workspace Init           | ✅ OK   | Struttura repo creata         |
| —          | —    | —                 | —                           | —       | —                             |
