# WinDeploy — Naming Conventions

> Versione: 1.0.0 | Aggiornato: 2026-03-07

Questo documento è la **fonte di verità** per tutte le convenzioni di naming nel progetto WinDeploy.
Ogni PR che introduce nuovi file, classi, componenti o endpoint deve rispettare queste regole.

---

## PHP / Laravel 11

### Modelli Eloquent
- **PascalCase singolare**
- Il nome corrisponde esattamente alla tabella plurale snake_case (Laravel convention)
- File in: `backend/app/Models/`

```php
// ✅ Corretto
Wizard.php           → tabella: wizards
ExecutionLog.php     → tabella: execution_logs
SoftwareLibrary.php  → tabella: software_libraries
AgentSession.php     → tabella: agent_sessions
User.php             → tabella: users

// ❌ Errato
wizard.php
Wizards.php
execution_log.php
```

### Controller
- **PascalCase + suffisso `Controller`**
- File in: `backend/app/Http/Controllers/`
- Un controller per risorsa, usa Resource Controllers quando possibile

```php
// ✅ Corretto
WizardController.php
ExecutionLogController.php
SoftwareLibraryController.php
AgentAuthController.php
AuthController.php

// ❌ Errato
wizardController.php
WizardCtrl.php
WizardAPI.php
```

### Service
- **PascalCase + suffisso `Service`**
- File in: `backend/app/Services/`
- Contiene la business logic, non interagisce direttamente con HTTP

```php
// ✅ Corretto
WizardCodeService.php
SoftwareResolverService.php
ExecutionPlanService.php
AgentTokenService.php
```

### Form Request
- **PascalCase + suffisso `Request`**
- File in: `backend/app/Http/Requests/`

```php
StoreWizardRequest.php
UpdateWizardRequest.php
AgentLoginRequest.php
```

### Resource (API)
- **PascalCase + suffisso `Resource`**
- File in: `backend/app/Http/Resources/`

```php
WizardResource.php
WizardCollection.php
ExecutionLogResource.php
```

### Migration
- **snake_case con timestamp Laravel** (generato automaticamente da `artisan`)
- Formato: `YYYY_MM_DD_HHMMSS_<verb>_<tabella>_table`

```
// ✅ Corretto
2024_01_01_000001_create_wizards_table.php
2024_01_15_102300_create_execution_logs_table.php
2024_02_10_083000_add_status_to_wizards_table.php
2024_03_05_140000_create_agent_sessions_table.php

// ❌ Errato
create_wizards.php
20240101_wizards.php
```

### Route
- **kebab-case plurale** per le risorse
- Prefisso `/api/` gestito dal file `routes/api.php`
- Parametri route: camelCase o snake_case accettati, preferire camelCase

```php
// ✅ Corretto
GET    /api/wizards
POST   /api/wizards
GET    /api/wizards/{wizardId}
DELETE /api/wizards/{wizardId}
GET    /api/software-library
GET    /api/execution-logs
POST   /api/agent/authenticate

// ❌ Errato
/api/Wizards
/api/wizard
/api/getWizards
/api/software_library
```

### Variabili e Metodi PHP
- **camelCase** per variabili, metodi, proprietà

```php
// ✅ Corretto
$wizardCode = '...';
$executionLog = ExecutionLog::find($id);
public function generateCode(): string { ... }
private function validateUniqueness(string $code): bool { ... }

// ❌ Errato
$WizardCode
$wizard_code
public function generate_code()
```

### Costanti PHP
- **UPPER_SNAKE_CASE**

```php
const MAX_WIZARD_STEPS = 10;
const AGENT_TOKEN_TTL = 3600;
const EXECUTION_STATUS_PENDING = 'pending';
```

---

## JavaScript / React 18

### Componenti React
- **PascalCase**
- Un file per componente
- File in: `frontend/src/components/<NomeComponente>.jsx` o `frontend/src/pages/<NomePagina>/index.jsx`

```jsx
// ✅ Corretto
WizardBuilder.jsx
StepProgress.jsx
SoftwareLibraryTable.jsx
ExecutionLogViewer.jsx
AgentStatusBadge.jsx

// ❌ Errato
wizardBuilder.jsx
wizard-builder.jsx
Wizardbuilder.jsx
```

### Hook Personalizzati
- **camelCase con prefisso `use`**
- File in: `frontend/src/hooks/`

```jsx
// ✅ Corretto
useWizard.js
useMonitor.js
useAgentStatus.js
useExecutionLog.js
useAuth.js

// ❌ Errato
wizardHook.js
WizardHook.js
hook-wizard.js
```

### Store Zustand
- **camelCase + suffisso `Store`**
- File in: `frontend/src/stores/`

```jsx
// ✅ Corretto
authStore.js
wizardStore.js
monitorStore.js
softwareLibraryStore.js

// ❌ Errato
AuthStore.js
auth-store.js
useAuthStore.js   // no prefisso use per gli store
```

### Pagine
- Cartella **PascalCase** + file `index.jsx` dentro
- File in: `frontend/src/pages/`

```
frontend/src/pages/
  Dashboard/
    index.jsx
  WizardBuilder/
    index.jsx
  SoftwareLibrary/
    index.jsx
  ExecutionLog/
    index.jsx
  Login/
    index.jsx
```

### CSS / Stile
- **Tailwind utility classes** — nessun file CSS custom se possibile
- Se necessario un file CSS specifico: `NomeComponente.module.css` (CSS Modules)
- Nessun stile inline se evitabile
- Nessuna libreria CSS aggiuntiva senza approvazione architetturale

### Variabili JavaScript
- **camelCase** per variabili e funzioni
- **UPPER_SNAKE_CASE** per costanti modulo

```jsx
// ✅ Corretto
const wizardData = await fetchWizard(id);
const API_BASE_URL = import.meta.env.VITE_API_URL;
const isLoading = true;
```

---

## Python 3.11 (Agent)

### Classi
- **PascalCase**
- File in: `agent/src/`

```python
# ✅ Corretto
class ApiClient: ...
class SystemScanner: ...
class ExecutionEngine: ...
class WizardParser: ...
class ConfigManager: ...

# ❌ Errato
class apiClient: ...
class API_Client: ...
class Apiclient: ...
```

### Funzioni e Metodi
- **snake_case**

```python
# ✅ Corretto
def get_installed_apps() -> list: ...
def send_execution_result(payload: dict) -> bool: ...
def validate_jwt_token(token: str) -> bool: ...

# ❌ Errato
def getInstalledApps(): ...
def SendExecutionResult(): ...
```

### Costanti
- **UPPER_SNAKE_CASE** a livello modulo

```python
# ✅ Corretto — in agent/src/config.py
API_URL = "https://api.windeploy.mavcoo.it"
SSL_VERIFY = True
AGENT_VERSION = "1.0.0"
MAX_RETRY_ATTEMPTS = 3
REQUEST_TIMEOUT = 30
```

### File e Moduli
- **snake_case**
- Nomi descrittivi e specifici

```
# ✅ Corretto
api_client.py
system_config.py
system_scanner.py
execution_engine.py
wizard_parser.py
gui_main.py
build_config.py

# ❌ Errato
ApiClient.py
apiClient.py
client.py          # troppo generico
```

---

## JSON Payload (condiviso tra tutti i moduli)

> I payload JSON rappresentano il contratto tra Backend, Frontend e Agent.
> La consistenza è critica per evitare bug silenti.

### Chiavi
- **snake_case** obbligatorio per tutte le chiavi

```json
// ✅ Corretto
{
  "pc_name": "WORKSTATION-01",
  "admin_user": "marco",
  "software_install": ["7zip", "vscode"],
  "execution_status": "pending",
  "agent_version": "1.0.0",
  "created_at": "2024-01-15T10:30:00Z",
  "is_active": true,
  "retry_count": 0
}

// ❌ Errato
{
  "pcName": "...",       // camelCase ❌
  "AdminUser": "...",    // PascalCase ❌
  "is_active": 1,        // intero invece di booleano ❌
  "created_at": "15/01/2024" // formato data non ISO ❌
}
```

### Booleani
- **`true` / `false`** (JSON nativo) — mai `1` / `0`
- In PHP: `(bool)` cast prima della serializzazione
- In Python: `True`/`False` vengono serializzati correttamente da `json.dumps()`

### Date
- **ISO 8601** con timezone UTC: `2024-01-15T10:30:00Z`
- In Laravel: `$model->toArray()` serializza `Carbon` automaticamente se nel cast `datetime`
- In Python: `datetime.utcnow().strftime('%Y-%m-%dT%H:%M:%SZ')`
- In JavaScript: `new Date().toISOString()`

---

## Struttura File Progetto (Riferimento)

```
windeploy/
├── backend/         # Laravel 11 — app/, routes/, database/, config/
├── frontend/        # React 18 + Vite — src/components/, src/pages/, src/hooks/
├── agent/           # Python 3.11 — src/, build/
├── docs/            # Documentazione tecnica e operativa
├── ai/              # Workspace AI (non deployato)
├── .github/         # CI/CD workflows, templates issue/PR
└── VERSION          # Versione corrente del progetto
```
