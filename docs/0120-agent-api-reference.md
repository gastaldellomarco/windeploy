# 🤖 Agent API Reference — Endpoint WinDeploy

**Data aggiornamento:** 2026-03-06  
**Versione schema:** 1.0  
**Stack:** Laravel 11 (PHP 8.3) | Python 3.11 agent

---

## Panoramica

L'agent Windows (.exe Python) comunica con il backend tramite 4 endpoint REST REST. Usa autenticazione **JWT monouso** per la sessione di configurazione.

**Base URL (dev locale):** `http://windeploy.local/api/agent`  
**Base URL (produzione):** `https://windeploy.tuodominio.com/api/agent`

**Rate limiting:** 120 richieste/minuto per token JWT (o per IP se non autenticato).

---

## Contratto dati

Tutti gli endpoint usano il schema canonico definito in:  
📄 **`docs/schemas/wizard-config.schema.json`**  
📋 **Esempio:** `docs/schemas/wizard-config-example.json`

---

## Endpoint 1: POST /api/agent/auth

**Autenticazione della sessione wizard.**

Riceve il codice univoco del wizard e l'indirizzo MAC del PC. Restituisce un token JWT valido per 4 ore e la configurazione completa.

### Request

```http
POST /api/agent/auth HTTP/1.1
Host: windeploy.local
Content-Type: application/json
Accept: application/json

{
  "codice_wizard": "WD-0A1B2C3D",
  "mac_address": "00:1A:2B:3C:4D:5E"
}
```

**Parametri:**

| Campo           | Tipo   | Descrizione               | Vincoli                                 |
| --------------- | ------ | ------------------------- | --------------------------------------- |
| `codice_wizard` | string | Codice univoco del wizard | Pattern: `WD-[A-F0-9]{8}`               |
| `mac_address`   | string | Indirizzo MAC del PC      | Pattern: `([0-9A-F]{2}:){5}[0-9A-F]{2}` |

### Response — 200 OK

```json
{
  "success": true,
  "message": "Autenticazione riuscita.",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_expires_at": "2026-03-06T16:30:00Z",
    "wizard_code": "WD-0A1B2C3D",
    "wizard_config": {
      "version": "1.0",
      "pc_name": "PC-UFFICIO-01",
      "admin_user": { ... },
      "software": [ ... ],
      "bloatware": [ ... ],
      "power_plan": { ... },
      "extras": { ... }
    }
  }
}
```

**Field Details:**

| Campo              | Tipo    | Descrizione                                                                                 |
| ------------------ | ------- | ------------------------------------------------------------------------------------------- |
| `token`            | string  | Bearer token JWT. Usare in header `Authorization: Bearer <token>` per richieste successive. |
| `token_expires_at` | ISO8601 | Data/ora scadenza token (UTC).                                                              |
| `wizard_code`      | string  | Codice wizard autorizzato (per riferimento agente).                                         |
| `wizard_config`    | object  | Schema canonico WizardConfig v1.0. Vedi `docs/schemas/wizard-config.schema.json`.           |

### Response — 422 Unprocessable Entity

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "codice_wizard": [
      "The codice wizard field must be 12 characters.",
      "The codice wizard format is invalid."
    ],
    "mac_address": ["The mac address field is required."]
  }
}
```

### Response — 404 Not Found

```json
{
  "error": "Wizard non trovato o già scaduto."
}
```

### Response — 409 Conflict

```json
{
  "error": "Questo wizard è già stato utilizzato (già eseguito su un PC)."
}
```

---

## Endpoint 2: POST /api/agent/start

**Avvia l'esecuzione del wizard sul PC.**

L'agent segnala l'inizio dell'installazione. Il backend crea un `execution_log`.

### Request

```http
POST /api/agent/start HTTP/1.1
Host: windeploy.local
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "wizard_code": "WD-0A1B2C3D",
  "pc_info": {
    "hostname": "PC-UFFICIO-01",
    "os_version": "Windows 11 Build 22621",
    "total_disk_gb": 500,
    "ram_gb": 16,
    "cpu_cores": 8
  }
}
```

**Parametri:**

| Campo                   | Tipo    | Descrizione                                          |
| ----------------------- | ------- | ---------------------------------------------------- |
| `wizard_code`           | string  | Codice wizard (da /auth response).                   |
| `pc_info`               | object  | Info hw del PC (metadata, opzionale ma consigliato). |
| `pc_info.hostname`      | string  | Nome hostname del PC.                                |
| `pc_info.os_version`    | string  | Versione Windows (es: "Windows 11 Build 22621").     |
| `pc_info.total_disk_gb` | integer | Spazio disco totale in GB.                           |
| `pc_info.ram_gb`        | integer | RAM in GB.                                           |
| `pc_info.cpu_cores`     | integer | Numero core CPU.                                     |

### Response — 200 OK

```json
{
  "success": true,
  "message": "Esecuzione avviata.",
  "data": {
    "execution_log_id": 42,
    "started_at": "2026-03-06T12:30:45Z"
  }
}
```

### Response — 401 Unauthorized

```json
{
  "message": "Token JWT non valido, scaduto o revocato."
}
```

---

## Endpoint 3: POST /api/agent/step

**Registra un passo intermedio dell'esecuzione.**

L'agent invia uno step completato, con stato, messaggio e progresso (0-100%).  
Fire-and-forget: il backend non solleva eccezioni anche se offline; l'agent continua.

### Request

```http
POST /api/agent/step HTTP/1.1
Host: windeploy.local
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "wizard_code": "WD-0A1B2C3D",
  "step": "software_installation",
  "status": "in_progress",
  "message": "Installando Microsoft PowerToys...",
  "progress": 45,
  "timestamp": "2026-03-06T12:35:20.000Z"
}
```

**Parametri:**

| Campo         | Tipo    | Descrizione                           | Valori                                                                                                  |
| ------------- | ------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `wizard_code` | string  | Codice wizard.                        | —                                                                                                       |
| `step`        | string  | Nome dello step.                      | `software_installation`, `bloatware_removal`, `power_plan_config`, `extras_apply`, `execution_complete` |
| `status`      | string  | Stato.                                | `pending`, `in_progress`, `completed`, `error`                                                          |
| `message`     | string  | Messaggio descrittivo (max 500 char). | —                                                                                                       |
| `progress`    | integer | Percentuale avanzamento (0-100).      | 0–100                                                                                                   |
| `timestamp`   | ISO8601 | Data/ora step (UTC).                  | —                                                                                                       |

### Response — 200 OK

```json
{
  "success": true,
  "message": "Step registrato."
}
```

### Response — 422 Unprocessable Entity

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "The status field must be: pending, in_progress, completed, error."
    ],
    "progress": ["The progress must be between 0 and 100."]
  }
}
```

---

## Endpoint 4: POST /api/agent/complete

**Segnala il completamento dell'esecuzione.**

Chiude l'`execution_log`, genera il report e invalida il token JWT.

### Request

```http
POST /api/agent/complete HTTP/1.1
Host: windeploy.local
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "wizard_code": "WD-0A1B2C3D",
  "success": true,
  "steps_ok": 8,
  "steps_failed": 0,
  "total_duration_seconds": 1245,
  "report_data": {
    "installed_software": ["Microsoft.PowerToys", "7zip.7zip"],
    "removed_bloatware": ["Microsoft.XboxApp", "Microsoft.Teams"],
    "system_changes": {
      "power_plan_applied": "high_performance",
      "timezone_changed": "Europe/Rome",
      "windows_update_enabled": true
    }
  }
}
```

**Parametri:**

| Campo                    | Tipo    | Descrizione                              |
| ------------------------ | ------- | ---------------------------------------- |
| `wizard_code`            | string  | Codice wizard.                           |
| `success`                | boolean | true = completamento OK, false = errore. |
| `steps_ok`               | integer | Numero step completati con successo.     |
| `steps_failed`           | integer | Numero step falliti.                     |
| `total_duration_seconds` | integer | Durata totale esecuzione in secondi.     |
| `report_data`            | object  | Dati opzionali per il report HTML.       |

### Response — 200 OK

```json
{
  "success": true,
  "message": "Esecuzione completata. Report generato.",
  "data": {
    "report_url": "/reports/WD-0A1B2C3D-2026-03-06.html",
    "execution_log_id": 42
  }
}
```

⚠️ **Nota:** Dopo questa risposta, il token JWT viene invalidato. Qualsiasi richiesta successiva riceverà 401 Unauthorized.

---

## Errori Comuni e Troubleshooting

### ❌ 422 — "The codice wizard field is required"

**Causa:** Il campo `codice_wizard` è vuoto, mancante o malformato.  
**Fix:**

- Verifica che il wizard code abbia pattern `WD-XXXXXXXX` (8 hex digits dopo WD-)
- Controlla che le variabili d'ambiente / configurazione forniscano il codice correttamente
- In dev: usa il codice di test da AdminUserSeeder (es: `WD-ABCD1234`)

### ❌ 401 — "Token JWT non valido"

**Causa:** Token scaduto (>4h), revocato o malformato.  
**Fix:**

- Riautenticare con POST /agent/auth per ottenere un nuovo token
- Includere il Bearer token correttamente: `Authorization: Bearer <token>`
- Non dimenticare il prefix `Bearer` nello header

### ❌ 409 — "Wizard già utilizzato"

**Causa:** Questo wizard code è stato già completato su un altro PC (idempotency check).  
**Fix:**

- Creare un nuovo wizard dal builder Web
- Non riutilizzare wizard code già eseguiti

### ❌ 429 — "Too Many Requests"

**Causa:** Rate limit exceeded (>120 req/min).  
**Fix:**

- Attendere qualche minuto prima di riprovare
- Distribuire le richieste nel tempo (batch processing)
- Verificare che l'agent non entri in loop di retry

---

## Flusso Completo (Happy Path)

```
1. POST /api/agent/auth
   ↓ (ricevi token + wizard_config)
2. POST /api/agent/start
   ↓ (avvia esecuzione)
3. POST /api/agent/step (multiple)
   ↓ (software installation, bloatware removal, power plan, extras)
4. POST /api/agent/complete
   ↓ (chiudi session, invalida token)
   ✅ Fine
```

---

## Header Richiesti

Tutti gli endpoint (tranne /auth) richiedono:

```http
Authorization: Bearer <jwt_token>
Content-Type: application/json
Accept: application/json
```

---

## Rate Limiting

| Endpoint             | Limite                | Reset      |
| -------------------- | --------------------- | ---------- |
| POST /agent/auth     | 10 req/min per IP     | 60 secondi |
| POST /agent/start    | 120 req/min per token | 60 secondi |
| POST /agent/step     | 120 req/min per token | 60 secondi |
| POST /agent/complete | 120 req/min per token | 60 secondi |

---

## Sicurezza

- ✅ **HTTPS obbligatorio** in produzione (Cloudflare Tunnel certificate)
- ✅ **JWT a breve scadenza** (4h max, invalida al complete)
- ✅ **MAC address nel JWT** per protezione anti-replay
- ✅ **Password sempre cifrate** (AES-256-GCM) nel transito
- ⚠️ **CORS:** agent (localhost:8000) può fare richieste a windeploy.local

---

## Referenze

- Schema dati: `docs/schemas/wizard-config.schema.json`
- Esempio payload: `docs/schemas/wizard-config-example.json`
- Agent Python: `agent/api_client.py` (wrapper client)
- Backend Laravel: `backend/routes/api.php` (route definitions)

---

**[Modifiche apportate: 1 sezione creata, 4 endpoint documentati, flusso completo + troubleshooting]**
