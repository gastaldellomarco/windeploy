# WinDeploy — API Design

> Convenzioni REST, contratti di risposta e catalogo completo degli endpoint.

---

## Convenzioni Generali

| Proprietà | Valore |
|---|---|
| **Base URL (prod)** | `https://api.windeploy.mavcoo.it/api` |
| **Base URL (locale)** | `http://windeploy.local/api` |
| **Versioning** | Nessuno per ora; prefisso `/v1/` quando breaking change |
| **Content-Type** | `application/json` (sempre, sia request che response) |
| **Autenticazione** | `Authorization: Bearer <token>` |
| **Charset** | UTF-8 |
| **Date format** | ISO 8601: `2026-03-07T22:53:00Z` |
| **Naming chiavi JSON** | `snake_case` |

---

## Response Envelope

### Success (con dati)
```json
{
  "data": { ... },
  "message": "Operazione completata con successo"
}
```

### Success (lista paginata)
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73,
    "from": 1,
    "to": 15
  },
  "message": null
}
```

### Success (no content)
```
HTTP 204 No Content
(body vuoto)
```

### Error (validazione)
```json
{
  "message": "I dati forniti non sono validi.",
  "errors": {
    "email": ["Il campo email è obbligatorio."],
    "configurazione.pc_name": ["Il nome PC non può superare 15 caratteri."]
  }
}
```

### Error (generico)
```json
{
  "message": "Descrizione errore human-readable",
  "errors": null
}
```

---

## HTTP Status Codes

| Codice | Quando si usa |
|---|---|
| `200 OK` | GET/PUT/PATCH con dati in risposta |
| `201 Created` | POST che crea una risorsa |
| `204 No Content` | DELETE o azioni senza body di ritorno |
| `400 Bad Request` | Richiesta malformata (JSON non valido, ecc.) |
| `401 Unauthorized` | Token mancante o non valido |
| `403 Forbidden` | Token valido ma ruolo insufficiente |
| `404 Not Found` | Risorsa non trovata |
| `422 Unprocessable Entity` | Validazione fallita (FormRequest) |
| `429 Too Many Requests` | Rate limit superato |
| `500 Internal Server Error` | Errore non gestito lato server |

---

## Catalogo Endpoint

### Autenticazione Web (Sanctum)

| Method | Path | Auth | Ruolo min | Descrizione |
|---|---|---|---|---|
| `POST` | `/api/auth/login` | ❌ | — | Login utente, ritorna Sanctum token |
| `POST` | `/api/auth/logout` | ✅ Sanctum | any | Revoca token corrente |
| `GET` | `/api/auth/me` | ✅ Sanctum | any | Dati utente autenticato + ruoli |
| `POST` | `/api/auth/refresh` | ✅ Sanctum | any | Rinnova token Sanctum |

### Wizard (Web App)

| Method | Path | Auth | Ruolo min | Descrizione |
|---|---|---|---|---|
| `GET` | `/api/wizards` | ✅ Sanctum | tecnico | Lista wizard (paginata, filtri: stato, data) |
| `POST` | `/api/wizards` | ✅ Sanctum | tecnico | Crea nuovo wizard (8 step config) |
| `GET` | `/api/wizards/{id}` | ✅ Sanctum | tecnico | Dettaglio wizard (proprio o admin) |
| `PUT` | `/api/wizards/{id}` | ✅ Sanctum | tecnico | Aggiorna wizard (solo stato=pronto) |
| `DELETE` | `/api/wizards/{id}` | ✅ Sanctum | admin | Elimina wizard |
| `GET` | `/api/wizards/{id}/monitor` | ✅ Sanctum | tecnico | Dati real-time per polling monitor |
| `GET` | `/api/wizards/{id}/report` | ✅ Sanctum | tecnico | Scarica/visualizza report HTML esecuzione |
| `POST` | `/api/wizards/{id}/expire` | ✅ Sanctum | admin | Forza scadenza wizard |

### Utenti e Ruoli (Admin)

| Method | Path | Auth | Ruolo min | Descrizione |
|---|---|---|---|---|
| `GET` | `/api/users` | ✅ Sanctum | admin | Lista utenti (paginata) |
| `POST` | `/api/users` | ✅ Sanctum | admin | Crea nuovo utente |
| `GET` | `/api/users/{id}` | ✅ Sanctum | admin | Dettaglio utente |
| `PUT` | `/api/users/{id}` | ✅ Sanctum | admin | Aggiorna utente (nome, email, ruolo) |
| `DELETE` | `/api/users/{id}` | ✅ Sanctum | admin | Elimina utente |
| `PUT` | `/api/users/{id}/password` | ✅ Sanctum | admin | Reset password utente |

### Agent (JWT Guard)

| Method | Path | Auth | Ruolo min | Descrizione |
|---|---|---|---|---|
| `POST` | `/api/agent/auth` | ❌ | — | Verifica codice WD-XXXX, emette JWT + config |
| `POST` | `/api/agent/start` | ✅ JWT | — | Avvia esecuzione, crea execution_log |
| `POST` | `/api/agent/step` | ✅ JWT | — | Appende singolo step al log JSON |
| `POST` | `/api/agent/complete` | ✅ JWT | — | Chiude esecuzione, salva report HTML |
| `POST` | `/api/agent/error` | ✅ JWT | — | Segnala errore fatale, aggiorna stato=errore |

---

## Dettaglio Request/Response per Endpoint Chiave

### POST /api/auth/login

**Request:**
```json
{
  "email": "tecnico@azienda.it",
  "password": "password123"
}
```

**Response 200:**
```json
{
  "data": {
    "token": "1|Abc123XyzToken...",
    "token_type": "Bearer",
    "user": {
      "id": 7,
      "name": "Marco Tecnico",
      "email": "tecnico@azienda.it",
      "ruolo": "tecnico"
    }
  },
  "message": "Login effettuato"
}
```

---

### POST /api/wizards

**Request:**
```json
{
  "pc_name": "UFFICIO-PC-01",
  "dominio": null,
  "admin_username": "adminlocale",
  "admin_password": "P@ssw0rd!",
  "software_installa": [
    {"id": "Google.Chrome", "metodo": "winget"},
    {"id": "7zip.7zip", "metodo": "winget"}
  ],
  "software_rimuovi": [
    {"nome": "Candy Crush Saga", "metodo": "winget"}
  ],
  "utenti_locali": [
    {"username": "utente1", "password": "Utente@123", "admin": false}
  ],
  "power_plan": "high_performance",
  "rdp_abilitato": true,
  "note": "PC reception"
}
```

**Response 201:**
```json
{
  "data": {
    "id": 42,
    "codice_univoco": "WD-K7M2",
    "stato": "pronto",
    "expires_at": "2026-03-08T22:53:00Z"
  },
  "message": "Wizard creato con successo"
}
```

---

### POST /api/agent/auth

**Request:**
```json
{
  "codice": "WD-K7M2"
}
```

**Response 200:**
```json
{
  "data": {
    "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 14400,
    "config": {
      "wizard_id": 42,
      "pc_name": "UFFICIO-PC-01",
      "admin_username": "adminlocale",
      "admin_password": "P@ssw0rd!",
      "software_installa": [ ... ],
      "software_rimuovi": [ ... ],
      "utenti_locali": [ ... ],
      "power_plan": "high_performance",
      "rdp_abilitato": true
    }
  },
  "message": "Autenticazione agent riuscita"
}
```

**Response 422 (codice scaduto):**
```json
{
  "message": "Il codice WD-K7M2 è scaduto o già utilizzato.",
  "errors": null
}
```

---

### POST /api/agent/step

**Request:**
```json
{
  "wizard_id": 42,
  "step_number": 3,
  "azione": "uninstall",
  "target": "Candy Crush Saga",
  "stato": "completato",
  "output": "Successfully uninstalled: Candy Crush Saga (King)",
  "durata_ms": 3420
}
```

**Response 200:**
```json
{
  "data": {
    "step_salvato": true,
    "step_number": 3
  },
  "message": "Step registrato"
}
```

---

### GET /api/wizards/{id}/monitor

**Response 200:**
```json
{
  "data": {
    "wizard_id": 42,
    "stato": "in_corso",
    "progresso": 62,
    "step_totali": 8,
    "step_completati_count": 5,
    "step_corrente": {
      "step_number": 6,
      "azione": "install",
      "target": "Google Chrome",
      "stato": "in_corso"
    },
    "steps": [
      {"step_number": 1, "azione": "rename_pc", "stato": "completato", "durata_ms": 800},
      {"step_number": 2, "azione": "create_admin", "stato": "completato", "durata_ms": 450}
    ],
    "hardware_info": {
      "hostname": "VECCHIO-NOME",
      "cpu": "Intel Core i5-12400",
      "ram_gb": 16,
      "disk_gb": 512,
      "os": "Windows 11 Pro 23H2",
      "os_build": "22631.3447"
    },
    "started_at": "2026-03-07T22:40:00Z",
    "estimated_completion": null
  },
  "message": null
}
```

---

## Rate Limiting

| Route Group | Limite | Finestra |
|---|---|---|
| `/api/auth/login` | 10 req | 1 minuto per IP |
| `/api/agent/auth` | 20 req | 1 minuto per IP |
| `/api/agent/step` | 120 req | 1 minuto per JWT |
| Tutte le altre `/api/*` | 60 req | 1 minuto per token |

> **Nota:** Il rate limiting usa `CF-Connecting-IP` come identificatore IP reale (non `REMOTE_ADDR`) perché tutte le richieste transitano da Cloudflare Tunnel.

Configurare in `app/Http/Kernel.php` (o `bootstrap/app.php` in Laravel 11):
```php
RateLimiter::for('api', function (Request $request) {
    $ip = $request->header('CF-Connecting-IP') ?? $request->ip();
    return Limit::perMinute(60)->by($request->user()?->id ?: $ip);
});
```

---

## Sicurezza API — Checklist

- [x] Tutti gli endpoint protetti richiedono `Authorization: Bearer <token>`
- [x] Validazione input tramite `FormRequest` (mai validazione solo in controller)
- [x] Password admin cifrate AES-256-GCM prima di salvarle in DB
- [x] JWT agent con scadenza 4 ore e revoca dopo `/complete`
- [x] Rate limiting differenziato per tipo endpoint
- [x] IP reale letto da `CF-Connecting-IP` (non `REMOTE_ADDR`)
- [x] Ruoli verificati tramite `spatie/laravel-permission` middleware
- [x] CORS configurato per accettare solo origini autorizzate
- [x] Log di sicurezza su ogni tentativo di autenticazione fallito
- [ ] **TODO:** Audit log su operazioni admin (crea/elimina utenti)
- [ ] **TODO:** 2FA per ruolo admin
