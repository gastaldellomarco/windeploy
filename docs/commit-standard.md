# WinDeploy — Commit Standard

> Versione: 1.0.0 | Aggiornato: 2026-03-07

Based on [Conventional Commits v1.0.0](https://www.conventionalcommits.org/en/v1.0.0/)

---

## Formato

```
<type>(<scope>): <description>

[corpo opzionale — separato da riga vuota]

[footer opzionale — Closes #N / Refs #N]
```

**Regole generali:**
- La prima riga (subject) non deve superare **72 caratteri**
- Il tipo e lo scope sono **obbligatori**
- La descrizione è in **minuscolo**, imperativo, senza punto finale
- Il corpo spiega il *perché*, non il *cosa* (quello lo dice il codice)
- Un commit = una modifica logica coerente

---

## Types

| Type | Quando usarlo | Bump versione |
|------|---------------|---------------|
| `feat` | Nuova funzionalità, nuovo endpoint, nuovo componente UI | MINOR |
| `fix` | Correzione bug (locale o produzione) | PATCH |
| `refactor` | Riscrittura codice senza cambiare comportamento | — |
| `docs` | Documentazione, README, commenti | — |
| `test` | Aggiunta o modifica test | — |
| `chore` | Setup tooling, dipendenze, script build, CI config | — |
| `security` | Fix vulnerabilità, aggiornamento dipendenze critiche, rimozione debug | PATCH |
| `perf` | Ottimizzazione performance (query, cache, bundle size) | PATCH |

> **Breaking change:** aggiungi `!` dopo il type/scope → `feat(backend)!: change wizard payload schema`
> Questo triggera un bump **MAJOR**.

---

## Scopes

| Scope | Cosa copre |
|-------|------------|
| `backend` | Laravel — modelli, controller, service, migration, route |
| `frontend` | React — componenti, hook, store, pagine, Vite config |
| `agent` | Python — classi, GUI, build PyInstaller |
| `db` | Migration, seed, cambiamenti schema MySQL |
| `ci` | GitHub Actions, workflow, Docker, deploy script |
| `docs` | File in `/docs`, README, CHANGELOG |
| `config` | File di configurazione progetto (.env.example, nginx, cloudflared) |

---

## Esempi Validi

```bash
# Nuova funzionalità backend
feat(backend): add WizardCodeService with uniqueness check

# Fix bug frontend
fix(frontend): resolve JWT token not sent on page reload

# Sicurezza — file debug rimossi
security(backend): remove tmp debug files from backend root

# Refactor agent
refactor(agent): delegate network calls to ApiClient class

# Migration database
feat(db): create execution_logs table with foreign key to wizards

# Documentazione
docs(docs): add git branch strategy and naming conventions

# CI/CD
chore(ci): add GitHub Actions workflow for backend deploy on main

# Performance
perf(backend): add composite index on execution_logs(wizard_id, status)

# Breaking change con corpo e footer
feat(backend)!: change wizard payload schema to v2 format

The previous schema used camelCase keys which was inconsistent
with the agent payload contract. All keys are now snake_case.

Closes #42
Refs #38

# Hotfix urgente
fix(backend): correct Sanctum guard missing in api.php middleware

The 'auth:sanctum' middleware was applied only to v1 routes.
Added to all protected route groups.

Closes #87
```

---

## Esempi NON Validi

```bash
# ❌ Nessun type/scope
git commit -m "fix bug"

# ❌ Description con maiuscola iniziale
git commit -m "feat(frontend): Add wizard builder"

# ❌ Subject troppo lungo (>72 char)
git commit -m "feat(backend): add complete wizard code generation service with all validation rules"

# ❌ Type non standard
git commit -m "update(backend): modify wizard service"

# ❌ Commit generico non informativo
git commit -m "fix(frontend): fixes"
git commit -m "chore(backend): various changes"
```

---

## Git Hooks (consigliato)

Installa `commitlint` per enforcement automatico:

```bash
# Nella root del progetto
npm install --save-dev @commitlint/cli @commitlint/config-conventional husky

# Inizializza husky
npx husky init
echo "npx --no -- commitlint --edit \$1" > .husky/commit-msg
```

File `commitlint.config.js` nella root:
```js
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [2, 'always', [
      'feat', 'fix', 'refactor', 'docs', 'test', 'chore', 'security', 'perf'
    ]],
    'scope-enum': [2, 'always', [
      'backend', 'frontend', 'agent', 'db', 'ci', 'docs', 'config'
    ]],
    'header-max-length': [2, 'always', 72],
    'subject-case': [2, 'always', 'lower-case'],
  }
};
```

---

## CHANGELOG

Il file `CHANGELOG.md` nella root viene aggiornato ad ogni release.
Può essere generato automaticamente con:

```bash
npx conventional-changelog-cli -p conventional -i CHANGELOG.md -s
```

Oppure manualmente organizzando i commit per tipo prima del merge su `main`.
