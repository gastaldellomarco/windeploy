<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sto sviluppando WinDeploy in locale su XAMPP (Windows) per poi deployare su

Ubuntu Server 24.04 LTS con Nginx + Cloudflare Tunnel (cloudflared).
 
Verifica versioni ATTUALI e segnala breaking change per tutto lo stack:
 
═══ XAMPP ═══

- Versione XAMPP attuale per Windows? Quale PHP include di default?
- XAMPP include PHP 8.3? O devo aggiornare manualmente il modulo PHP?
- Come si aggiunge Composer a XAMPP su Windows (PATH, ecc.)?
- Laravel funziona con XAMPP o è meglio usare il server built-in (php artisan serve)?
- phpMyAdmin in XAMPP: versione inclusa? È compatibile con Laravel 11 + MySQL 8?
- Problemi noti tra XAMPP e Laravel 11 su Windows 11?
- Virtual host in XAMPP (windeploy.local): si configura in httpd-vhosts.conf? Come?
 
═══ LARAVEL ═══
- Laravel: versione corrente (11 o 12)? Quale consigliata per nuovi progetti 2026?
- laravel/sanctum: versione attuale compatibile?
- tymon/jwt-auth: ancora mantenuto nel 2026? Alternative (paseto, laravel-jwt)?
- spatie/laravel-permission: versione attuale?
- Con XAMPP su Windows, ci sono problemi con le migration o i comandi Artisan?
 
═══ REACT + VITE ═══
- React: 18 o 19 stabile nel 2026?
- Vite: versione attuale? Il proxy verso Laravel in vite.config.js è cambiato?
- Tailwind CSS: v3 o v4? Breaking change importanti?
- React Router: v6 o v7? Cosa cambia?
- Come configurare Vite per puntare all'API Laravel su XAMPP (localhost:80/api)?
 
═══ AGENT PYTHON (Windows) ═══
- Python 3.11 o 3.12 per PyInstaller su Windows 11?
- CustomTkinter: versione attuale?
- PyInstaller su Windows: problemi con Windows Defender / false positive?
- Per i test locali dell'agent, come configurarlo per puntare a http://localhost invece di [https://windeploy.mavcoo.it?](https://windeploy.mavcoo.it?)
 
═══ CLOUDFLARE TUNNEL (per il deploy finale) ═══
- cloudflared: versione stabile 2026?
- La struttura config.yml è cambiata rispetto al 2024?
- Con cloudflared, Nginx deve ascoltare su localhost:80 o 127.0.0.1:80?
 
Per ogni punto: versione ESATTA consigliata, problemi noti, e alternative se esistono.

**Ecco le versioni attuali (febbraio 2026) dello stack WinDeploy, con breaking changes, configurazioni consigliate e rischi noti. Ho verificato compatibilità con sviluppo locale XAMPP (Windows 11) e deploy Ubuntu 24.04 LTS + Nginx + Cloudflare Tunnel. Per nuovi progetti, usa Laravel 12 (LTS fino 2027), React 19, Tailwind v4 (beta stabile).**[^1_1][^1_2][^1_3][^1_4]

## XAMPP (Windows)

**Versione attuale:** XAMPP 8.2.12 (include PHP 8.2.12, Apache 2.4, MySQL 8.0.33, phpMyAdmin 5.2.1). Nessuna release ufficiale con PHP 8.3+ nativa; aggiorna manualmente PHP da windows.php.net (non-thread-safe, VC16 x64).[^1_5][^1_6][^1_7]

**PHP 8.3 in XAMPP:** Non incluso di default. Scarica PHP 8.3.12 NTS, sostituisci cartella `C:\xampp\php`, aggiorna `httpd.conf` (LoadModule php_module "C:/xampp/php/php8apache2_4.dll"). Backup prima! Rischio: estensioni mancanti (es. intl, gd); reinstalla via `php.ini`.[^1_8][^1_9]

**Aggiungere Composer:**

```
1. Scarica Composer-Setup.exe da getcomposer.org.
2. Esegui installer, seleziona php.exe da C:\xampp\php\php.exe.
3. Aggiungi %APPDATA%\Composer\vendor\bin a PATH utente (Sistema > Variabili ambiente).
```

Test: `composer --version`. Alternativa: `php composer.phar` da XAMPP/php.[^1_10][^1_11]

**Laravel con XAMPP vs artisan serve:** Funziona, ma usa virtual host (non artisan serve, instabile per API). Configura `httpd-vhosts.conf`:

```
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/windeploy-backend/public"
    ServerName windeploy.local
    <Directory "C:/xampp/htdocs/windeploy-backend/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Aggiungi `127.0.0.1 windeploy.local` a `C:\Windows\System32\drivers\etc\hosts`. Riavvia Apache.[^1_12]

**phpMyAdmin:** v5.2.1 inclusa, compatibile Laravel 11/12 + MySQL 8 (supporta JSON, enum). Problemi: sessioni su Windows 11 (fix: `php.ini` session.save_path).[^1_5]

**Problemi noti Laravel 11/12 + XAMPP Win11:** Rari; migration Artisan OK se PATH Composer corretto. Issue: permessi file (usa `icacls`), Windows Defender blocca Artisan (escludi htdocs). Meglio Laravel Herd (PHP 8.2-8.5, auto-domain). Trade-off: XAMPP gratuito/simple, ma Herd più scalabile/no-config.[^1_13][^1_14]

**Checklist setup XAMPP:**

- [ ] Installa/aggiorna XAMPP 8.2.12+.
- [ ] Virtual host per backend/frontend separati.
- [ ] Composer in PATH.
- [ ] Escludi XAMPP da Defender.


## Laravel

**Versione corrente:** Laravel 12 (ril. Feb 2025, bugfix fino Ago 2026, security Feb 2027; PHP 8.2-8.5). Consigliata per nuovi: **Laravel 12** (LTS, Reverb WebSocket nativo per monitor realtime WinDeploy). Da `composer create-project laravel/laravel backend 12.*`.[^1_15][^1_3][^1_4]

**laravel/sanctum:** v4.3.1 (perfetto per SPA React + agent API).[^1_16]

**tymon/jwt-auth:** Non mantenuto (fork PHPOpenSourceSaver/jwt-auth). Alternative: **Sanctum** (stateless SPA/mobile), Passport (OAuth), o Laravel Passport per agent. Per WinDeploy: Sanctum + abilities per ruoli.[^1_17][^1_18]

**spatie/laravel-permission:** v6.x (aggiorna migrations; metodi chain return `static`).[^1_19]

**Problemi XAMPP + Artisan:** Migration falliscono se storage/ non scrivibile (chmod 775). Fix: `php artisan storage:link`, `.env APP_URL=http://windeploy.local`. Scalabile? Sì locale, ma deploy Nginx.[^1_12]

**Trade-off:** Laravel 12 scalabile/sicuro (rate limiting/sec), ma migration da 11 minima (solo bootstrap/app.php). Rischio: PHP 8.5 deprecations.

## React + Vite

**React:** v19.2.1 stabile (concurrent features, compiler).[^1_20][^1_1]

**Vite:** v6.x (latest 2026). Proxy API: `vite.config.js`:

```js
// Structure: project-root/vite.config.js
export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: 'http://windeploy.local',  // o localhost:80
        changeOrigin: true,
        secure: false,
      },
    },
  },
});
```

No breaking changes proxy.[^1_2]

**Tailwind CSS:** v4.0 beta (stabile 2026; OKLCH colors, no content[] config, Vite plugin `@tailwindcss/vite`). Breaking: hover solo su hover:hover, 3D transforms. Upgrade: `npm i tailwindcss@next @tailwindcss/vite@next`.[^1_21][^1_22][^1_2]

**React Router:** v7 (da v6; framework mode, type safety, server rendering). `npm i react-router-dom@7`.[^1_23]

**Checklist Vite + XAMPP:**

- [ ] `npm create vite@latest frontend -- --template react-ts`.
- [ ] Proxy /api -> windeploy.local.
- [ ] Tailwind v4 plugin.

**Rischio:** Tailwind v4 auto-detect classes (no purge issues), ma test build.

## Agent Python (Windows)

**Python:** 3.12 (LTS, PyInstaller stabile). Non 3.11 (EOL).[^1_24]

**CustomTkinter:** v5.2+ (latest GitHub).[^1_25]

**PyInstaller:** v6.9+ (Windows Defender false positive comuni). Fix: escludi .exe da Defender (Virus \& threat > Esclusioni > Aggiungi .exe). Usa `--onefile --windowed --add-data "customtkinter;customtkinter"`.[^1_26][^1_24]

**Config locale agent:** Modifica codice Python:

```python
# Structure: agent/main.py
BASE_URL = "http://localhost/api"  # Invece di https://windeploy.mavcoo.it/api
# Test: if __name__ == "__main__": requests.get(f"{BASE_URL}/health")
```

Compila: `pyinstaller --onefile main.py`. Sicurezza: HTTPS solo deploy; locale HTTP OK (no prod).[^1_25]

**Rischio:** Defender blocca exe (firma digitale futura). Trade-off: CustomTkinter moderno vs Tkinter nativo (no deps).

## Cloudflare Tunnel (Deploy)

**cloudflared:** v2026.x stabile (no major changes config.yml da 2024).[^1_27]

**config.yml:** Invariata:

```yaml
# /etc/cloudflared/config.yml
tunnel: <tunnel-id>
credentials-file: /etc/cloudflared/<tunnel-id>.json
ingress:
  - hostname: windeploy.mavcoo.it
    service: http://localhost:80
  - service: http_status:404
```

Avvia: `cloudflared tunnel run`.[^1_27]

**Nginx + cloudflared:** Nginx su `listen 127.0.0.1:80;` (non 0.0.0.0). Cloudflare proxy localhost. Struttura deploy:

```
/var/www/windeploy/
├── frontend/dist/  # Nginx static
├── backend/        # Laravel public -> Nginx alias
└── agent/agent.exe
```

**Sicurezza:** HTTPS forzato, Cloudflare WAF. Rischio: Tunnel downtime se cloudflared crash (systemd service).[^1_28]

**Avvocato del diavolo:** XAMPP ok prototipo, ma non prod (no containerizzazione). Laravel 12 solido, ma Sanctum > JWT (mantenuto). Tailwind v4 breaking se v3 heavy-custom. Agent: test su Win11/10 vari (UAC). Deploy: Dockerizza per scalabilità futura. Costi: Cloudflare free tier ok 100k req/giorno.
<span style="display:none">[^1_29][^1_30][^1_31][^1_32][^1_33][^1_34][^1_35][^1_36][^1_37][^1_38][^1_39][^1_40][^1_41][^1_42][^1_43][^1_44][^1_45][^1_46][^1_47][^1_48][^1_49][^1_50]</span>

<div align="center">⁂</div>

[^1_1]: https://react.dev/versions

[^1_2]: https://v3.tailwindcss.com/docs/v4-beta

[^1_3]: https://laravel.com/docs/12.x/releases

[^1_4]: https://itmarkerz.co.in/blog/laravel-12-for-enterprise-2026-what-changed-what-to-upgrade-what-to-ignore

[^1_5]: https://www.apachefriends.org/download.html

[^1_6]: https://github.com/orgs/community/discussions/103059

[^1_7]: https://sourceforge.net/projects/xampp/files/XAMPP Windows/

[^1_8]: https://dev.to/kansoldev/how-to-change-php-version-in-xampp-for-windows-4klb

[^1_9]: https://github.com/orgs/community/discussions/155925

[^1_10]: https://getcomposer.org/doc/00-intro.md

[^1_11]: https://stackoverflow.com/questions/25549177/change-composer-global-path-windows

[^1_12]: https://weeklyhow.com/installing-laravel-8-with-xampp/

[^1_13]: https://herd.laravel.com/docs/windows/migration-guides/xampp

[^1_14]: https://www.reddit.com/r/PHP/comments/1r58lxd/xampp_in_2026/

[^1_15]: https://laravel.com/docs/12.x/deployment

[^1_16]: https://github.com/laravel/sanctum/releases

[^1_17]: https://laravel.com/docs/12.x/sanctum

[^1_18]: https://laravel-news.com/package/php-open-source-saver-jwt-auth

[^1_19]: https://spatie.be/docs/laravel-permission/v7/upgrading

[^1_20]: https://react.dev/blog

[^1_21]: https://stackoverflow.com/questions/79624339/tailwind-v4-utilities-not-generating-with-vite-tailwindcss-vite-plugin

[^1_22]: https://tailwindcss.com/blog/tailwindcss-v4

[^1_23]: https://remix.run/blog/react-router-v7

[^1_24]: https://pyinstaller.org/en/stable/CHANGES.html

[^1_25]: https://github.com/TomSchimansky/CustomTkinter/issues/517

[^1_26]: https://www.youtube.com/watch?v=9lD93k5kxtE

[^1_27]: https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/do-more-with-tunnels/local-management/configuration-file/

[^1_28]: 0001-projectdescription.txt

[^1_29]: https://laravel.com/docs/11.x/releases

[^1_30]: https://laravel.com/docs/8.x

[^1_31]: https://dev.mysql.com/doc/

[^1_32]: https://laravel.com/docs/7.x

[^1_33]: https://laravel.com/docs/10.x/releases

[^1_34]: https://cloud.laravel.com/docs/changelog

[^1_35]: https://laravel.com/docs/12.x/authorization

[^1_36]: https://herd.laravel.com/docs/windows/advanced-usage/command-line

[^1_37]: https://www.youtube.com/watch?v=SFGOYBlArhQ\&vl=it

[^1_38]: https://www.apachefriends.org/it/index.html

[^1_39]: https://www.mindpathtech.com/blog/reactjs-trends/

[^1_40]: https://www.youtube.com/watch?v=vOP3MwvFXfc

[^1_41]: https://sourceforge.net/projects/xampp/

[^1_42]: https://herd.laravel.com/docs/windows/getting-started/installation

[^1_43]: https://react.dev/blog/2025/12/03/critical-security-vulnerability-in-react-server-components

[^1_44]: https://herd.laravel.com/docs/windows/advanced-usage/directories-and-files

[^1_45]: https://herd.laravel.com/docs/windows/troubleshooting/common-issues

[^1_46]: https://he.react.dev/blog/2023/05/03/react-canaries

[^1_47]: https://tailwindcss.com/docs/upgrade-guide

[^1_48]: https://www.apachefriends.org/it/download.html

[^1_49]: https://xampp-windows.en.softonic.com/download

[^1_50]: https://www.youtube.com/watch?v=iVvtHVL_raA

