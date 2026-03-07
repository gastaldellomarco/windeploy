# WinDeploy — Project State

> Ultimo aggiornamento: 2026-03-07

---

## Versione Corrente
`0.1.0` — Fase di inizializzazione repository

## Stack Confermato
- **Backend:** Laravel 11, PHP 8.3, Sanctum (web) + JWT monouso (agent)
- **Frontend:** React 18 + Vite, proxy verso windeploy.local (XAMPP locale)
- **Agent:** Python 3.11 + CustomTkinter, build .exe con PyInstaller
- **Database:** MySQL 8, XAMPP locale, Ubuntu 24 in produzione
- **Deploy:** Ubuntu 24 LTS + Nginx + Cloudflare Tunnel (ID: 32e9943d-d2d3-41e1-9776-94a684aaec30)
- **Dominio:** windeploy.mavcoo.it
- **Sottodomini attivi:** api, dev, remote, test

## Completato
- [x] Repository GitHub creato (gastaldellomarco/windeploy)
- [x] Struttura cartelle: /backend, /frontend, /agent, /docs
- [x] Inizializzazione piano repository (docs/, ai/, .github/, .gitignore)

## In Corso
- [ ] Setup Laravel 11 in /backend
- [ ] Setup React 18 + Vite in /frontend
- [ ] Setup Python agent in /agent
- [ ] Configurazione GitHub Actions CI/CD
- [ ] Configurazione Nginx + Cloudflare Tunnel su Ubuntu 24

## Prossimi Step
1. Installare Laravel 11 e configurare .env per XAMPP locale
2. Creare struttura base React + Vite con routing e auth
3. Implementare autenticazione Sanctum per il frontend SPA
4. Implementare JWT monouso per l'agent Python
5. Prima migration: users, wizards, execution_logs
