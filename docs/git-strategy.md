# WinDeploy — Git Branch Strategy

> Versione: 1.0.0 | Aggiornato: 2026-03-07

---

## Branch Model

WinDeploy usa un modello derivato da **Git Flow semplificato**, ottimizzato per team piccoli con deploy continuo su produzione via Cloudflare Tunnel.

```
main
  └── develop
        ├── feature/wizard-builder
        ├── feature/software-library-ui
        └── feature/agent-jwt-refresh

main
  └── fix/jwt-guard-config        ← hotfix urgenti
```

---

## Branch Definitions

### `main`
- Rappresenta la **produzione stabile**.
- Ogni commit su `main` triggera il deploy automatico (GitHub Actions → Ubuntu 24).
- **Nessun commit diretto è consentito.** Solo merge da `develop` (release) o da `fix/*` (hotfix).
- Ogni merge su `main` deve essere taggato con una versione semantica: `v1.0.0`.

### `develop`
- Branch di **integrazione continua**.
- Tutte le feature vengono integrate qui prima del rilascio.
- Può essere deployato sull'ambiente `dev.windeploy.mavcoo.it` automaticamente.
- I merge da `feature/*` richiedono **Pull Request approvata** (almeno 1 reviewer).

### `feature/*`
- **Origine:** `develop`
- **Merge target:** `develop` via Pull Request
- Usato per ogni nuova funzionalità, componente UI, endpoint API, o modifica agent.
- Naming: `feature/<descrizione-kebab-case>` (max 50 caratteri totali)
- Esempi validi:
  ```
  feature/wizard-builder
  feature/software-library-ui
  feature/agent-jwt-refresh
  feature/execution-log-filter
  ```

### `fix/*`
- **Origine:** `main` (per hotfix critici in produzione)
- **Merge target:** `main` E `develop` (doppio merge obbligatorio)
- Usato per bug critici che non possono attendere il ciclo `develop → main`.
- Naming: `fix/<descrizione-kebab-case>` (max 50 caratteri totali)
- Esempi validi:
  ```
  fix/jwt-guard-config
  fix/nginx-cors-header
  fix/agent-version-mismatch
  ```
- Dopo il merge su `main`, il branch `fix/*` viene eliminato.
- Il merge su `develop` avviene subito dopo per mantenere la sincronizzazione.

---

## Regole Operative

### Pull Request
- **Obbligatoria** per qualsiasi merge su `main` o `develop`.
- Titolo PR: segue il formato commit (`feat(frontend): add wizard step progress bar`).
- Template PR disponibile in `.github/pull_request_template.md`.
- Almeno **1 review approvata** prima del merge.
- I check CI devono essere **verdi** (build + lint + test).

### Protezione Branch
Configurare in GitHub → Settings → Branches:

| Branch    | Require PR | Require review | Require status checks | No force push |
|-----------|------------|----------------|----------------------|---------------|
| `main`    | ✅          | ✅ (min 1)      | ✅ (CI pipeline)      | ✅             |
| `develop` | ✅          | ✅ (min 1)      | ✅ (CI pipeline)      | ✅             |

### Lifecycle Branch
```
# Crea feature branch da develop
git checkout develop && git pull origin develop
git checkout -b feature/nome-funzionalita

# Lavora, committa, pusha
git push origin feature/nome-funzionalita

# Apri PR verso develop su GitHub
# Dopo approvazione → merge → elimina branch remoto
git branch -d feature/nome-funzionalita
git push origin --delete feature/nome-funzionalita
```

### Hotfix Workflow
```
# Crea hotfix da main
git checkout main && git pull origin main
git checkout -b fix/descrizione-problema

# Committa la fix
git push origin fix/descrizione-problema

# PR verso main → merge → tag versione PATCH
git tag -a v1.0.1 -m "fix: descrizione problema"
git push origin v1.0.1

# Merge immediato anche su develop
git checkout develop && git merge fix/descrizione-problema
git push origin develop
```

---

## Naming Convention Branch

| Tipo      | Pattern                        | Esempio                        |
|-----------|--------------------------------|--------------------------------|
| Feature   | `feature/<slug>`               | `feature/wizard-builder`       |
| Hotfix    | `fix/<slug>`                   | `fix/jwt-guard-config`         |
| Release   | `release/<version>` (opz.)     | `release/1.2.0`                |

**Regole slug:**
- Kebab-case obbligatorio (`-` come separatore, no underscore)
- Solo caratteri alfanumerici + trattino
- Massimo 50 caratteri totali per il nome branch
- No prefissi ridondanti (`feature/feature-wizard` ❌)
- Descrive la funzionalità, non il task (`feature/fix-things` ❌)

---

## Environments e Sottodomini

| Branch    | Ambiente        | URL                                  | Deploy |
|-----------|-----------------|--------------------------------------|--------|
| `main`    | Production      | `windeploy.mavcoo.it`                | Auto (Actions) |
| `develop` | Development     | `dev.windeploy.mavcoo.it`            | Auto (Actions) |
| `feature/*` | Local / Preview | `windeploy.local`                  | Manuale |
