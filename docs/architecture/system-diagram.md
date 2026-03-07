# WinDeploy — System Diagram

> Architettura di produzione e sviluppo locale con tutti i componenti infrastrutturali.

---

## Produzione — Architettura Completa

```
 ╔══════════════════════════════════════════════════════════════════════════╗
 ║                         INTERNET (HTTPS/443)                            ║
 ╚══════════════════════════════════════════════════════════════════════════╝
          │                                      │
          │                                      │
 ┌────────▼─────────┐                  ┌─────────▼──────────┐
 │  Browser/React   │                  │   Agent .exe       │
 │  (utente web)    │                  │   (Windows PC)     │
 │                  │                  │                    │
 │  windeploy.      │                  │  ENV=prod          │
 │  mavcoo.it       │                  │  https://api.      │
 └────────┬─────────┘                  │  windeploy.mavcoo.it│
          │ HTTPS                      └─────────┬──────────┘
          │ TLS 1.3                              │ HTTPS
          ▼                                      │ TLS 1.3
 ┌──────────────────────────────────────────────▼──────────────────────┐
 │                      CLOUDFLARE EDGE                                │
 │                                                                     │
 │  DNS: windeploy.mavcoo.it  → Cloudflare Proxy (orange cloud)       │
 │  DNS: api.windeploy.mavcoo.it → Cloudflare Proxy                   │
 │                                                                     │
 │  SSL terminato a Cloudflare Edge (Full strict mode)                │
 │  DDoS protection + WAF + Rate limiting (CF rules)                  │
 │  CF-Connecting-IP header → IP reale visitor                        │
 └────────────────────────────┬────────────────────────────────────────┘
                              │
                              │ HTTP (interno tunnel)
                              │ connessione outbound persistente
                              ▼
 ┌────────────────────────────────────────────────────────────────────┐
 │            CLOUDFLARE TUNNEL (cloudflared daemon)                  │
 │                                                                     │
 │  Tunnel ID: 32e9943d-d2d3-41e1-9776-94a684aaec30                  │
 │  Processo: cloudflared service (systemd)                           │
 │  Config: /etc/cloudflared/config.yml                               │
 │                                                                     │
 │  ingress rules:                                                     │
 │    hostname: windeploy.mavcoo.it     → localhost:80                │
 │    hostname: api.windeploy.mavcoo.it → localhost:80                │
 │    hostname: remote.windeploy.mavcoo.it → localhost:80             │
 └────────────────────────────┬───────────────────────────────────────┘
                              │
                              │ HTTP localhost:80
                              ▼
 ┌────────────────────────────────────────────────────────────────────┐
 │                    NGINX (Ubuntu 24 LTS)                           │
 │                    /etc/nginx/sites-enabled/windeploy              │
 │                                                                     │
 │   server_name windeploy.mavcoo.it api.windeploy.mavcoo.it;        │
 │                                                                     │
 │     location /api/  ──────────────────────────────────────┐        │
 │     location /      ──────────────────────────────────┐   │        │
 │                                                        │   │        │
 └────────────────────────────────────────────────────────┼───┼────────┘
                                                          │   │
                                              ┌───────────▼┐  │
                                              │Static files│  │
                                              │/var/www/   │  │
                                              │windeploy/  │  │
                                              │public/dist/│  │
                                              │(React SPA) │  │
                                              └────────────┘  │
                                                              │
                                                 ┌────────────▼─────────┐
                                                 │   PHP-FPM 8.3        │
                                                 │   Socket Unix/TCP    │
                                                 │   fastcgi_pass       │
                                                 └────────────┬─────────┘
                                                              │
                                                 ┌────────────▼─────────┐
                                                 │   Laravel 11         │
                                                 │   /var/www/windeploy │
                                                 │                      │
                                                 │   .env (prod)        │
                                                 │   APP_ENV=production │
                                                 │   QUEUE_CONNECTION=  │
                                                 │   database           │
                                                 └────────────┬─────────┘
                                                              │
                                                 ┌────────────▼─────────┐
                                                 │   MySQL 8.0          │
                                                 │   localhost:3306     │
                                                 │   DB: windeploy_prod │
                                                 │   user: windeploy    │
                                                 └──────────────────────┘
```

---

## Sviluppo Locale — Architettura XAMPP

```
 ┌──────────────────────────────────────────────────────────────────────┐
 │                      WINDOWS 11 (Dev Machine)                       │
 │                                                                      │
 │  ┌─────────────────┐        ┌──────────────────────────────────┐   │
 │  │ Browser Chrome  │        │     Agent .exe (debug)            │   │
 │  │ localhost:5173  │        │     ENV=local                     │   │
 │  └────────┬────────┘        │     API_URL=http://windeploy.local│   │
 │           │ HTTP            └─────────────────┬────────────────┘   │
 │           ▼                                   │ HTTP               │
 │  ┌─────────────────────┐                      │                    │
 │  │   Vite Dev Server   │                      │                    │
 │  │   localhost:5173    │                      │                    │
 │  │                     │                      │                    │
 │  │  HMR, proxy /api    │                      │                    │
 │  │  vite.config.js:    │                      │                    │
 │  │  proxy: {           │                      │                    │
 │  │   '/api': {         │                      │                    │
 │  │    target: http://  │                      │                    │
 │  │    windeploy.local  │                      │                    │
 │  │   }                 │                      │                    │
 │  │  }                  │                      │                    │
 │  └────────┬────────────┘                      │                    │
 │           │ HTTP proxy /api                   │                    │
 │           │ (Vite forward)                    │                    │
 │           ▼                                   ▼                    │
 │  ┌─────────────────────────────────────────────────────────────┐  │
 │  │              XAMPP (Apache 2.4 + PHP 8.3)                   │  │
 │  │                                                             │  │
 │  │  Virtual Host: windeploy.local → C:\xampp\htdocs\windeploy  │  │
 │  │  DocumentRoot: public/                                      │  │
 │  │  mod_rewrite: ON (Laravel routing)                          │  │
 │  │                                                             │  │
 │  │  Apache:80  →  Laravel 11 public/index.php                  │  │
 │  └───────────────────────────┬─────────────────────────────────┘  │
 │                              │                                     │
 │                  ┌───────────▼─────────────┐                      │
 │                  │      MySQL 8.0 XAMPP     │                      │
 │                  │      localhost:3306       │                      │
 │                  │      DB: windeploy_local  │                      │
 │                  └─────────────────────────┘                      │
 └──────────────────────────────────────────────────────────────────────┘
```

---

## Sottodomini e Routing

| Sottodominio | Ambiente | Destinazione | Uso |
|---|---|---|---|
| `windeploy.mavcoo.it` | Prod | Nginx → React SPA dist | Frontend principale |
| `api.windeploy.mavcoo.it` | Prod | Nginx → PHP-FPM → Laravel | API per React + Agent |
| `remote.windeploy.mavcoo.it` | Prod | Nginx → Laravel | Endpoint agent remoto |
| `dev.windeploy.mavcoo.it` | Dev/Staging | TBD | Ambiente di test |
| `test.windeploy.mavcoo.it` | Test | TBD | CI/CD testing |
| `windeploy.local` | Locale | XAMPP Apache:80 | Sviluppo locale |
| `localhost:5173` | Locale | Vite dev server | HMR React |

---

## Stack Versioni (produzione)

| Componente | Versione | Note |
|---|---|---|
| Ubuntu | 24.04 LTS | Server OS |
| Nginx | 1.24.x | Reverse proxy + static |
| PHP | 8.3.x | php-fpm |
| Laravel | 11.x | Backend framework |
| MySQL | 8.0.x | Database |
| cloudflared | latest stable | Tunnel daemon |
| Node.js | 20.x LTS | Solo build React |
| React | 18.x | SPA framework |
| Vite | 5.x | Build tool |
| Python | 3.11.x | Agent runtime |
| PyInstaller | 6.x | Agent compilation |
