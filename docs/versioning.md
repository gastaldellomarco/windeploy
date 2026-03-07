# WinDeploy — Versioning Rules

> Versione: 1.0.0 | Aggiornato: 2026-03-07

---

## Semantic Versioning (SemVer 2.0.0)

WinDeploy segue [Semantic Versioning 2.0.0](https://semver.org/).

Formato: **`MAJOR.MINOR.PATCH`**

| Componente | Quando incrementare | Esempio |
|------------|--------------------|---------|
| **MAJOR** | Breaking change API o schema DB incompatibile con versioni precedenti | `1.0.0 → 2.0.0` |
| **MINOR** | Nuova funzionalità retrocompatibile (nuovi endpoint, nuovi campi opzionali, nuova schermata) | `1.0.0 → 1.1.0` |
| **PATCH** | Bugfix, sicurezza, performance, documentazione | `1.0.0 → 1.0.1` |

> Quando incrementi MAJOR → resetta MINOR e PATCH a 0.
> Quando incrementi MINOR → resetta PATCH a 0.

---

## Esempi di Applicazione

```
# Breaking change: schema DB wizard incompatibile con agent v1
1.0.0 → 2.0.0

# Nuova funzionalità: aggiunta schermata Software Library al frontend
1.0.0 → 1.1.0

# Aggiunto nuovo campo opzionale all'endpoint /api/wizards (retrocompatibile)
1.1.0 → 1.2.0

# Fix: JWT token non inviato al reload della pagina
1.2.0 → 1.2.1

# Security: rimossi file debug dalla root
1.2.1 → 1.2.2
```

---

## File VERSION

File `VERSION` nella **root del progetto** (testo semplice, no newline extra):

```
1.0.0
```

**Regole:**
- Aggiornato manualmente prima del merge su `main`
- Commit dedicato: `chore(config): bump version to 1.1.0`
- Deve essere sincronizzato con il tag Git

---

## Tag Git

Ogni versione rilasciata su `main` deve avere un **tag annotato**:

```bash
# Crea tag annotato
git tag -a v1.0.0 -m "Release v1.0.0 — initial production release"
git push origin v1.0.0

# Oppure tagga un commit specifico
git tag -a v1.1.0 abc1234 -m "Release v1.1.0 — add software library module"
git push origin v1.1.0
```

**Formato tag:** `v` + versione SemVer
- ✅ `v1.0.0`, `v1.1.0`, `v1.1.1`, `v2.0.0`
- ❌ `1.0.0`, `release-1.0`, `v1.0`

---

## Versione Agent .exe

L'agent compilato con PyInstaller deve avere la **stessa versione del backend**.

### Controllo compatibilità all'avvio

L'agent legge la propria versione da `agent/src/config.py`:

```python
# agent/src/config.py
AGENT_VERSION = "1.0.0"
```

All'avvio, l'agent chiama l'endpoint backend:

```
GET /api/agent/version-check
Headers: Authorization: Bearer <jwt-token>
Body: { "agent_version": "1.0.0" }
```

Risposta backend:
```json
// Compatibile
{ "compatible": true, "backend_version": "1.0.0", "message": null }

// Incompatibile (MAJOR diverso)
{ "compatible": false, "backend_version": "2.0.0", "message": "Aggiornamento agent richiesto. Scarica la nuova versione." }

// Warning (MINOR diverso, retrocompatibile)
{ "compatible": true, "backend_version": "1.2.0", "message": "Aggiornamento agent disponibile. Alcune funzionalità potrebbero non essere disponibili." }
```

### Logica di compatibilità
- **MAJOR diverso:** blocco avvio + messaggio errore + link download
- **MINOR diverso:** warning non bloccante + suggerimento aggiornamento
- **PATCH diverso:** silenzioso, nessun avviso

### Release agent .exe
L'eseguibile compilato viene caricato come **GitHub Release Asset** con il tag corrispondente:

```
Release v1.1.0
  └── windeploy-agent-v1.1.0-win64.exe
```

---

## Pre-release e Build Metadata

Per versioni non ancora stabili (alpha/beta):

```
1.0.0-alpha.1
1.0.0-beta.2
1.0.0-rc.1
```

Queste versioni:
- NON vengono deployate in produzione automaticamente
- NON triggera la build dell'agent .exe
- Vengono taggate su `develop` o `release/*`

---

## Workflow Release Completo

```bash
# 1. Aggiorna VERSION nella root
echo "1.1.0" > VERSION

# 2. Aggiorna AGENT_VERSION in agent/src/config.py
sed -i 's/AGENT_VERSION = .*/AGENT_VERSION = "1.1.0"/' agent/src/config.py

# 3. Commit di versione
git add VERSION agent/src/config.py
git commit -m "chore(config): bump version to 1.1.0"

# 4. Merge su main (via PR)
# 5. Tagger main
git checkout main
git tag -a v1.1.0 -m "Release v1.1.0"
git push origin v1.1.0

# 6. GitHub Actions builda agent.exe e crea la Release automaticamente
```
