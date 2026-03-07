# WinDeploy — Open Questions

> Le domande risolte vengono spostate nella sezione **Risolte** in fondo.
> Non eliminare domande risolte — documentare la risposta.

---

### [Q-001] Schema wizardConfig: snake_case vs camelCase nel JSON del DB

**Contesto:** Il frontend usa camelCase, Python usa snake_case, PHP usa snake_case per array. Il JSON salvato in `wizards.configurazione` è il contratto tra tutti e tre i moduli.

**Opzioni note:**
- `pcName` (camelCase, nativo JS)
- `pc_name` (snake_case, nativo Python/PHP)
- Mantenere entrambe le forme con trasformazione

**Impatto se non risolta:** Frontend, backend e agent usano chiavi diverse → crash silenzioso dell'agent (`KeyError`), bug difficili da debuggare.

**Assegnata a:** Decisione già presa (snake_case) — manca solo lo schema JSON formale in `docs/schemas/wizard-config-schema.json`

**Priorità:** Alta — Issue #8, #38

**Stato:** ⚠️ Decisione presa (snake_case), ma schema formale non ancora creato

---

### [Q-002] Agent build: PyInstaller vs Nuitka per distribuzione .exe

**Contesto:** L'agent deve essere distribuito come singolo .exe su PC Windows aziendali. Due opzioni principali con trade-off diversi.

**Opzioni note:**
- **PyInstaller 6.x** — standard, ben documentato, ~25-35 MB, compile time 1-2 min, rischio false positive antivirus con UPX
- **Nuitka** — compila in C nativo, ~15-20 MB, +20% runtime performance, compile time 5-10 min, richiede Visual Studio Build Tools

**Impatto se non risolta:** Nessun blocco per MVP — PyInstaller è la scelta di default. Diventa rilevante se dimensione .exe supera 30 MB o se Windows Defender flagga il file.

**Assegnata a:** Marco (valutare post-MVP dopo primo build reale)

**Priorità:** Bassa — Issue #26, #32

**Stato:** 🔵 Aperta, non urgente

---

### [Q-003] Polling monitor vs WebSocket per aggiornamenti real-time

**Contesto:** Il frontend monitor deve mostrare lo stato dell'agent in real-time durante l'esecuzione del wizard. Due approcci possibili.

**Opzioni note:**
- **Polling HTTP ogni 10s** — semplice, già implementato, funziona con proxy/firewall aziendali / contro: latenza fino a 10s, traffico costante
- **WebSocket (Laravel Reverb/Pusher)** — real-time push, nessun polling / contro: infrastruttura aggiuntiva, problemi con alcuni firewall aziendali, complessità
- **Server-Sent Events (SSE)** — push unidirezionale, più semplice di WebSocket / contro: meno supporto librerie Laravel

**Impatto se non risolta:** Il polling da 10s è accettabile per MVP. WebSocket diventerebbe necessario se i clienti richiedono feedback immediato (<2s).

**Assegnata a:** Decisione post-MVP — implementare polling robusto per MVP (issue #24)

**Priorità:** Bassa per MVP, Media per v2.0

**Stato:** 🔵 Aperta, non urgente pre-MVP

---

### [Q-004] Struttura domini produzione: frontend e API sullo stesso dominio o separati?

**Contesto:** Il deploy attuale usa Cloudflare Tunnel con `windeploy.mavcoo.it`. Il frontend React può essere servito:
- Dallo stesso Nginx come static files → nessun CORS
- Da un sottodominio separato (es. `app.windeploy.mavcoo.it`) → richiede CORS configurato

**Opzioni note:**
1. **Stesso dominio** `windeploy.mavcoo.it`: Nginx serve `/` come static React, `/api/*` come proxy PHP-FPM — pro: zero CORS, cookie SameSite funziona / contro: configurazione Nginx più complessa
2. **Sottodomini separati** `windeploy.mavcoo.it` (frontend) + `api.windeploy.mavcoo.it` (backend) — pro: separazione netta / contro: CORS richiesto, cookie SameSite=None+Secure obbligatorio

**Impatto se non risolta:** Blocca configurazione CORS e Sanctum stateful domains (issue #39). La scelta influenza 5+ file di configurazione.

**Assegnata a:** Marco (architettura deploy)

**Priorità:** Alta — blocca issue #39

**Stato:** 🔴 Aperta, urgente prima del primo deploy produzione

---

### [Q-005] Gestione wizard "bruciato" se agent crasha prima di completare

**Contesto:** Il JWT agent è monouso — `used_at` viene settato al primo utilizzo. Se l'agent crasha dopo l'autenticazione ma prima di completare il wizard, il codice non può essere riutilizzato.

**Opzioni note:**
1. **Reset manuale** da dashboard admin — tecnico può resettare `used_at = null` da UI
2. **Finestra di grazia** — se `used_at` è impostato ma non esiste `execution_logs.completed_at` dopo X minuti, il codice può essere riusato
3. **Rigenerazione automatica** — wizard che fallisce genera automaticamente nuovo codice con stessa config

**Impatto se non risolta:** In produzione ogni crash agent richiede intervento manuale del tecnico (o admin) per rigenerare il wizard. Impatto su UX e supporto.

**Assegnata a:** Marco (UX decision) + AI per implementazione

**Priorità:** Media — da risolvere prima del primo deploy con clienti reali

**Stato:** 🟡 Aperta, da risolvere post-MVP blocker

---

### [Q-006] Retention policy report HTML: DB vs file system vs object storage

**Contesto:** I report HTML vengono salvati in `reports.html_content` (LONGTEXT MySQL). Un report completo con step dettagliati può occupare 500KB-2MB. Con clienti reali (100+ wizard/mese), il DB può crescere significativamente.

**Opzioni note:**
1. **LONGTEXT nel DB** (attuale) — semplice, backup incluso nel dump MySQL / contro: query lente con molti report, dimensione DB crescente
2. **File system** `/var/www/windeploy/storage/reports/` — pro: veloce, no peso DB / contro: non incluso nel DB backup, richiede gestione path
3. **Object storage (S3/MinIO)** — pro: scalabile, backup automatico / contro: dipendenza esterna, costi, overkill per MVP
4. **Ibrido**: HTML minificato nel DB, assets (immagini) su storage

**Impatto se non risolta:** Per MVP (< 100 wizard totali) il DB è accettabile. Da rivalutare a 1000+ report.

**Assegnata a:** Architettura da decidere prima dello scale-out

**Priorità:** Bassa per MVP

**Stato:** 🔵 Aperta, non urgente

---

## Domande risolte

| Q-ID | Domanda                                    | Risposta                                              | Data       |
|------|--------------------------------------------|-------------------------------------------------------|------------|
| —    | Quale metodo di tunnel per esposizione?    | Cloudflare Tunnel outbound (non DNS proxy)            | 2026-03-03 |
| —    | Auth SPA vs Auth agent: stesso meccanismo? | No — Sanctum per SPA, JWT monouso per agent           | 2026-03-03 |
| —    | Delete fisica o soft delete su Wizard?     | Soft delete (`SoftDeletes` trait)                     | 2026-03-03 |
| —    | Quale algoritmo per cifratura password?    | AES-256-GCM via `EncryptionService` custom            | 2026-03-03 |

<!-- NUOVE DOMANDE: aggiungere sopra la sezione Risolte -->
