# WinDeploy — Architectural Decisions Log

> Formato: data, titolo, decisione, motivo, alternative scartate, impatto

---

## 2026-03-07 — Doppia autenticazione Sanctum + JWT

**Decisione:** Laravel Sanctum per sessioni web SPA, JWT monouso (tymondesigns/jwt-auth) per l'agent Python.

**Motivo:** Il frontend React gira nello stesso dominio (o subdomain) del backend, Sanctum è il metodo raccomandato da Laravel per SPA. L'agent è un client esterno headless che non può gestire cookie, quindi JWT stateless è più appropriato.

**Alternative scartate:**
- Solo JWT per tutto: gestione CSRF per SPA con JWT è più complessa e meno sicura.
- Solo Sanctum per tutto: l'agent non può gestire cookie facilmente da Python.

**Impatto:** Doppio guard configurato in `config/auth.php`, middleware separati per route `/api/agent/*` vs route utente.

---

## 2026-03-07 — Cloudflare Tunnel come reverse proxy

**Decisione:** Usare cloudflared daemon outbound invece di esporre porte direttamente.

**Motivo:** Sicurezza: nessuna porta aperta in ingresso sul server Ubuntu. Cloudflare gestisce TLS, DDoS, WAF.

**Impatto:** L'IP reale del visitatore arriva nell'header `CF-Connecting-IP`, NON in `REMOTE_ADDR`. Tutti i middleware Laravel che usano IP devono leggere `CF-Connecting-IP`.

---

## 2026-03-07 — Monorepo vs Repo separati

**Decisione:** Monorepo unico (gastaldellomarco/windeploy) con sottocartelle /backend, /frontend, /agent.

**Motivo:** Team piccolo (sviluppatore singolo), dipendenze tra moduli frequenti, più semplice gestire versioni sincronizzate e CI/CD unificato.

**Alternative scartate:** 3 repo separati — overhead di gestione sproporzionato per la fase attuale.

**Impatto:** .gitignore e GitHub Actions devono coprire tutti e tre gli stack.
