# AI Memory System — WinDeploy

Questa cartella contiene il sistema di **memoria persistente** per le sessioni AI sul progetto WinDeploy.

## File presenti

| File | Scopo | Frequenza aggiornamento |
|------|-------|-------------------------|
| `project_state.md` | Stato attuale del progetto, completamento moduli, issue aperte per priorità | Dopo ogni sessione |
| `decisions_log.md` | Log immutabile (append-only) di tutte le decisioni architetturali | Quando si prende una nuova decisione |
| `ai_notes.md` | Istruzioni specifiche per AI (Claude/GPT/Gemini), errori da non rifare | Quando si scopre un pattern |
| `open_questions.md` | Domande architetturali aperte che richiedono una decisione | Quando nasce una nuova questione |

---

## Prompt di aggiornamento memoria

Alla fine di ogni sessione di sviluppo, usa questo prompt con Claude (o qualsiasi AI) per aggiornare i file:

```
Aggiorna il project_state.md e decisions_log.md di WinDeploy
con il lavoro fatto in questa sessione:

- Ho completato: [lista di feature/task terminati con file modificati]
- Ho deciso: [decisioni architetturali con motivazione]
- Ho incontrato questi problemi: [lista con soluzione adottata]
- Rimane aperto: [lista di cose non risolte]

Fornisci solo le sezioni da aggiornare/aggiungere, non riscrivere
l'intero file se non necessario.
```

---

## Come usare la memoria in una nuova sessione

All'inizio di ogni sessione AI, incolla il contenuto di questi file nel contesto:

1. **Sempre**: `project_state.md` (stato corrente)
2. **Se lavori su architettura**: `decisions_log.md`
3. **Se lavori su codice**: `ai_notes.md` (sezione relativa al modulo)
4. **Se devi prendere decisioni**: `open_questions.md`

---

## Convenzioni

- `project_state.md` — sovrascrivibile, aggiornare le sezioni rilevanti
- `decisions_log.md` — **append-only**, mai modificare decisioni passate
- `ai_notes.md` — aggiungere nuove note senza rimuovere quelle esistenti
- `open_questions.md` — spostare le domande risolte in fondo nella sezione Risolte
