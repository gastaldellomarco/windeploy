<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# backend/.env — Configurazione Laravel Locale XAMPP + Agent Python

**Data aggiornamento:** 2026-03-06  
**Ambiente:** Sviluppo locale (XAMPP)  
**Stack:** Laravel 11 (PHP 8.3) | MySQL 8 | Python 3.11 Agent

---

## 📋 Struttura `.env` Completa

```env
# ════════════════════════════════════════════════════════════════════
# APP BASIC
# ════════════════════════════════════════════════════════════════════

APP_NAME=WinDeploy
APP_ENV=local                   # local | staging | production
APP_KEY=                        # php artisan key:generate
APP_DEBUG=true                  # ⚠️ false in produzione!
APP_TIMEZONE=Europe/Rome

# ════════════════════════════════════════════════════════════════════
# URLS (Backend API + Frontend Discovery)
# ════════════════════════════════════════════════════════════════════

# Virtual host backend (Apache vhosts.conf)
APP_URL=http://windeploy.local.api
ASSET_URL=http://windeploy.local.api

# URL raggiungibile dall'agent Windows (per WINDEPLOY_API_URL env)
WINDEPLOY_API_URL=http://windeploy.local.api

# ════════════════════════════════════════════════════════════════════
# DATABASE (MySQL di XAMPP)
# ════════════════════════════════════════════════════════════════════

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=windeploy_db
DB_USERNAME=windeploy_user
DB_PASSWORD=password_locale     # Imposta la tua password phpMyAdmin

# ════════════════════════════════════════════════════════════════════
# CACHE / SESSIONI (file in locale)
# ════════════════════════════════════════════════════════════════════

CACHE_STORE=file
SESSION_DRIVER=file
SESSION_LIFETIME=480            # 8 ore
SESSION_SECURE_COOKIE=false     # false in locale (HTTPS off)

# ════════════════════════════════════════════════════════════════════
# QUEUE (job asincroni)
# ════════════════════════════════════════════════════════════════════

QUEUE_CONNECTION=sync           # sync in locale; database/redis in prod

# ════════════════════════════════════════════════════════════════════
# LOGGING
# ════════════════════════════════════════════════════════════════════

LOG_CHANNEL=daily
LOG_LEVEL=debug                 # debug in locale; info/warning in prod

# ════════════════════════════════════════════════════════════════════
# AUTHENTICATION — Sanctum (Web App React)
# ════════════════════════════════════════════════════════════════════

SANCTUM_STATEFUL_DOMAINS=localhost:5173,windeploy.local.api
SANCTUM_COOKIE_PATH=/
SANCTUM_COOKIE_DOMAIN=windeploy.local.api

# ════════════════════════════════════════════════════════════════════
# AUTHENTICATION — JWT (Agent Windows .exe Python)
# ════════════════════════════════════════════════════════════════════

JWT_SECRET=                     # php artisan jwt:secret
JWT_TTL=240                     # Token validity: 4 hours (minutes)
JWT_REFRESH_TTL=0               # Monouso (no refresh per agent)
JWT_ALGORITHM=HS256

# ════════════════════════════════════════════════════════════════════
# RATE LIMITING (Throttle Middleware)
# ════════════════════════════════════════════════════════════════════

# Definizioni in: bootstrap/app.php + app/Providers/RateLimiterServiceProvider.php
# throttle:login → 5 attempts/15min per IP
# throttle:agent → 120 attempts/1min per JWT token or IP

# ════════════════════════════════════════════════════════════════════
# CORS (Cross-Origin Requests)
# ════════════════════════════════════════════════════════════════════

CORS_ALLOWED_ORIGINS=http://localhost:5173,http://windeploy.local.api

# ════════════════════════════════════════════════════════════════════
# MAIL (Log only in locale)
# ════════════════════════════════════════════════════════════════════

MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@windeploy.local
MAIL_FROM_NAME=WinDeploy

# In produzione: cambia a SMTP (SendGrid, Mailgun, ecc.)
```

---

## ⚠️ Passaggi Setup (Checklist)

Prima di usare questo `.env`, esegui:

```bash
# 1. Genera APP_KEY
php artisan key:generate

# 2. Genera JWT_SECRET
php artisan jwt:secret

# 3. Migra il database
php artisan migrate --seed

# 4. Verificare configurazione
php artisan config:cache
```

---

## 🔴 Problemi Comuni e Soluzione

| Problema                           | Causa                                      | Soluzione                                         |
| ---------------------------------- | ------------------------------------------ | ------------------------------------------------- |
| "The APP_KEY is not set"           | `APP_KEY=` vuoto                           | `php artisan key:generate`                        |
| JWT 401 su agent                   | `JWT_SECRET=` vuoto                        | `php artisan jwt:secret`                          |
| "Driver [mail] not supported"      | `MAIL_MAILER` errato                       | Usa `log` in locale, `smtp` in prod               |
| "CORS denied"                      | Frontend URL non in `CORS_ALLOWED_ORIGINS` | Aggiungi l'URL del frontend                       |
| "Invalid SANCTUM_STATEFUL_DOMAINS" | Dominio mancante                           | Aggiungi `localhost:5173` e `windeploy.local.api` |

---

## 🚀 Differenze Locale vs Produzione

| Config                  | Locale                       | Produzione                         |
| ----------------------- | ---------------------------- | ---------------------------------- |
| `APP_ENV`               | `local`                      | `production`                       |
| `APP_DEBUG`             | `true`                       | `false`                            |
| `SESSION_SECURE_COOKIE` | `false`                      | `true`                             |
| `MAIL_MAILER`           | `log`                        | `smtp`                             |
| `QUEUE_CONNECTION`      | `sync`                       | `database` o `redis`               |
| `APP_URL`               | `http://windeploy.local.api` | `https://windeploy.tuodominio.com` |

---

## 📌 Note Importanti

- ✅ **Il file `.env` non va in Git** (già in `.gitignore` di Laravel di default)
- ✅ **Password in produzione**: usa un vault (AWS Secrets, Vault, ecc.)
- ✅ **JWT_TTL=240**: agent deve ri-autenticarsi dopo 4h — pianifica refresh logic se necessario
- ⚠️ **QUEUE_CONNECTION=sync**: perfetto per sviluppo; in Fase 2 migrare a `database` per report asincroni

---

**[Modifiche apportate: Aggiunto WINDEPLOY_API_URL, JWT_ALGORITHM, CORS_ALLOWED_ORIGINS, checklist setup, troubleshooting, locale vs production comparison]**
