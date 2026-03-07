# WinDeploy — Decisions Log

> ⚠️ Questo file è **append-only**. Non modificare decisioni passate — aggiungere solo nuove voci in fondo.
> Formato data: YYYY-MM-DD

---

### 2026-03-03 — Cloudflared Tunnel come metodo di esposizione produzione

**Contesto:** Il server Ubuntu 24 non ha IP pubblico statico. Serve esporre il backend Laravel e il frontend React su internet senza configurare port-forwarding sul router o acquistare IP statico.

**Opzioni valutate:**
1. **DNS proxy classico (Cloudflare proxy)** — pro: standard, SSL automatico / contro: richiede IP pubblico, non funziona da LAN
2. **Cloudflare Tunnel (cloudflared daemon)** — pro: nessun IP pubblico, connessione outbound, SSL gestito da Cloudflare, tunnel ID stabile / contro: dipendenza da Cloudflare, outbound-only (non adatto a servizi che richiedono inbound raw TCP)
3. **VPN WireGuard** — pro: flessibile / contro: complessità setup, richiede client su ogni device

**Decisione presa:** Cloudflare Tunnel. Il servizio è outbound-only e non richiede apertura porte. Tunnel ID: `32e9943d-d2d3-41e1-9776-94a684aaec30`.

**Impatto:** `deploy/cloudflared/config.yml`, configurazione Nginx backend, documentazione deploy.

**Revisione prevista:** Mai (se la topologia di rete cambia con IP pubblico, rivalutare).

---

### 2026-03-03 — Doppia autenticazione: Sanctum (web SPA) + JWT monouso (agent Windows)

**Contesto:** L'app ha due client con esigenze radicalmente diverse: la SPA React (browser, sessione persistente) e l'agent Python .exe (process Windows, nessun cookie, sessione monouso).

**Opzioni valutate:**
1. **Solo Sanctum** — pro: semplice / contro: l'agent non può gestire cookie SameSite, sessione non termina dopo esecuzione
2. **Solo JWT (tymon/jwt-auth)** — pro: stateless, funziona per agent / contro: per la SPA richiede gestione token in JS (XSS risk se in localStorage)
3. **Sanctum per SPA + JWT monouso per agent** — pro: ogni client usa il meccanismo ottimale, JWT invalidato dopo use_at / contro: due guard da mantenere

**Decisione presa:** Opzione 3. Guard `sanctum` per SPA web, guard `api` (driver: jwt) per agent. Il JWT viene generato una volta sola all'autenticazione agent e invalidato al completamento wizard (campo `used_at`).

**Impatto:** `config/auth.php` (guard `api`), `routes/api.php` (middleware distinti), `backend/app/Http/Controllers/Api/Agent/AgentController.php`.

**Revisione prevista:** Post-MVP se si implementa challenge-response hardware (issue #13).

---

### 2026-03-03 — snake_case PHP per JSON condiviso con agent Python

**Contesto:** Il payload `wizardConfig` è generato dal frontend React (camelCase nativo JS), salvato da Laravel nel DB, e consumato dall'agent Python. Serve una convenzione di naming consistente nel JSON.

**Opzioni valutate:**
1. **camelCase ovunque** — pro: nativo JS / contro: Python convention è snake_case, PHP array di solito snake_case
2. **snake_case ovunque** — pro: nativo Python e PHP, leggibile nel DB JSON / contro: il frontend deve convertire in/out
3. **Frontend camelCase → backend normalizza in snake_case prima di salvare** — pro: ogni layer usa la propria convention / contro: logica di trasformazione duplicata

**Decisione presa:** snake_case per il JSON condiviso (salvato nel DB e inviato all'agent). Il frontend usa camelCase internamente e converte prima del POST. Lo schema ufficiale è documentato in `docs/schemas/wizard-config-schema.json`.

**Impatto:** `frontend/src/` (serialization), `backend/app/Http/Requests/Wizard/StoreWizardRequest.php`, `agent/api_client.py`, `docs/schemas/wizard-config-schema.json`.

**Revisione prevista:** Mai (stabilito con schema JSON formale).

---

### 2026-03-03 — XAMPP per sviluppo locale (non Laravel Sail / Docker)

**Contesto:** Il developer usa Windows 11 con XAMPP già configurato. Serve scegliere l'ambiente di sviluppo locale per Laravel.

**Opzioni valutate:**
1. **Laravel Sail (Docker)** — pro: ambiente identico a produzione, isolamento / contro: Docker Desktop su Windows è pesante, setup iniziale lungo
2. **XAMPP + Virtual Host Apache** — pro: già installato, veloce, familiare / contro: differenze con Nginx in produzione (path, config), non usa PHP-FPM
3. **php artisan serve** — pro: zero config / contro: non gestisce virtual host multipli, problemi con cookie SameSite su localhost

**Decisione presa:** XAMPP con Virtual Host `windeploy.local` (Apache) e `windeploy.local.api` per il backend. Differenze con Nginx produzione sono accettabili per MVP.

**Impatto:** Tutti i path `.htaccess`, `config/cors.php`, `config/sanctum.php` (stateful domains includono `windeploy.local`), documentazione setup.

**Revisione prevista:** Fase 2 — valutare migrazione a Sail per parity produzione.

---

### 2026-03-03 — Soft delete su Wizard e Template (non delete fisico)

**Contesto:** Wizard e Template contengono configurazioni che possono essere referenziate da execution_logs storici. Una delete fisica romperebbe l'integrità dei report passati.

**Opzioni valutate:**
1. **Delete fisico** — pro: semplice, nessun dato residuo / contro: rompe FK con execution_logs, audit trail perso
2. **Soft delete (`deleted_at`)** — pro: dati recuperabili, integrità referenziale mantenuta, audit trail preservato / contro: query richiedono `whereNull('deleted_at')` o scope
3. **Archivio (campo `archived = true`)** — pro: semanticamente diverso da eliminazione / contro: non standard Laravel, richiede scope custom

**Decisione presa:** Soft delete con `SoftDeletes` trait su Model Wizard e Template. Scope `scopeActive()` per escludere i soft-deleted dalle query normali.

**Impatto:** `app/Models/Wizard.php`, `app/Models/Template.php`, migration (aggiungere `$table->softDeletes()`), query nei controller.

**Revisione prevista:** Mai.

---

### 2026-03-03 — AES-256-GCM per cifratura password wizard

**Contesto:** Il wizard include credenziali (password admin locale del PC, password WiFi). Queste vengono salvate nel campo `wizards.configurazione` (JSON nel DB). Se il DB viene compromesso, le password non devono essere in chiaro.

**Opzioni valutate:**
1. **Nessuna cifratura (in chiaro nel JSON)** — pro: zero complessità / contro: leak DB = leak password admin di tutti i PC
2. **Cifratura Laravel `Crypt::encrypt()`** (AES-256-CBC) — pro: built-in Laravel / contro: CBC mode non autentica il ciphertext (vulnerabile a bit-flipping)
3. **AES-256-GCM via `openssl_encrypt()`** — pro: autenticazione integrata (AEAD), IV univoco per ogni encrypt, tag di autenticità / contro: richiede `EncryptionService` custom
4. **Hashing bcrypt** — non applicabile: le password devono essere recuperabili (usate dall'agent per configurare il PC)

**Decisione presa:** AES-256-GCM via `EncryptionService`. Formato salvato: `base64(iv).base64(tag).base64(cipher)`. Chiave derivata da `APP_KEY` in `.env`.

**Impatto:** `app/Services/EncryptionService.php` (da creare, issue #9), `app/Models/Wizard.php` (cast sul campo password), `agent/api_client.py` (non decifra — riceve password già in chiaro dopo decrypt backend nel JWT payload).

**Revisione prevista:** Post-MVP se si implementa key rotation.

---

<!-- NUOVE DECISIONI: aggiungere sotto questa linea -->
