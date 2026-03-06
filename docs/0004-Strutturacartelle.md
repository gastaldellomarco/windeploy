<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# \# ════════════════════════════════════════════════════════════════════

# FILE 1: C:\xampp\apache\conf\extra\httpd-vhosts.conf

# Aggiungi in fondo al file (non cancellare le voci esistenti)

# ════════════════════════════════════════════════════════════════════

# ── Virtual host per Laravel API ──

<VirtualHost *:80>
    ServerName windeploy.local
    DocumentRoot "C:/xampp/htdocs/windeploy/backend/public"
 
    <Directory "C:/xampp/htdocs/windeploy/backend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
 
    \# Log specifici per il progetto
    ErrorLog  "C:/xampp/apache/logs/windeploy-error.log"
    CustomLog "C:/xampp/apache/logs/windeploy-access.log" combined
</VirtualHost>

# ── NOTA: React gira su Vite dev server (porta 5173) ──

# Non serve un virtual host Apache per React in sviluppo.

# Vite ha un proxy integrato che manda /api → windeploy.local

# ════════════════════════════════════════════════════════════════════

# FILE 2: C:\Windows\System32\drivers\etc\hosts

# Aggiungi questa riga (richiede apertura come Amministratore)

# ════════════════════════════════════════════════════════════════════

127.0.0.1    windeploy.local

# ════════════════════════════════════════════════════════════════════

# FILE 3: C:\xampp\htdocs\windeploy\backend\public\.htaccess

# Necessario per il routing di Laravel su Apache/XAMPP

# ════════════════════════════════════════════════════════════════════

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
 
    RewriteEngine On
 
    \# Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
 
    \# Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/\$
    RewriteRule ^ %1 [L,R=301]
 
    \# Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# ════════════════════════════════════════════════════════════════════

# COMANDI DA ESEGUIRE (PowerShell o CMD come Amministratore)

# ════════════════════════════════════════════════════════════════════

# 1. Crea la struttura cartelle

mkdir C:\xampp\htdocs\windeploy
mkdir C:\xampp\htdocs\windeploy\backend

# 2. Abilita mod_rewrite in Apache (necessario per Laravel)

# Apri C:\xampp\apache\conf\httpd.conf

# Cerca la riga: \#LoadModule rewrite_module modules/mod_rewrite.so

# Rimuovi il \# all'inizio della riga

# 3. Riavvia Apache dal XAMPP Control Panel dopo le modifiche

# 4. Verifica che il virtual host funzioni:

# Apri browser → [http://windeploy.local](http://windeploy.local)

# Deve mostrare la pagina di Laravel (anche se non ancora installato)

Ho letto tutta la documentazione del progetto. La query che hai incollato è una **configurazione già pronta** che ti è stata fornita in una sessione precedente. Ti fornisco ora un'analisi critica completa di quei file con i fix necessari rispetto allo schema documentato nel progetto.

***

## ⚠️ Problema Principale da Correggere

La configurazione che hai incollato usa **un solo virtual host** (`windeploy.local`) che punta direttamente al backend Laravel. Questo **confligge** con l'architettura definita nella documentazione del progetto, che separa frontend (`windeploy.local`) e backend API (`windeploy.local.api`) su due ServerName distinti.[^1_1][^1_2]

***

## File 1 — `httpd-vhosts.conf` Corretto

Il file che hai ricevuto mappa `windeploy.local` al backend Laravel, ma la ricerca preliminare del progetto aveva definito `windeploy.local.api` per il backend. La configurazione corretta per WinDeploy è questa:[^1_2]

```apache
# C:\xampp\apache\conf\extra\httpd-vhosts.conf
# IMPORTANTE: mantieni localhost VHost alla fine

# ── VHost per Laravel API Backend ──
<VirtualHost *:80>
    ServerName windeploy.local.api
    DocumentRoot "C:/xampp/htdocs/windeploy/backend/public"

    <Directory "C:/xampp/htdocs/windeploy/backend/public">
        Options -Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  "C:/xampp/apache/logs/windeploy-error.log"
    CustomLog "C:/xampp/apache/logs/windeploy-access.log" combined
</VirtualHost>

# ── VHost fallback per localhost (non rimuovere!) ──
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
</VirtualHost>
```

> ⚠️ **Sicurezza:** la configurazione originale aveva `Options Indexes` che elenca i file della directory se `index.php` non risponde. Va cambiato in `Options -Indexes`.[^1_2]

***

## File 2 — `hosts` Corretto

Servono **due righe**, non una:

```
# C:\Windows\System32\drivers\etc\hosts

127.0.0.1    windeploy.local.api
```

Non serve un record per `windeploy.local` perché React gira su Vite (porta 5173) e non tramite Apache. Aggiungi `windeploy.local` solo se in futuro vuoi servire la build statica React da Apache.[^1_1]

***

## File 3 — `.htaccess` ✅ Corretto

Il `.htaccess` incollato è quello **standard di Laravel** ed è corretto così com'è. Non modificarlo.[^1_1]

***

## Checklist Operativa Completa

Esegui in questo ordine esatto:

**Struttura cartelle (PowerShell Admin):**

```powershell
New-Item -ItemType Directory -Force -Path "C:\xampp\htdocs\windeploy\backend"
New-Item -ItemType Directory -Force -Path "C:\xampp\htdocs\windeploy\frontend"
```

**Abilita mod_rewrite in `httpd.conf`:**

```
# Cerca e decommmenta questa riga:
LoadModule rewrite_module modules/mod_rewrite.so
```

**Abilita il file vhosts in `httpd.conf`:**

```
# Cerca e decommenta questa riga (spesso già commentata!):
Include conf/extra/httpd-vhosts.conf
```

> Questo passaggio mancava nella configurazione originale che ti è stata fornita — senza di esso i virtual host vengono ignorati completamente.

**Vite proxy in `frontend/vite.config.js`:**

```js
// frontend/vite.config.js
export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: 'http://windeploy.local.api',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
```

**Verifica finale:**

```powershell
# Test che Apache risolva il virtual host
curl http://windeploy.local.api
# Deve rispondere con HTML Laravel (404 o welcome page)
```


***

## 🔴 Avvocato del Diavolo — Rischi di questa Config

| Problema | Causa | Fix |
| :-- | :-- | :-- |
| Apache non parte | `httpd-vhosts.conf` malformato (una tag non chiusa) | `C:\xampp\apache\bin\httpd.exe -t` per testare la sintassi prima di riavviare |
| `windeploy.local.api` non raggiungibile | `Include conf/extra/httpd-vhosts.conf` ancora commentato | Decommenta in `httpd.conf` |
| Laravel 404 su tutte le route | `mod_rewrite` non abilitato o `.htaccess` non trovato | Verifica `AllowOverride All` e `LoadModule rewrite_module` |
| Conflitto con porta 80 occupata | IIS o altro processo su porta 80 | `netstat -ano \| findstr :80` poi Task Manager per kill |
| Windows Defender blocca Artisan | XAMPP in percorso non escluso | Aggiungi `C:\xampp` alle esclusioni Defender |

[^1_2][^1_1]

***

## Prossimi Passi

Una volta che Apache risponde su `http://windeploy.local.api`, installa Laravel nel backend:

```powershell
cd C:\xampp\htdocs\windeploy\backend
composer create-project laravel/laravel . "12.*"
```

Poi configura il `.env`:

```
APP_URL=http://windeploy.local.api
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=windeploy_db
DB_USERNAME=windeploy_user
DB_PASSWORD=wdeploy123!
```

<span style="display:none">[^1_3]</span>

<div align="center">⁂</div>

[^1_1]: 0003-setupiniziale.md

[^1_2]: 0002-ricerca-iniziale.md

[^1_3]: 0001-projectdescription.txt

