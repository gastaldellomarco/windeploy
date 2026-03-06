<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

## Download e Installazione XAMPP

XAMPP è ideale per sviluppo locale di WinDeploy su Windows 11, ma non è nativo per PHP 8.3: l'ultima versione stabile (8.2.12 o simile nel 2026) include PHP 8.2; aggiorna manualmente PHP dopo l'installazione per Laravel 11.[^1_1][^1_2][^1_3]

**Checklist Installazione:**

- Scarica l'ultima XAMPP per Windows x64 da https://www.apachefriends.org/download.html (scegli installer .exe, ~170MB).[^1_2][^1_4]
- Esegui come Amministratore (UAC Sì).
- Seleziona: Apache, MySQL, phpMyAdmin, PHP (Composer non incluso, installalo separato).
- Cartella: C:\xampp (standard, evita spazi/programmi; se C: protetta, usa C:\sviluppo\xampp).
- Uncheck "Learn more about Bitnami", Next > Installa.
- Post-install: Avvia XAMPP Control Panel, start Apache/MySQL. Se porta 80/443 occupata (IIS/Hyper-V): Config > Service/Ports > Cambia Apache a 8080/8443, MySQL 3307. Disabilita IIS via "Turn Windows features off/on".[^1_5][^1_1]

**Rischi Windows 11:** Porte occupate comuni (80 da World Wide Web Publishing), Windows Defender blocca file (escludi C:\xampp), UAC blocca script. Trade-off: XAMPP gratuito/simple vs Laravel Herd (pay, auto-PHP8.3/multi-domain).[^1_6][^1_1]

## Configurazione PHP 8.3

Verifica versione: Browser > http://localhost/dashboard > PHP Info (o php -v in XAMPP shell). XAMPP default PHP 8.2; per 8.3 scarica NTS VC16 x64 da https://windows.php.net/download/\#php-8.3, estrai in C:\xampp\php83_temp.[^1_3][^1_7][^1_1]

**Aggiornamento PHP 8.3 (manuale, consigliato per Laravel 11):**

1. Backup C:\xampp\php (rinomina php_old).
2. Copia php83_temp/* in C:\xampp\php.
3. Copia da backup: php.ini, php_for_Apache.dll (o php8apache2_4.dll), extras (opcache, etc.).
4. Modifica C:\xampp\apache\conf\httpd.conf: LoadModule php_module "C:/xampp/php/php8apache2_4.dll", DirectoryIndex index.php.
5. Riavvia Apache. Verifica http://localhost/phpinfo.php (?><?php phpinfo();).

**Estensioni Laravel 11 (obbligatorie):** Apri php.ini (XAMPP Control > Apache > Config > PHP (php.ini)). Togli ; da:

```
extension=mbstring
extension=xml
extension=curl
extension=mysqli (o pdo_mysql)
extension=zip
extension=bcmath
extension=json
extension=tokenizer
extension=openssl
extension=intl
extension=pdo_mysql
```

Aggiungi se mancanti. Riavvia Apache.[^1_8][^1_9][^1_1]

**Impostazioni Sviluppo:**

```
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

Salva, riavvia. Verifica con phpinfo().[^1_10]

**Sicurezza:** Esposizione estensioni aumenta superficie attacco; disabilita in prod. Rischio: PHP 8.3 deprecazioni (es. dynamic properties); testa Laravel compatibilità.[^1_1]

## MySQL in XAMPP

Default: root senza password (insicuro!), porta 3306. Verifica conflitti: netstat -ano | findstr 3306 (Task Manager > kill se occupato). [^1_11]

**Cambia Password Root:**

1. Avvia MySQL in XAMPP Control.
2. http://localhost/phpmyadmin > User accounts > root@localhost > Edit privileges > Change password > Inserisci 'rootpass123' > Go.
O SQL tab:
```
ALTER USER 'root'@'localhost' IDENTIFIED BY 'rootpass123';
FLUSH PRIVILEGES;
```

Aggiorna config.inc.php in C:\xampp\phpMyAdmin se serve. Riavvia MySQL.[^1_11][^1_12][^1_13]

**Crea DB/Utente per WinDeploy:**

1. phpMyAdmin > Databases > Crea: windeploy_db.
2. User accounts > Add user account: windeploy_user, host localhost, password 'wdeploy123!', grant ALL on windeploy_db.
SQL alternativo:
```
CREATE DATABASE windeploy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'windeploy_user'@'localhost' IDENTIFIED BY 'wdeploy123!';
GRANT ALL PRIVILEGES ON windeploy_db.* TO 'windeploy_user'@'localhost';
FLUSH PRIVILEGES;
```

Test: mysql -u windeploy_user -p windeploy_db.[^1_14]

**Rischi:** Password vuota espone DB locale; usa strong pw, firewall. Non scalabile per prod (usa Ubuntu MySQL).[^1_1]

## Virtual Host (Consigliato)

Separa React (porta 3000 Vite) da Laravel API (Apache). Struttura cartelle:

```
C:\xampp\htdocs\windeploy\
├── backend\ (Laravel: composer create-project laravel/laravel backend)
│   └── public\
├── frontend\ (React: npm create vite@latest frontend -- --template react)
```

**Config Apache (httpd-vhosts.conf):**

1. Abilita: C:\xampp\apache\conf\httpd.conf, togli \# da Include conf/extra/httpd-vhosts.conf.
2. C:\xampp\apache\conf\extra\httpd-vhosts.conf (backup prima):
```
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/windeploy/frontend"
    ServerName windeploy.local
    <Directory "C:/xampp/htdocs/windeploy/frontend">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/windeploy/backend/public"
    ServerName windeploy.local.api
    <Directory "C:/xampp/htdocs/windeploy/backend/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs"
    ServerName localhost
</VirtualHost>
```

3. Windows hosts: Ammin > C:\Windows\System32\drivers\etc\hosts (backup):
```
127.0.0.1 windeploy.local
127.0.0.1 windeploy.local.api
```

4. Riavvia Apache. Test: http://windeploy.local (React), http://windeploy.local.api (Laravel).

**Vite Proxy (frontend/vite.config.js):**

```js
export default {
  server: {
    proxy: {
      '/api': {
        target: 'http://windeploy.local.api',
        changeOrigin: true,
        secure: false,
      }
    }
  }
};
```

npm run dev.[^1_15][^1_16][^1_1]

**Rischi:** Hosts errati causano loop; permessi Directory (Windows difesi). Avvocato diavolo: Virtual host ok dev, ma Vite+php artisan serve più veloce/no Apache overhead. Conflitti HTTPS futuri.[^1_1]

## Composer

**Installa/Integra con XAMPP PHP:**

1. Scarica Composer-Setup.exe da https://getcomposer.org/download/.
2. Esegui: Seleziona php.exe = C:\xampp\php\php.exe > Install.
3. PATH: %APPDATA%\Composer\vendor\bin + C:\xampp\php.
4. Verifica: composer --version; composer diagnose (deve mostrare PHP da C:\xampp\php).

**Test PHP XAMPP:** php -v (in CMD nuovo). Crea Laravel: cd C:\xampp\htdocs\windeploy\backend; composer create-project laravel/laravel .[^1_17][^1_18][^1_1]

**Trade-off:** Globale ok dev multi-progetto, ma rischio versioni PHP multiple (usa composer.phar locale: php composer.phar install). Non scalabile prod (usa Ubuntu). Escludi vendor da Defender.[^1_1]

**Prossimi Passi WinDeploy:** .env Laravel DB_CONNECTION=mysql, DB_HOST=127.0.0.1:3306, DB_DATABASE=windeploy_db, etc. npm install Tailwind React Router. Test agent Python locale BASE_URL=http://windeploy.local.api.[^1_14][^1_1]
<span style="display:none">[^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_31][^1_32]</span>

<div align="center">⁂</div>

[^1_1]: 0002-ricerca-iniziale.md

[^1_2]: https://www.apachefriends.org/download.html

[^1_3]: https://github.com/orgs/community/discussions/155925

[^1_4]: https://www.apachefriends.org/it/download.html

[^1_5]: https://www.youtube.com/watch?v=G2VEf-8nepc

[^1_6]: https://herd.laravel.com/docs/windows/getting-started/installation

[^1_7]: https://www.youtube.com/watch?v=otZ5jvbPNro

[^1_8]: https://stackoverflow.com/questions/33611598/cant-enable-php-extensions-on-php-ini-on-app-engine-for-laravel

[^1_9]: https://gist.github.com/bradtraversy/7485f928e3e8f08ee6bccbe0a681a821?permalink_comment_id=5000359

[^1_10]: https://herd.laravel.com/docs/windows/technology/php-settings

[^1_11]: https://dev.mysql.com/doc/en/resetting-permissions.html

[^1_12]: https://www.heelpbook.net/2018/change-the-root-password-for-mysql-xampp/

[^1_13]: https://stackoverflow.com/questions/24566453/resetting-mysql-root-password-with-xampp-on-localhost

[^1_14]: 0001-projectdescription.txt

[^1_15]: https://stackoverflow.com/questions/27268205/how-to-create-virtual-host-on-xampp

[^1_16]: https://pixelative.co/how-to-setup-apache-virtual-hosts-using-xampp-in-windows/

[^1_17]: https://laravel.com/docs/7.x

[^1_18]: https://github.com/composer/windows-setup/issues/72

[^1_19]: https://laravel.com/docs/8.x

[^1_20]: https://dev.mysql.com/doc/

[^1_21]: https://certbot.eff.org/instructions?ws=other\&os=windows

[^1_22]: https://dev.mysql.com/doc/refman/8.0/ja/server-system-variables.html

[^1_23]: https://herd.laravel.com/docs/macos/technology/php-settings

[^1_24]: https://dev.mysql.com/doc/search/?q=Fatal+error%3A+Maximum+execution+time+of+300+seconds+exceeded+in+C%3A\xampp\phpMyAdmin\libraries\dbi\DBIMysqli.class.php+on+line+261%2F1000\&d=12\&p=139

[^1_25]: https://herd.laravel.com/docs/windows/advanced-usage/directories-and-files

[^1_26]: https://dev.mysql.com/doc/search/?q=Fatal+error%3A+Maximum+execution+time+of+300+seconds+exceeded+in+C%3A\xampp\phpMyAdmin\libraries\dbi\DBIMysqli.class.php+on+line+261%2F1000\&d=12\&p=132

[^1_27]: https://herd.laravel.com/docs/windows/technology/php-versions

[^1_28]: https://herd.laravel.com/docs/windows/debugging/dumps

[^1_29]: https://dev.mysql.com/doc/search/?q=Fatal+error%3A+Maximum+execution+time+of+300+seconds+exceeded+in+C%3A\xampp\phpMyAdmin\libraries\dbi\DBIMysqli.class.php+on+line+261\&d=12\&p=164

[^1_30]: https://www.youtube.com/watch?v=SFGOYBlArhQ\&vl=it

[^1_31]: https://sourceforge.net/projects/xampp/files/XAMPP Windows/8.0.3/xampp-windows-x64-8.0.3-0-VS16.zip/download

[^1_32]: https://www.c-sharpcorner.com/article/installing-and-configuring-xampp-on-windows-11/

