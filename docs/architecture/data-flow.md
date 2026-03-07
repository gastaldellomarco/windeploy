# WinDeploy — Data Flow

> Flussi dati completi per i 3 scenari principali del sistema.

---

## SCENARIO A — Creazione Wizard (Web App)

### A.1 Diagramma Flusso

```
  [Tecnico Browser]
        │
        │  Compila 8 step del wizard form
        │  React state via useReducer (useWizard hook)
        │
        │  Step 1: Info PC (nome, dominio)
        │  Step 2: Account amministratore
        │  Step 3: Software da installare
        │  Step 4: Software da rimuovere
        │  Step 5: Configurazioni OS
        │  Step 6: Power plan + RDP
        │  Step 7: Utenti locali da creare
        │  Step 8: Riepilogo + conferma
        │
        │  [Step 8] POST /api/wizards
        │  Content-Type: application/json
        │  Authorization: Bearer <sanctum_token>
        │  Body: {configurazione JSON completa 8 step}
        ▼
  [StoreWizardRequest]
        │
        ├─ Valida tutti i campi (rules() method)
        ├─ Se fallisce → 422 {errors: {campo: ["msg"]}}
        │
        ▼
  [WizardController::store()]
        │
        ├─ Inietta StoreWizardRequest (già validata)
        ├─ Chiama EncryptionService::encrypt($adminPassword)
        │    → AES-256-GCM, key da APP_KEY
        │    → output: {encrypted, iv, tag}
        │
        ├─ Chiama WizardCodeService::generate()
        │    → formato: WD-XXXX (4 char alfanumerici uppercase)
        │    → verifica unicità in DB (retry se collisione)
        │
        ├─ Wizard::create({
        │    tecnico_id: auth()->id(),
        │    codice_univoco: 'WD-XXXX',
        │    configurazione: $validatedData (JSON cast),
        │    password_admin_cifrata: $encrypted,
        │    stato: 'pronto',
        │    expires_at: now()->addHours(24)
        │  })
        │
        ▼
  [Response 201]
        │  {
        │    data: {
        │      id: 42,
        │      codice_univoco: "WD-K7M2",
        │      expires_at: "2026-03-08T22:53:00Z"
        │    },
        │    message: "Wizard creato con successo"
        │  }
        ▼
  [React Frontend]
        │
        └─ Aggiorna wizardStore (Zustand)
        └─ Mostra Modal con:
             • Codice univoco WD-XXXX
             • QR code (opzionale)
             • Link download WinDeployAgent.exe
             • Scadenza: 24h
```

### A.2 Schema DB — Tabella wizards

```sql
CREATE TABLE wizards (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tecnico_id      BIGINT UNSIGNED NOT NULL,  -- FK users.id
    codice_univoco  VARCHAR(10) UNIQUE NOT NULL,  -- WD-XXXX
    configurazione  JSON NOT NULL,
    password_admin_cifrata JSON NULL,  -- {encrypted, iv, tag}
    stato           ENUM('pronto','in_corso','completato','errore','scaduto'),
    expires_at      TIMESTAMP NOT NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

---

## SCENARIO B — Esecuzione Agent

### B.1 Diagramma Flusso Completo

```
  [Tecnico avvia WinDeployAgent.exe]
        │
        │  GUI CustomTkinter: schermata inserimento codice
        │
        │  Inserisce codice WD-XXXX
        │
        │  POST /api/agent/auth
        │  {"codice": "WD-K7M2"}
        ▼
  [AgentController::auth()]
        │
        ├─ Wizard::where('codice_univoco', $codice)->first()
        ├─ Se non trovato → 404
        ├─ Se stato != 'pronto' → 422 {message: "Wizard non disponibile"}
        ├─ Se expires_at < now() → 422 {message: "Codice scaduto"}
        │
        ├─ AgentAuthService::issueToken($wizard)
        │    → JWTAuth::fromUser($wizard->tecnico)
        │    → payload: {sub, wizard_id, exp: +4h}
        │
        ├─ Decifra password_admin con EncryptionService::decrypt()
        │
        ▼
  [Response 200]
        │  {
        │    data: {
        │      jwt: "eyJhbGc...",
        │      config: { /* configurazione completa wizard */ },
        │      pc_name: "WINDEPLOY-PC-01",
        │      admin_password: "<decifrata per uso locale>"
        │    }
        │  }
        ▼
  [Agent: GUI mostra riepilogo configurazione]
        │
        ├─ Lista software da installare
        ├─ Lista software da rimuovere
        ├─ Configurazioni OS pianificate
        │
        │  Tecnico: [CONFERMA]
        ▼
  [Agent: Scanner]
        │  scanner.py legge registro Windows:
        │  HKLM\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall
        │  HKCU\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall
        │  + winget list --output json
        │
        │  GUI: lista app installate con checkbox
        │  Tecnico seleziona app da rimuovere
        ▼
  [Agent: Avvio esecuzione]
        │
        │  POST /api/agent/start
        │  Authorization: Bearer <jwt>
        │  {"wizard_id": 42, "hostname": "...", "os_version": "..."}
        ▼
  [Laravel: crea execution_log]
        │  ExecutionLog::create({
        │    wizard_id: 42,
        │    steps_json: [],
        │    hardware_info: {...}
        │  })
        │  Wizard::update(['stato' => 'in_corso', 'started_at' => now()])
        ▼
  [Agent: esegue step per step]
        │
        │  Per ogni azione (installazione/rimozione/config):
        │  ├─ Esegue operazione OS (winget/PowerShell)
        │  ├─ Cattura output + exit code
        │  │
        │  │  POST /api/agent/step
        │  │  Authorization: Bearer <jwt>
        │  │  {
        │  │    "wizard_id": 42,
        │  │    "step_number": 3,
        │  │    "azione": "uninstall",
        │  │    "target": "Bloatware XY",
        │  │    "stato": "completato",  // o "errore"
        │  │    "output": "Successfully uninstalled...",
        │  │    "durata_ms": 1240
        │  │  }
        │  │
        │  └─ Backend: appende step al JSON del execution_log
        │
        │  [Contemporaneamente — Frontend polling]
        │  GET /api/wizards/42/monitor ogni 5s
        │  → React aggiorna UI in real-time
        ▼
  [Agent: completamento]
        │
        │  report_gen.py genera HTML standalone
        │  (Jinja2 template + assets base64 embedded)
        │
        │  POST /api/agent/complete
        │  Authorization: Bearer <jwt>
        │  {
        │    "wizard_id": 42,
        │    "report_html": "<base64>",
        │    "stato_finale": "completato"
        │  }
        ▼
  [Laravel: AgentController::complete()]
        │
        ├─ Salva report HTML su storage (disk: local o s3)
        ├─ Wizard::update({
        │    stato: 'completato',
        │    completed_at: now()
        │  })
        ├─ Invalida JWT (revoca in DB)
        ▼
  [Frontend]
        └─ useMonitor rileva stato='completato'
        └─ Stop polling
        └─ Mostra bottone "Scarica Report"
```

### B.2 Schema DB — Tabella execution_logs

```sql
CREATE TABLE execution_logs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wizard_id     BIGINT UNSIGNED NOT NULL UNIQUE,  -- FK wizards.id
    hardware_info JSON NULL,   -- CPU, RAM, disco, OS version
    steps_json    JSON NOT NULL DEFAULT '[]',  -- array step appended
    report_path   VARCHAR(512) NULL,  -- path storage report HTML
    created_at    TIMESTAMP,
    updated_at    TIMESTAMP
);

-- Struttura di un singolo step nel JSON:
-- {
--   "step_number": 3,
--   "azione": "uninstall",
--   "target": "Bloatware XY",
--   "stato": "completato",
--   "output": "...",
--   "durata_ms": 1240,
--   "timestamp": "2026-03-07T22:53:00Z"
-- }
```

---

## SCENARIO C — Monitoring Real-Time (Web App)

### C.1 Diagramma Flusso

```
  [Tecnico apre /wizards/42/monitor]
        │
        │  WizardMonitorPage monta
        │  useMonitor(42) hook attivato
        ▼
  [useMonitor hook]
        │
        │  setInterval ogni 5000ms:
        │  ├─ GET /api/wizards/42/monitor
        │  │  Authorization: Bearer <sanctum_token>
        │  │
        │  │  Se response.data.stato === 'completato':
        │  │  └─ clearInterval() → stop polling
        │  │
        │  │  Se 401/403: → logout + redirect /login
        │  │
        │  └─ Aggiorna state locale del hook
        ▼
  [WizardController::monitor()]
        │
        │  $wizard = Wizard::with('executionLog')->find(42)
        │
        │  WizardMonitorService::buildMonitorData($wizard)
        │  ├─ legge steps_json da execution_log
        │  ├─ calcola progresso:
        │  │    step_completati = count(steps dove stato='completato')
        │  │    progresso = step_completati / 8 * 100
        │  ├─ estrae hardware_info
        │  └─ prepara last_step (ultima azione eseguita)
        ▼
  [Response 200]
        │  {
        │    data: {
        │      stato: "in_corso",
        │      progresso: 62,
        │      step_corrente: 5,
        │      step_completati: [
        │        {step_number: 1, azione: "rename_pc", stato: "completato"},
        │        ...
        │      ],
        │      hardware_info: {
        │        cpu: "Intel i5-12400",
        │        ram_gb: 16,
        │        os: "Windows 11 Pro 23H2"
        │      },
        │      started_at: "2026-03-07T22:40:00Z"
        │    }
        │  }
        ▼
  [React: WizardMonitorPage aggiorna UI]
        │
        ├─ ProgressBar: 62%
        ├─ Step icons: ✅✅✅✅✅⏳⭕⭕
        ├─ Hardware info panel
        ├─ Live log: ultime 10 righe output
        │
        │  [Quando stato='completato']
        ├─ clearInterval()
        ├─ Step icons: ✅✅✅✅✅✅✅✅
        ├─ ProgressBar: 100%
        └─ Bottone: [📄 Scarica Report HTML]
```

### C.2 Considerazioni Performance Polling

| Aspetto | Scelta | Motivazione |
|---|---|---|
| **Frequenza** | 5 secondi | Bilanciamento UX vs carico server |
| **Alternativa** | WebSocket / SSE | Più efficiente ma più complessa; da valutare in v2 |
| **Timeout** | Stop a `completato` o `errore` | Evita polling infinito |
| **Max durata** | 4 ore (= JWT lifetime) | Allineato con scadenza agent |
| **Caching** | Nessuno (dati real-time) | Cache su monitor endpoint non applicabile |

---

## Modello Dati — Relazioni principali

```
  users
   │  id, name, email, password, remember_token
   │  ← (spatie) model_has_roles → roles
   │
   └──< wizards (tecnico_id → users.id)
          │  id, codice_univoco, configurazione(JSON)
          │  stato, expires_at, started_at, completed_at
          │
          └──1 execution_logs (wizard_id)
                id, hardware_info(JSON), steps_json(JSON)
                report_path
```
