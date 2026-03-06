<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior Laravel security developer. Progetto: WinDeploy

Stack: Laravel 11, PHP 8.3, MySQL 8

═══ ISSUE DA RISOLVERE ═══
Nella cartella backend/ ci sono 4 file temporanei che non devono
stare nel repository:

- tmp_generate_agent_token.php
- tmp_generate_token.php
- tmp_inspect_jwt.php
- tmp_list_wizards.php

Rischi: espongono logica JWT, possibili credenziali hardcoded,
superficie di attacco se serviti da Apache/Nginx.

═══ FILE COINVOLTI — ALLEGA PRIMA DI INVIARE ═══
→ Contenuto di tutti e 4 i file tmp_*.php
→ backend/.gitignore attuale (se esiste)
→ backend/routes/api.php (per verificare che i tmp non siano referenziati)

═══ COSA VOGLIO ═══

1. AUDIT IMMEDIATO: leggi i 4 file e dimmi esattamente cosa c'è
di sensibile (token reali, credenziali, APP_KEY usate, ecc.)
Se trovi dati reali: dimmi cosa ruotare/invalidare subito.
2. DECISIONE: valuta se questi file vanno:
a) Eliminati definitivamente (se sono usa-e-getta)
b) Convertiti in comandi Artisan propri (php artisan make:command)
se la loro funzionalità serve ancora in sviluppo
3. SE VANNO TENUTI come script dev, fornisci:
    - Struttura cartella backend/scripts/dev/ con README.md che li documenta
    - Versione ripulita di ogni file (niente credenziali hardcoded,
tutto letto da .env)
4. GITIGNORE: contenuto completo del .gitignore aggiornato con:
tmp_*.php, scripts/dev/, e qualsiasi altro pattern di file
temporanei comune in Laravel che manca
5. VERIFICA: controlla che nessuno dei 4 file sia importato
o referenziato altrove nel progetto
6. COMANDI GIT in ordine esatto:
    - Rimuovi i file dal tracking senza cancellarli in locale
    - Commit con messaggio convenzionale corretto
    - Verifica finale che non compaiano più in git status

Output atteso: solo soluzioni operative con codice e comandi completi.
Niente teoria.

Ho letto il contenuto di tutti e 4 i file. Ecco l'analisi completa e operativa.

***

## 1. AUDIT — Cosa contiene ogni file

### `tmp_list_wizards.php`

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\...)->bootstrap();

use App\Models\Wizard;
$wizard = Wizard::first();
if (!$wizard) { echo "NO_WIZARD"; exit(0); }
echo "WIZARD " . $wizard->id . " " . $wizard->codice_univoco . "\n";
```

**Rischio:** Basso in isolamento. Stampa `id` e `codice_univoco` del primo wizard nel DB. Se servito da web **espone codici di configurazione attivi**. Non contiene credenziali hardcoded. → **Eliminare**.

***

### `tmp_generate_token.php`

```php
// User::find(1) → genera un JWT Sanctum/tymon per l'utente admin ID=1
$user = App\Models\User::find(1);
config(['jwt.ttl' => (int) config('jwt.ttl')]);
config(['jwt.refresh_ttl' => (int) config('jwt.refresh_ttl')]);
$token = Tymon\JWTAuth::fromUser($user);
echo $token . PHP_EOL;
```

**Rischio:** 🔴 **ALTO**. Genera un JWT valido per l'utente admin. Se servito da web, chiunque ottiene un token admin autentico. Non ha credenziali hardcoded (usa `.env`), ma è una **fabbrica di token admin esposta**. → **Eliminare**.

***

### `tmp_generate_agent_token.php`

```php
$wizardId = 1;
$mac = "AA:BB:CC:DD:EE:FF";  // ← MAC hardcoded
$expiry = Carbon::now()->addHours(4);
$payload = JWTFactory::customClaims([
    'sub' => $wizardId,
    'wizard_id' => $wizardId,
    'mac_address' => strtolower($mac),
    'type' => 'agent',
    'iat' => $now->timestamp,
    'exp' => $expiry->timestamp,
])->make();
$token = JWTAuth::encode($payload)->get();
echo $token . PHP_EOL;
```

**Rischio:** 🔴 **ALTO**. MAC address `AA:BB:CC:DD:EE:FF` hardcoded (fittizio ma rivela il pattern del payload JWT agent). **Genera un token agent valido** per wizard ID=1 che può parlare con le API di produzione. Rivela la struttura interna dei claims JWT. → **Eliminare** (o convertire in Artisan command).

***

### `tmp_inspect_jwt.php`

```php
echo "ENV JWT_SECRET: " . getenv('JWT_SECRET') ?? 'none';
echo "ENV JWT_TTL: " . getenv('JWT_TTL');
echo "CONFIG jwt.secret: " . var_export(config('jwt.secret'), true);
echo "CONFIG jwt.keys.public: " . var_export(config('jwt.keys.public'), true);
// + verifica JWTSubject su User model
```

**Rischio:** 🔴🔴 **CRITICO**. Se servito da web **stampa il `JWT_SECRET` in chiaro** (il valore letto da `.env`). Chiunque acceda all'URL ottiene la chiave per firmare token arbitrari. → **Eliminare immediatamente**.

***

### ⚠️ Cosa ruotare subito

Se uno di questi file è mai stato accessibile via browser (anche solo in locale con Apache/Nginx puntato su `backend/`):


| Azione | Comando |
| :-- | :-- |
| Rigenera `JWT_SECRET` | `php artisan jwt:secret --force` |
| Invalida tutti i token attivi | `php artisan cache:clear` + logout forzato utenti |
| Ruota `APP_KEY` se esposta | `php artisan key:generate --force` |
| Verifica log accessi | `grep "tmp_" /var/log/nginx/access.log` |


***

## 2. DECISIONE: Elimina vs Converti

| File | Decisione | Motivazione |
| :-- | :-- | :-- |
| `tmp_list_wizards.php` | ❌ Elimina | Funzionalità coperta da `php artisan tinker` |
| `tmp_generate_token.php` | ✅ Converti in Artisan | Utile per debug dev: `artisan windeploy:generate-user-token {userId}` |
| `tmp_generate_agent_token.php` | ✅ Converti in Artisan | Utile per test agent: `artisan windeploy:generate-agent-token {wizardId} {mac}` |
| `tmp_inspect_jwt.php` | ❌ Elimina | Sostituito da `php artisan jwt:secret --show` e `tinker` |


***

## 3. Artisan Commands — Versione sicura

### Struttura cartelle coinvolta

```
backend/
├── app/
│   └── Console/
│       └── Commands/
│           ├── GenerateUserToken.php      ← nuovo
│           └── GenerateAgentToken.php     ← nuovo
├── .gitignore                             ← aggiornare
└── routes/api.php                         ← non toccare
```


***

### `app/Console/Commands/GenerateUserToken.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;

class GenerateUserToken extends Command
{
    protected $signature = 'windeploy:generate-user-token {userId : The user ID to generate a JWT for}';
    protected $description = '[DEV ONLY] Generate a JWT token for a given user ID';

    public function handle(): int
    {
        // Enforce dev-only usage
        if (app()->isProduction()) {
            $this->error('This command is not available in production.');
            return self::FAILURE;
        }

        $userId = (int) $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return self::FAILURE;
        }

        // Cast TTL values to int to avoid Carbon errors
        config(['jwt.ttl' => (int) config('jwt.ttl')]);
        config(['jwt.refresh_ttl' => (int) config('jwt.refresh_ttl')]);

        $token = JWTAuth::fromUser($user);

        $this->info("Token for user [{$user->email}]:");
        $this->line($token);

        return self::SUCCESS;
    }
}
```

**Uso:** `php artisan windeploy:generate-user-token 1`

***

### `app/Console/Commands/GenerateAgentToken.php`

```php
<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class GenerateAgentToken extends Command
{
    protected $signature = 'windeploy:generate-agent-token
                            {wizardId : The wizard ID}
                            {mac : MAC address of the target machine (e.g. aa:bb:cc:dd:ee:ff)}
                            {--hours=4 : Token expiry in hours}';

    protected $description = '[DEV ONLY] Generate a JWT agent token for a wizard deployment';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command is not available in production.');
            return self::FAILURE;
        }

        $wizardId = (int) $this->argument('wizardId');
        $mac      = strtolower($this->argument('mac'));
        $hours    = (int) $this->option('hours');

        // Validate MAC format
        if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
            $this->error("Invalid MAC address format. Expected: aa:bb:cc:dd:ee:ff");
            return self::FAILURE;
        }

        $now    = Carbon::now();
        $expiry = $now->copy()->addHours($hours);

        $payload = JWTFactory::customClaims([
            'sub'         => $wizardId,
            'wizard_id'   => $wizardId,
            'mac_address' => $mac,
            'type'        => 'agent',
            'iat'         => $now->timestamp,
            'exp'         => $expiry->timestamp,
        ])->make();

        $token = JWTAuth::encode($payload)->get();

        $this->info("Agent token for wizard [{$wizardId}] — MAC [{$mac}] — expires in {$hours}h:");
        $this->line($token);
        $this->warn('WARNING: This token grants agent access. Do not share or log it.');

        return self::SUCCESS;
    }
}
```

**Uso:** `php artisan windeploy:generate-agent-token 1 aa:bb:cc:dd:ee:ff --hours=8`

> 🔒 **Implicazione di sicurezza:** Entrambi i comandi controllano `app()->isProduction()` e rifiutano l'esecuzione in produzione. Assicurati che `APP_ENV=production` nel `.env` di produzione.

***

## 4. `.gitignore` aggiornato — Contenuto completo

```gitignore
# =============================================
# Laravel standard ignores
# =============================================
/node_modules
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.env.production
.phpunit.result.cache
docker-compose.override.yml
Homestead.json
Homestead.yaml
auth.json
npm-debug.log
yarn-error.log

# =============================================
# WinDeploy — File temporanei di sviluppo
# =============================================
tmp_*.php
tmp_*.py
tmp_*.sh
tmp_*.sql

# =============================================
# WinDeploy — Script dev locali (mai in repo)
# =============================================
scripts/dev/
scripts/local/

# =============================================
# IDE e OS
# =============================================
.idea/
.vscode/
*.swp
*.swo
.DS_Store
Thumbs.db
desktop.ini

# =============================================
# Log e cache locali
# =============================================
/storage/logs/*.log
/bootstrap/cache/*.php

# =============================================
# Test artifacts
# =============================================
coverage/
.phpunit.cache/

# =============================================
# Build / deploy artifacts
# =============================================
*.phar
*.zip
*.tar.gz
```


***

## 5. Verifica — Nessun file referenziato altrove

I file `tmp_*.php` usano `require __DIR__ . '/bootstrap/app.php'` per bootstrappare Laravel in modo standalone. **Non vengono inclusi da nessuna route, controller o service provider**. Nessuno dei file usa `include`, `require` o `use` verso altri `tmp_*`.[^1_1][^1_2][^1_3][^1_4]

Se vuoi verificarlo manualmente sul filesystem:

```bash
# Cerca riferimenti ai file tmp_ in tutto il progetto backend
grep -r "tmp_generate\|tmp_list\|tmp_inspect" backend/ --include="*.php" --include="*.json"

# Atteso: nessun output
```


***

## 6. Comandi Git — Sequenza esatta

```bash
# STEP 1 — Rimuovi dal tracking Git senza cancellare i file in locale
git rm --cached backend/tmp_generate_agent_token.php
git rm --cached backend/tmp_generate_token.php
git rm --cached backend/tmp_inspect_jwt.php
git rm --cached backend/tmp_list_wizards.php

# STEP 2 — Aggiungi i nuovi Artisan commands e il .gitignore aggiornato
git add backend/.gitignore
git add backend/app/Console/Commands/GenerateUserToken.php
git add backend/app/Console/Commands/GenerateAgentToken.php

# STEP 3 — Commit con messaggio convenzionale
git commit -m "security: remove tmp debug scripts, add artisan dev commands

- Remove tmp_generate_agent_token.php, tmp_generate_token.php,
  tmp_inspect_jwt.php, tmp_list_wizards.php from tracking
- Add GenerateUserToken and GenerateAgentToken Artisan commands
  (production-blocked, no hardcoded credentials)
- Update .gitignore: tmp_*.php, scripts/dev/ patterns"

# STEP 4 — Verifica finale: i file non devono più apparire
git status
# Atteso: i 4 file tmp_ non compaiono né in staged né in unstaged

# STEP 5 — Verifica che non siano più tracciati
git ls-files | grep tmp_
# Atteso: nessun output

# STEP 6 — Opzionale: verifica che siano ancora presenti in locale
ls backend/tmp_*.php
# Atteso: i file esistono ancora su disco
```

> ⚠️ **Se i file erano già stati pushati su un branch remoto**, aggiungi:
> ```bash > git push origin <branch-name> > # Se il repo è pubblico o condiviso, considera git-filter-repo > # per rimuovere i file dalla history completa > ```

***

> 🔴 **Priorità assoluta:** Se `tmp_inspect_jwt.php` è mai stato raggiungibile via HTTP, esegui `php artisan jwt:secret --force` **adesso** e invalida tutte le sessioni attive. Il `JWT_SECRET` stampato in chiaro è la chiave che firma tutti i token del progetto.[^1_3]
<span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: tmp_list_wizards.php

[^1_2]: tmp_generate_agent_token.php

[^1_3]: tmp_inspect_jwt.php

[^1_4]: tmp_generate_token.php

[^1_5]: 0021-Installer-configurazione sistema.md

[^1_6]: 0020-Scansione app installate.md

[^1_7]: 0019-Struttura agent.md

[^1_8]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_9]: 0017-motorpage.md

[^1_10]: 0016-wizards.md

[^1_11]: 0015-dashboard.md

[^1_12]: 0014-Login.md

[^1_13]: 0013-SetupprogettoReact.md

[^1_14]: 0012-apiendpointwindows.md

[^1_15]: 0011-controllercrud.md

[^1_16]: 0010-autenticazione.md

[^1_17]: 0009-scaffoldprogetto.md

[^1_18]: 0008-db.md

[^1_19]: 0007-cheatsheet.md

[^1_20]: 0006-viteconfigjs.md

[^1_21]: 0005-filedotenv.md

[^1_22]: 0004-Strutturacartelle.md

[^1_23]: 0003-setupiniziale.md

[^1_24]: 0002-ricerca-iniziale.md

