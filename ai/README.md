# WinDeploy — AI Artifact Storage Strategy

> Versione: 1.0.0 | Aggiornato: 2026-03-07

---

## Scopo

La cartella `ai/` è il **workspace persistente dell'AI** nel progetto WinDeploy.
Contiene lo stato del progetto visto dall'AI, le decisioni architetturali, i prompt usati nelle sessioni di sviluppo, e i contratti dati condivisi tra i moduli.

> ⚠️ **I file in `ai/` sono parte del repository ma NON vengono deployati.**
> Sono esclusi da `.dockerignore` e da tutti gli script di deploy.

---

## Struttura

```
ai/
├── README.md              ← questo file
├── memory/                ← stato progetto, decisioni, note
│   ├── project-state.md   ← stato attuale del progetto (aggiornato continuamente)
│   ├── decisions.md       ← log decisioni architetturali
│   └── open-questions.md  ← punti aperti e decisioni pendenti
├── prompts/               ← prompt usati, organizzati per fase
│   ├── phase-01-init/
│   ├── phase-02-backend/
│   ├── phase-03-frontend/
│   ├── phase-04-agent/
│   └── phase-05-deploy/
├── schemas/               ← contratti dati condivisi tra moduli
│   ├── wizard-payload.json
│   ├── execution-log-payload.json
│   └── agent-auth-payload.json
└── reviews/               ← output security e code review
    ├── security-review-YYYY-MM-DD.md
    └── code-review-YYYY-MM-DD.md
```

---

## Sottocartellle — Dettaglio

### `ai/memory/`

Stato persistente del progetto. Aggiornato ad ogni sessione di sviluppo significativa.

| File | Contenuto | Frequenza aggiornamento |
|------|-----------|------------------------|
| `project-state.md` | Cosa è completato, cosa è in corso, prossimi step | Ad ogni sessione |
| `decisions.md` | Decisioni architetturali prese (con motivo e alternative scartate) | Quando si prende una decisione significativa |
| `open-questions.md` | Dubbi irrisolti, trade-off da valutare, TODO tecnici | Ad ogni sessione |

**Formato `decisions.md`:**
```markdown
## 2024-01-15 — Autenticazione doppia Sanctum + JWT
**Decisione:** Usare Laravel Sanctum per sessioni web e JWT monouso per l'agent.
**Motivo:** Il frontend SPA richiede cookie-based auth (Sanctum), l'agent richiede token stateless (JWT).
**Alternative scartate:** Solo JWT per tutto — troppo complesso gestire CSRF con SPA.
**Impatto:** Doppio guard in api.php, middleware separati per le route agent.
```

### `ai/prompts/`

Prompt usati nelle sessioni AI, organizzati per fase di sviluppo.

- Naming file: `<YYYY-MM-DD>-<descrizione-breve>.md`
- Esempio: `2024-01-15-wizard-service-generation.md`
- Ogni file contiene: il prompt completo + l'output ricevuto (o link al file generato)
- Utile per: riprodurre risultati, capire perché una soluzione è stata scelta, onboarding

### `ai/schemas/`

**Contratti JSON** che definiscono la struttura dei payload condivisi tra Backend, Frontend e Agent.

Sono la fonte di verità per:
- Validazione Laravel (Form Request)
- TypeScript types nel frontend (se usato)
- Modelli Pydantic nell'agent Python

Formato file: JSON Schema Draft-07 compatibile.

Esempio `wizard-payload.json`:
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "WizardPayload",
  "type": "object",
  "required": ["wizard_code", "pc_name", "steps"],
  "properties": {
    "wizard_code": { "type": "string", "pattern": "^WZ-[A-Z0-9]{8}$" },
    "pc_name": { "type": "string", "maxLength": 255 },
    "steps": { "type": "array", "items": { "type": "object" } },
    "is_active": { "type": "boolean" },
    "created_at": { "type": "string", "format": "date-time" }
  }
}
```

### `ai/reviews/`

Output delle sessioni di **security review** e **code review** condotte con l'AI.

- Naming: `<tipo>-review-<YYYY-MM-DD>.md`
- Contenuto: vulnerabilità trovate, severità (Critical/High/Medium/Low), stato fix
- Le review **Critical** e **High** devono generare un issue GitHub con label `security`

---

## Regole di Deploy

### `.dockerignore`
```
ai/
```

### Script deploy Ubuntu 24 (`ci/deploy.sh`)
```bash
# La cartella ai/ NON viene mai copiata in produzione
rsync -av --exclude='ai/' --exclude='.git/' ./  ubuntu@server:/var/www/windeploy/
```

### GitHub Actions
Aggiungere in tutti i workflow di deploy:
```yaml
- name: Exclude AI artifacts from deploy
  run: |
    rm -rf ./ai
```

---

## Regole di Utilizzo

1. **Non inserire segreti** in `ai/` — nessuna password, token, chiave API
2. **Non inserire codice sorgente completo** — solo decisioni, schemi e prompt
3. **Aggiornare `memory/project-state.md`** all'inizio di ogni sessione AI prima di chiedere modifiche al codice
4. **Versionare gli schemi** in `ai/schemas/` come file separati con numero di versione nel nome se cambiano breaking: `wizard-payload-v2.json`
5. I file in `ai/` sono soggetti alle stesse regole di commit degli altri file
