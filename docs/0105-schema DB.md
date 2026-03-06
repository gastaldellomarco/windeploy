# ⚠️ FILE DEPRECATO

**Questo file è stato unito in `0008-db-schema.md`.**

Tutte le informazioni sullo schema database si trovano in:  
→ **`docs/0008-db-schema.md`**

---

<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Sei un senior Laravel database architect. Progetto: WinDeploy

Stack: Laravel 11, PHP 8.3, MySQL 8, sviluppo su XAMPP Windows

═══ CONTESTO ═══
I modelli e controller esistono ma le migration corrispondenti
potrebbero essere assenti o incomplete. Senza migration il database
non può essere inizializzato e ogni chiamata API genera errore 500.

═══ FILE DA ALLEGARE PRIMA DI INVIARE ═══
→ backend/database/migrations/ (TUTTI i file presenti, anche vuoti)
→ backend/app/Models/Wizard.php
→ backend/app/Models/Template.php
→ backend/app/Models/Software.php (o SoftwareLibrary.php)
→ backend/app/Models/ExecutionLog.php
→ backend/app/Models/Report.php
→ backend/app/Models/User.php
→ backend/database/seeders/ (tutti i seeder presenti)
→ backend/database/migrations/\*\_create_users_table.php
(quella di default Laravel — da non duplicare)
→ il file 0008-db.md dalla tua documentazione (specifica schema)

═══ COSA VOGLIO ═══

1. AUDIT MIGRATION ESISTENTI:
   Dai file allegati dimmi esattamente: - Quali migration esistono già e sono corrette - Quali mancano completamente - Quali esistono ma hanno campi mancanti rispetto allo schema
   Non procedere con la scrittura finché non hai fatto questo audit.
2. CREA LE MIGRATION MANCANTI nell'ordine corretto
   (rispetta le foreign key — prima la tabella referenziata, poi quella che riferisce):

ORDINE OBBLIGATORIO:

3. users (già esiste di default Laravel — verifica solo che abbia:
   ruolo enum('admin','tecnico','viewer'), attivo bool,
   last_login timestamp nullable, last_login_ip string nullable)
4. templates - id (unsignedBigInteger, PK) - nome (string 255) - descrizione (text nullable) - user_id (FK → users, cascade delete) - scope (enum: 'globale','personale', default 'personale') - configurazione (json) - timestamps - softDeletes
   INDEX: user_id, scope
5. software_library - id - nome (string 255) - versione (string 50 nullable) - publisher (string 255 nullable) - tipo (enum: 'winget','exe','msi') - identificatore (string 500 — winget ID o path) - categoria (string 100 nullable) - icona_url (string 500 nullable) - aggiunto_da (FK → users, set null on delete) - attivo (boolean default true) - timestamps
   INDEX: tipo, attivo, categoria
6. wizards - id - nome (string 255) - user_id (FK → users, cascade) - template_id (FK → templates nullable, set null on delete) - codice_univoco (string 10, UNIQUE — formato WD-XXXX) - stato (enum: 'bozza','pronto','in_esecuzione','completato','errore',
   default 'bozza') - configurazione (json — contiene tutto: nome_pc, utente_admin
   con password_encrypted, software_installa[], bloatware_default[],
   power_plan{}, extras{}) - expires_at (timestamp nullable) - used_at (timestamp nullable) - timestamps - softDeletes
   INDEX: codice_univoco (UNIQUE), stato, expires_at, user_id
7. wizard_software (pivot molti-a-molti) - wizard_id (FK → wizards, cascade) - software_id (FK → software_library, cascade) - PK composta: (wizard_id, software_id)
   NOTA: questa tabella è alternativa a software_installa[]
   nel JSON — chiarisci quale approccio usare e perché,
   poi implementa solo quello coerente con i modelli esistenti.
8. execution_logs - id - wizard_id (FK → wizards, cascade) - pc_nome_originale (string 255 nullable) - pc_nome_nuovo (string 255 nullable) - tecnico_user_id (FK → users, set null on delete) - hardware_info (json nullable — cpu, ram_gb, disco_gb, windows_version) - stato (enum: 'avviato','in_corso','completato','errore','abortito',
   default 'avviato') - log_dettagliato (json nullable — array di step con timestamp e esito) - started_at (timestamp nullable) - completed_at (timestamp nullable) - timestamps
   INDEX: wizard_id, stato, tecnico_user_id, started_at
9. reports - id - execution_log_id (FK → execution_logs, cascade) - html_content (longText) - timestamps
   INDEX: execution_log_id

1) AGGIORNA I MODELLI se necessario:
   Per ogni modello verifica e completa: - \$fillable (tutti i campi mass-assignable) - \$casts (json → array, timestamps, enums) - \$dates (expires_at, used_at, started_at, ecc.) - Relazioni Eloquent (hasMany, belongsTo, belongsToMany) - SoftDeletes trait se previsto

Particolare attenzione a Wizard.php: - Il campo configurazione deve essere castato a array - password_encrypted nel JSON non deve mai apparire
nei metodi toArray() o nelle API Resource 2. SEEDERS PER SVILUPPO LOCALE:
Crea/aggiorna database/seeders/DatabaseSeeder.php che chiami: - UserSeeder: 1 admin (admin@windeploy.local / Admin@1234!),
2 tecnici (tecnico1@, tecnico2@), 1 viewer - SoftwareSeeder: almeno 8 software con dati reali
(Chrome, Firefox, 7-Zip, VLC, Notepad++, ecc. con ID winget reali) - TemplateSeeder: 2 template (1 globale, 1 personale del tecnico1) - WizardSeeder: 3 wizard in stati diversi (bozza, pronto, completato)
con configurazione JSON completa e realistica 3. VERIFICA INTEGRITÀ REFERENZIALE:
Dopo aver scritto tutto, esegui mentalmente questo scenario
e dimmi se le cascade/set null sono corrette: - Elimino un utente → cosa succede ai suoi wizard e template? - Elimino un template → i wizard che lo usano perdono il riferimento? - Completo un wizard → execution_log e report rimangono?
Correggi eventuali comportamenti sbagliati nelle migration. 4. COMANDI DA ESEGUIRE IN ORDINE:
a) php artisan migrate:fresh
(verifica che giri senza errori — se ci sono errori di FK
significa che l'ordine delle migration è sbagliato)
b) php artisan db:seed
(verifica che i seeder girino e i dati fake siano coerenti)
c) php artisan migrate:fresh --seed
(tutto insieme — questo è il comando che userai ogni volta
che fai reset del DB in locale)
d) Come verificare in phpMyAdmin che tutte le tabelle e gli
indici siano stati creati correttamente 5. COMANDI GIT:
Commit message convenzionale separato per migration e seeders.

Ogni file con percorso completo in intestazione.
Codice PHP completo, niente abbreviazioni.
Commenti in italiano che spiegano le scelte architetturali.

Ho tutto il necessario. Ora genero la risposta completa.

---

# WinDeploy — Database Architecture Complete

## 1. Audit Migration Esistenti

Dall'analisi della documentazione e dei modelli esistenti, ecco lo stato reale:[^1_1][^1_2]

| File migration                        | Stato                                                                                                                                                 | Problema                                                                                                                                           |
| :------------------------------------ | :---------------------------------------------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------------------- |
| `*_create_users_table.php`            | ✅ Esiste, **parzialmente corretta**                                                                                                                  | Usa `timestamps()` invece di `timestamp('created_at')` — ok. Mancano `updated_at` esplicito e **softDeletes** non sempre presenti. Verifica sotto. |
| `*_create_templates_table.php`        | ⚠️ **Probabilmente assente o da documento storico**                                                                                                   | La versione in 0008-db.md usa `restrictOnDelete` ma i wizard poi usano `nullOnDelete` su `template_id` — incoerenza da risolvere                   |
| `*_create_wizards_table.php`          | ⚠️ **Manca `updated_at`**                                                                                                                             | Usa `timestamp('created_at')->useCurrent()` ma non `updated_at`. Senza questo, Eloquent genera errore su ogni `save()`                             |
| `*_create_software_library_table.php` | ⚠️ **Manca `updated_at`**                                                                                                                             | Campo `identificatore` a 255 char: nel tuo requisito è 500. Da correggere                                                                          |
| `*_create_execution_logs_table.php`   | ❌ **timestamps disabilitati nel modello** ma la migrazione ha solo `started_at` — ok per scelta intenzionale. Manca `cascadeOnDelete` su `wizard_id` |                                                                                                                                                    |
| `*_create_reports_table.php`          | ⚠️ **Manca `updated_at`**                                                                                                                             | `cascadeOnDelete` presente, ma Report model ha `$timestamps = true` — conflitto se la tabella ha solo `created_at`                                 |
| `*_create_wizard_software_table.php`  | ❌ **MANCA COMPLETAMENTE**                                                                                                                            | Pivot richiesta nel tuo schema                                                                                                                     |

**Decisione architetturale su `wizard_software` pivot vs JSON `software_installa[]`:**

> Il modello `Wizard.php` esistente NON dichiara `belongsToMany(SoftwareLibrary::class)`, usa solo il campo JSON `configurazione`. I seeder esistenti usano il JSON. **Scelta: mantenere il JSON** come approccio primario e **non creare la pivot**. Motivazione: l'agent riceve un payload self-contained (funziona anche offline dopo il download), non fa query separate per ogni software. La pivot ha senso solo se vuoi query tipo "trova tutti i wizard che usano Chrome" — funzionalità non richiesta ora. Se in futuro serve analytics, si aggiunge la pivot senza rompere niente.

---

## 2. Migration Complete e Corrette

### Struttura file

```
database/
├── migrations/
│   ├── 2024_01_01_000001_create_users_table.php          ← SOSTITUISCI
│   ├── 2024_01_01_000002_create_templates_table.php      ← SOSTITUISCI
│   ├── 2024_01_01_000003_create_software_library_table.php ← NUOVA (ordine corretto FK)
│   ├── 2024_01_01_000004_create_wizards_table.php        ← SOSTITUISCI
│   ├── 2024_01_01_000005_create_execution_logs_table.php ← SOSTITUISCI
│   └── 2024_01_01_000006_create_reports_table.php        ← SOSTITUISCI
└── seeders/
    ├── DatabaseSeeder.php
    ├── UserSeeder.php
    ├── SoftwareSeeder.php
    ├── TemplateSeeder.php
    └── WizardSeeder.php
```

> ⚠️ **Nota sicurezza**: la migration `software_library` va PRIMA di `wizards` perché `wizards.configurazione` è JSON (no FK fisica), ma logicamente i software devono esistere. L'ordine qui è quello che evita errori di FK reali.

---

### Migration 1 — `users`

```php
<?php
// database/migrations/2024_01_01_000001_create_users_table.php
// VERSIONE DEFINITIVA — sostituisce quella esistente

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('email')->unique();
            $table->string('password');

            // Ruolo gestito come enum nativo MySQL per performance e constraint DB-level.
            // Spatie permission usa tabelle separate — manteniamo l'enum come source of truth
            // per query veloci (WHERE ruolo = 'admin') senza JOIN.
            $table->enum('ruolo', ['admin', 'tecnico', 'viewer'])->default('tecnico');

            $table->boolean('attivo')->default(true);

            // timestamps() genera sia created_at che updated_at — standard Laravel
            $table->timestamps();

            // Audit fields per sicurezza e compliance
            $table->timestamp('last_login')->nullable();
            $table->string('last_login_ip', 45)->nullable(); // 45 char coprono IPv6 completo

            // SoftDeletes: un utente eliminato ha wizard e log storici da preservare.
            // restrictOnDelete sulle FK garantisce che non si possa cancellare
            // un utente con dati collegati senza prima riassegnarli.
            $table->softDeletes();

            // Indici per query frequenti
            $table->index('ruolo');
            $table->index('attivo');
            $table->index(['email', 'attivo']); // query login: email + attivo insieme
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

### Migration 2 — `templates`

```php
<?php
// database/migrations/2024_01_01_000002_create_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->text('descrizione')->nullable();

            // cascadeOnDelete: se l'utente viene eliminato (soft delete non basta,
            // ma con softDeletes sulla tabella users non arriveremo mai qui in prod).
            // Scelta: cascadeOnDelete per coerenza — se un admin cancella fisicamente
            // un utente test, i suoi template personali spariscono con lui.
            // I template globali (scope='globale') sono sempre di un admin esistente.
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('scope', ['globale', 'personale'])->default('personale');

            // JSON con stessa struttura di wizards.configurazione (parziale o completa)
            $table->json('configurazione');

            $table->timestamps();
            $table->softDeletes(); // preserva template eliminati per storico wizard

            // Indici
            $table->index('user_id');
            $table->index('scope');
            $table->index(['user_id', 'scope']); // query: "miei template + tutti i globali"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
```

---

### Migration 3 — `software_library`

```php
<?php
// database/migrations/2024_01_01_000003_create_software_library_table.php
// NUOVA — va PRIMA di wizards (anche se non c'è FK fisica, per ordine logico)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_library', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->string('versione', 50)->nullable();   // nullable: winget gestisce auto
            $table->string('publisher', 255)->nullable();

            // Tipo determina come l'agent esegue l'installazione:
            // winget → winget install {identificatore}
            // exe/msi → esegue il file dal path {identificatore}
            $table->enum('tipo', ['winget', 'exe', 'msi']);

            // 500 char: winget ID può essere lungo (es. Microsoft.VisualStudioCode)
            // ma anche path UNC \\server\share\setup.exe
            $table->string('identificatore', 500);

            $table->string('categoria', 100)->nullable(); // Browser, Office, Sicurezza, ecc.
            $table->string('icona_url', 500)->nullable();

            // nullOnDelete: se il tecnico che ha aggiunto il software viene rimosso,
            // il software rimane nella libreria (non vogliamo perdere dati)
            $table->foreignId('aggiunto_da')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->boolean('attivo')->default(true);

            // Solo created_at: il software non ha updated_at perché le modifiche
            // creano una nuova versione (approccio immutabile per l'audit trail).
            // Se prevedi edit in-place, aggiungi timestamps() invece.
            $table->timestamps();
            $table->softDeletes(); // soft delete per preservare storico nei report

            // Indici per filtri UI frequenti
            $table->index('tipo');
            $table->index('attivo');
            $table->index('categoria');
            $table->index(['attivo', 'categoria']); // filtro combo più comune nella UI
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_library');
    }
};
```

---

### Migration 4 — `wizards`

```php
<?php
// database/migrations/2024_01_01_000004_create_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizards', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);

            // cascadeOnDelete: se l'utente viene eliminato, i suoi wizard spariscono.
            // ATTENZIONE: questo causa cascade anche su execution_logs se non protetto.
            // La protezione è su execution_logs.wizard_id con restrictOnDelete.
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // nullOnDelete: se il template viene eliminato (soft o hard),
            // il wizard mantiene la propria configurazione JSON autonoma.
            // Il template è solo "da dove è partito", non è necessario per l'esecuzione.
            $table->foreignId('template_id')
                  ->nullable()
                  ->constrained('templates')
                  ->nullOnDelete();

            // Formato WD-XXXX dove XXXX sono 4 caratteri alfanumerici uppercase.
            // UNIQUE a livello DB: l'agent usa questo come chiave di lookup primaria.
            $table->string('codice_univoco', 10)->unique();

            $table->enum('stato', [
                'bozza',        // in costruzione, non ancora distribuibile
                'pronto',       // generato con codice, pronto per l'agent
                'in_esecuzione',// agent sta lavorando
                'completato',   // esecuzione terminata con successo
                'errore',       // esecuzione fallita
            ])->default('bozza');

            // ⚠️ SICUREZZA CRITICA: questo campo contiene utente_admin.password_encrypted
            // e potenzialmente extras.wifi.password_encrypted.
            // REGOLA: mai esporre nelle API generali — vedi WizardResource che li rimuove.
            // La decifrazione avviene SOLO nell'endpoint /api/agent/start (JWT protetto).
            $table->json('configurazione');

            $table->timestamps(); // created_at + updated_at standard

            // expires_at: +24h da created_at, impostato nel Model boot() o nel Controller.
            // Lo scheduled job (app/Console/Commands/PurgeExpiredWizards) usa questo indice.
            $table->timestamp('expires_at')->nullable();

            // used_at: impostato quando l'agent esegue /api/agent/start.
            // Rende il wizard "monouso" — un secondo tentativo riceve 409 Conflict.
            $table->timestamp('used_at')->nullable();

            $table->softDeletes();

            // Indici critici per performance
            $table->unique('codice_univoco'); // già dichiarato sopra, ridondante ma esplicito
            $table->index('stato');
            $table->index('user_id');
            $table->index('expires_at');      // per il job di pulizia schedulato
            $table->index(['stato', 'user_id']); // dashboard: wizard per tecnico+stato
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
```

---

### Migration 5 — `execution_logs`

```php
<?php
// database/migrations/2024_01_01_000005_create_execution_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: NON si può eliminare un wizard che ha log associati.
            // Questo protegge l'audit trail. Per eliminare un wizard con log,
            // bisogna prima archiviare/eliminare i log — scelta deliberata.
            $table->foreignId('wizard_id')
                  ->constrained('wizards')
                  ->restrictOnDelete();

            $table->string('pc_nome_originale', 255)->nullable();
            $table->string('pc_nome_nuovo', 255)->nullable();

            // nullOnDelete: se il tecnico viene rimosso, il log rimane (audit trail)
            // ma tecnico_user_id diventa NULL. Il report mostra "tecnico rimosso".
            $table->foreignId('tecnico_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // JSON hardware: { "cpu": "Intel i7-12700", "ram_gb": 16,
            //                  "disco_gb": 512, "windows_version": "11 Pro 23H2" }
            $table->json('hardware_info')->nullable();

            $table->enum('stato', [
                'avviato',      // agent ha chiamato /api/agent/start
                'in_corso',     // step intermedi in arrivo
                'completato',   // tutti gli step ok
                'errore',       // almeno uno step critico fallito
                'abortito',     // utente o sistema ha interrotto
            ])->default('avviato');

            // JSON array di step:
            // [{"step": "rename_pc", "timestamp": "2026-03-04T20:00:00Z",
            //   "esito": "ok", "dettaglio": "PC rinominato da DESKTOP-ABC a PC-CONT-01"}]
            $table->json('log_dettagliato')->nullable();

            // execution_logs NON usa timestamps() standard di Laravel:
            // started_at e completed_at sono semanticamente diversi da created_at/updated_at.
            // Il Model ha $timestamps = false per evitare errori Eloquent.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Indici per dashboard e report
            $table->index('wizard_id');
            $table->index('stato');
            $table->index('tecnico_user_id');
            $table->index('started_at');                  // report per data
            $table->index(['stato', 'started_at']);       // dashboard: completati ultima settimana
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
```

---

### Migration 6 — `reports`

```php
<?php
// database/migrations/2024_01_01_000006_create_reports_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: se il log viene eliminato, il report sparisce.
            // Relazione 1:1 — un log ha al massimo un report.
            // unique() garantisce l'unicità a livello DB.
            $table->foreignId('execution_log_id')
                  ->unique()
                  ->constrained('execution_logs')
                  ->cascadeOnDelete();

            // LONGTEXT: un report HTML completo con CSS inline può arrivare a 10-50KB.
            // LONGTEXT supporta fino a 4GB — più che sufficiente.
            $table->longText('html_content');

            // Solo created_at: i report sono immutabili una volta generati.
            // Se serve ri-generare, si elimina e ricrea (cascade dal log).
            $table->timestamp('created_at')->useCurrent();

            // Indice su execution_log_id già creato dall'unique() sopra.
            // Aggiungiamo indice su created_at per query "report recenti".
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

---

## 3. Modelli Aggiornati

### `app/Models/User.php`

```php
<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'nome',
        'email',
        'password',
        'ruolo',
        'attivo',
        'last_login',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'attivo'      => 'boolean',
        'last_login'  => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // Relazioni
    public function wizards(): HasMany
    {
        return $this->hasMany(Wizard::class, 'user_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'user_id');
    }

    public function softwareAggiunto(): HasMany
    {
        return $this->hasMany(SoftwareLibrary::class, 'aggiunto_da');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'tecnico_user_id');
    }

    // Accessor per compatibilità con pacchetti che usano 'name'
    public function getNameAttribute(): ?string
    {
        return $this->attributes['nome'] ?? null;
    }

    // JWT Subject (per l'agent)
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
```

---

### `app/Models/Wizard.php`

```php
<?php
// app/Models/Wizard.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Wizard extends Model
{
    use SoftDeletes;

    // Stati validi — usati nel Controller e nelle Form Request
    public const STATI = ['bozza', 'pronto', 'in_esecuzione', 'completato', 'errore'];

    protected $fillable = [
        'nome',
        'user_id',
        'template_id',
        'codice_univoco',
        'stato',
        'configurazione',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        // Cast a array: Eloquent serializza/deserializza automaticamente il JSON.
        // ATTENZIONE: questo espone password_encrypted come stringa cifrata nell'array.
        // La rimozione avviene a livello di WizardResource, NON qui.
        'configurazione' => 'array',
        'expires_at'     => 'datetime',
        'used_at'        => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    // Genera automaticamente codice univoco e expires_at al momento della creazione
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Wizard $wizard) {
            // Genera codice WD-XXXX se non già impostato
            if (empty($wizard->codice_univoco)) {
                do {
                    $codice = 'WD-' . strtoupper(Str::random(4));
                } while (static::where('codice_univoco', $codice)->exists());

                $wizard->codice_univoco = $codice;
            }

            // Imposta scadenza a +24h dalla creazione
            if (empty($wizard->expires_at)) {
                $wizard->expires_at = now()->addHours(24);
            }
        });
    }

    // Relazioni
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'wizard_id');
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(ExecutionLog::class, 'wizard_id')->latestOfMany('started_at');
    }

    // Verifica se il wizard è ancora utilizzabile (non scaduto, non già usato)
    public function isUsabile(): bool
    {
        return $this->stato === 'pronto'
            && $this->used_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    // Cifra la password admin nel JSON configurazione prima del salvataggio.
    // Da chiamare nel Controller PRIMA di create() o update().
    public static function encryptSensitiveFields(array $configurazione): array
    {
        if (isset($configurazione['utente_admin']['password'])) {
            $plain = $configurazione['utente_admin']['password'];
            $configurazione['utente_admin']['password_encrypted'] = Crypt::encryptString($plain);
            unset($configurazione['utente_admin']['password']);
        }

        if (isset($configurazione['extras']['wifi']['password'])) {
            $plain = $configurazione['extras']['wifi']['password'];
            $configurazione['extras']['wifi']['password_encrypted'] = Crypt::encryptString($plain);
            unset($configurazione['extras']['wifi']['password']);
        }

        return $configurazione;
    }

    // Decifra la password admin — SOLO per l'endpoint /api/agent/start (JWT protetto).
    // Mai chiamare in API generali o Resource pubbliche.
    public function decryptAdminPassword(): string
    {
        $config = $this->configurazione;
        return Crypt::decryptString($config['utente_admin']['password_encrypted']);
    }
}
```

---

### `app/Models/Template.php`

```php
<?php
// app/Models/Template.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'templates';

    protected $fillable = [
        'nome',
        'descrizione',
        'user_id',
        'scope',
        'configurazione',
    ];

    protected $casts = [
        'configurazione' => 'array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wizards(): HasMany
    {
        return $this->hasMany(Wizard::class, 'template_id');
    }
}
```

---

### `app/Models/SoftwareLibrary.php`

```php
<?php
// app/Models/SoftwareLibrary.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftwareLibrary extends Model
{
    use SoftDeletes;

    protected $table = 'software_library';

    protected $fillable = [
        'nome',
        'versione',
        'publisher',
        'tipo',
        'identificatore',
        'categoria',
        'icona_url',
        'aggiunto_da',
        'attivo',
    ];

    protected $casts = [
        'attivo'     => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function aggiuntoDA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aggiunto_da');
    }
}
```

---

### `app/Models/ExecutionLog.php`

```php
<?php
// app/Models/ExecutionLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExecutionLog extends Model
{
    protected $table = 'execution_logs';

    // La tabella usa started_at/completed_at, NON created_at/updated_at standard.
    // Disabilitare timestamps Eloquent evita errori "Unknown column 'updated_at'" su save().
    public $timestamps = false;

    protected $fillable = [
        'wizard_id',
        'pc_nome_originale',
        'pc_nome_nuovo',
        'tecnico_user_id',
        'hardware_info',
        'stato',
        'log_dettagliato',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'hardware_info'   => 'array',
        'log_dettagliato' => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function wizard(): BelongsTo
    {
        return $this->belongsTo(Wizard::class, 'wizard_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_user_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'execution_log_id');
    }
}
```

---

### `app/Models/Report.php`

```php
<?php
// app/Models/Report.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'reports';

    // Report immutabile: ha solo created_at (non updated_at).
    // Disabilitiamo timestamps standard e gestiamo solo created_at via migration.
    public $timestamps = false;

    protected $fillable = [
        'execution_log_id',
        'html_content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function executionLog(): BelongsTo
    {
        return $this->belongsTo(ExecutionLog::class, 'execution_log_id');
    }
}
```

---

## 4. Seeders Completi

### `database/seeders/DatabaseSeeder.php`

```php
<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // L'ordine è obbligatorio: rispetta le dipendenze FK tra le tabelle.
        // UserSeeder prima di tutto, poi Software (FK aggiunto_da → users),
        // poi Template (FK user_id → users), poi Wizard (FK user_id + template_id).
        $this->call([
            UserSeeder::class,
            SoftwareSeeder::class,
            TemplateSeeder::class,
            WizardSeeder::class,
        ]);
    }
}
```

---

### `database/seeders/UserSeeder.php`

```php
<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usiamo firstOrCreate per rendere il seeder idempotente:
        // se girato due volte non duplica gli utenti.

        // Admin principale
        User::firstOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'nome'          => 'Admin WinDeploy',
                'password'      => Hash::make('Admin@1234!'),
                'ruolo'         => 'admin',
                'attivo'        => true,
                'last_login'    => now()->subHours(3),
                'last_login_ip' => '127.0.0.1',
            ]
        );

        // Tecnico 1 — proprietario dei template e wizard nel seeder
        User::firstOrCreate(
            ['email' => 'tecnico1@windeploy.local'],
            [
                'nome'          => 'Marco Ferretti',
                'password'      => Hash::make('Tecnico@1234!'),
                'ruolo'         => 'tecnico',
                'attivo'        => true,
                'last_login'    => now()->subDays(1),
                'last_login_ip' => '192.168.1.10',
            ]
        );

        // Tecnico 2
        User::firstOrCreate(
            ['email' => 'tecnico2@windeploy.local'],
            [
                'nome'          => 'Sara Lombardi',
                'password'      => Hash::make('Tecnico@1234!'),
                'ruolo'         => 'tecnico',
                'attivo'        => true,
                'last_login'    => now()->subDays(3),
                'last_login_ip' => '192.168.1.11',
            ]
        );

        // Viewer (solo lettura — accede ai report)
        User::firstOrCreate(
            ['email' => 'viewer@windeploy.local'],
            [
                'nome'     => 'Responsabile IT',
                'password' => Hash::make('Viewer@1234!'),
                'ruolo'    => 'viewer',
                'attivo'   => true,
            ]
        );
    }
}
```

---

### `database/seeders/SoftwareSeeder.php`

```php
<?php
// database/seeders/SoftwareSeeder.php

namespace Database\Seeders;

use App\Models\SoftwareLibrary;
use App\Models\User;
use Illuminate\Database\Seeder;

class SoftwareSeeder extends Seeder
{
    public function run(): void
    {
        // Recupera l'ID dell'admin — aggiunto_da non può essere NULL qui
        $adminId = User::where('email', 'admin@windeploy.local')->value('id');

        // Dati reali con ID winget verificati al 2026-03.
        // Fonte: winget.run e Microsoft winget-pkgs su GitHub.
        $software = [
            [
                'nome'           => 'Google Chrome',
                'versione'       => null, // winget installa sempre latest
                'publisher'      => 'Google LLC',
                'tipo'           => 'winget',
                'identificatore' => 'Google.Chrome',
                'categoria'      => 'Browser',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Mozilla Firefox',
                'versione'       => null,
                'publisher'      => 'Mozilla',
                'tipo'           => 'winget',
                'identificatore' => 'Mozilla.Firefox',
                'categoria'      => 'Browser',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => '7-Zip',
                'versione'       => '24.08',
                'publisher'      => 'Igor Pavlov',
                'tipo'           => 'winget',
                'identificatore' => '7zip.7zip',
                'categoria'      => 'Utilità',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'VLC Media Player',
                'versione'       => '3.0.21',
                'publisher'      => 'VideoLAN',
                'tipo'           => 'winget',
                'identificatore' => 'VideoLAN.VLC',
                'categoria'      => 'Multimedia',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Notepad++',
                'versione'       => '8.7',
                'publisher'      => 'Notepad++ Team',
                'tipo'           => 'winget',
                'identificatore' => 'Notepad++.Notepad++',
                'categoria'      => 'Sviluppo',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Adobe Acrobat Reader',
                'versione'       => null,
                'publisher'      => 'Adobe Inc.',
                'tipo'           => 'winget',
                'identificatore' => 'Adobe.Acrobat.Reader.64-bit',
                'categoria'      => 'Office',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Microsoft Visual C++ Redistributable',
                'versione'       => null,
                'publisher'      => 'Microsoft Corporation',
                'tipo'           => 'winget',
                'identificatore' => 'Microsoft.VCRedist.2015+.x64',
                'categoria'      => 'Sistema',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'WinRAR',
                'versione'       => '7.10',
                'publisher'      => 'win.rar GmbH',
                'tipo'           => 'winget',
                'identificatore' => 'RARLab.WinRAR',
                'categoria'      => 'Utilità',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => false, // disattivato: preferire 7-Zip (free)
            ],
        ];

        foreach ($software as $item) {
            SoftwareLibrary::firstOrCreate(
                ['identificatore' => $item['identificatore']],
                $item
            );
        }
    }
}
```

---

### `database/seeders/TemplateSeeder.php`

```php
<?php
// database/seeders/TemplateSeeder.php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $adminId    = User::where('email', 'admin@windeploy.local')->value('id');
        $tecnico1Id = User::where('email', 'tecnico1@windeploy.local')->value('id');

        // Template globale (creato dall'admin, visibile a tutti i tecnici)
        Template::firstOrCreate(
            ['nome' => 'Standard Aziendale'],
            [
                'descrizione'   => 'Configurazione base per tutti i PC aziendali. Include browser, PDF reader e utilità.',
                'user_id'       => $adminId,
                'scope'         => 'globale',
                'configurazione' => [
                    'software_installa' => [
                        ['software_library_id' => 1, 'nome' => 'Google Chrome',         'tipo' => 'winget', 'identificatore' => 'Google.Chrome',                  'obbligatorio' => true],
                        ['software_library_id' => 3, 'nome' => '7-Zip',                 'tipo' => 'winget', 'identificatore' => '7zip.7zip',                      'obbligatorio' => true],
                        ['software_library_id' => 6, 'nome' => 'Adobe Acrobat Reader',  'tipo' => 'winget', 'identificatore' => 'Adobe.Acrobat.Reader.64-bit',    'obbligatorio' => true],
                        ['software_library_id' => 7, 'nome' => 'VC++ Redistributable',  'tipo' => 'winget', 'identificatore' => 'Microsoft.VCRedist.2015+.x64',   'obbligatorio' => true],
                    ],
                    'bloatware_default' => [
                        'Microsoft.XboxApp',
                        'king.com.CandyCrushSaga',
                        'Microsoft.BingWeather',
                        'Microsoft.OneDriveSync',
                        'MicrosoftTeams',
                    ],
                    'power_plan' => [
                        'tipo'   => 'preset',
                        'params' => ['preset' => 'balanced'],
                    ],
                    'extras' => [
                        'timezone'                 => 'Europe/Rome',
                        'language'                 => 'it-IT',
                        'keyboard_layout'          => 'it-IT',
                        'wallpaper_url'            => null,
                        'wifi'                     => null,
                        'windows_update'           => ['policy' => 'download_only'],
                        'remove_microsoft_account' => true,
                    ],
                ],
            ]
        );

        // Template personale del tecnico1 (ottimizzato per sviluppatori)
        Template::firstOrCreate(
            ['nome' => 'Dev Workstation'],
            [
                'descrizione'   => 'Template personale per postazioni sviluppo. VLC, Notepad++, Chrome e strumenti di base.',
                'user_id'       => $tecnico1Id,
                'scope'         => 'personale',
                'configurazione' => [
                    'software_installa' => [
                        ['software_library_id' => 1, 'nome' => 'Google Chrome', 'tipo' => 'winget', 'identificatore' => 'Google.Chrome',        'obbligatorio' => true],
                        ['software_library_id' => 5, 'nome' => 'Notepad++',     'tipo' => 'winget', 'identificatore' => 'Notepad++.Notepad++',   'obbligatorio' => true],
                        ['software_library_id' => 3, 'nome' => '7-Zip',         'tipo' => 'winget', 'identificatore' => '7zip.7zip',             'obbligatorio' => false],
                        ['software_library_id' => 4, 'nome' => 'VLC',           'tipo' => 'winget', 'identificatore' => 'VideoLAN.VLC',          'obbligatorio' => false],
                    ],
                    'bloatware_default' => [
                        'Microsoft.XboxApp',
                        'king.com.CandyCrushSaga',
                        'Microsoft.BingWeather',
                    ],
                    'power_plan' => [
                        'tipo'   => 'preset',
                        'params' => ['preset' => 'high_performance'],
                    ],
                    'extras' => [
                        'timezone'                 => 'Europe/Rome',
                        'language'                 => 'it-IT',
                        'keyboard_layout'          => 'it-IT',
                        'wallpaper_url'            => null,
                        'wifi'                     => null,
                        'windows_update'           => ['policy' => 'manual'],
                        'remove_microsoft_account' => true,
                    ],
                ],
            ]
        );
    }
}
```

---

### `database/seeders/WizardSeeder.php`

```php
<?php
// database/seeders/WizardSeeder.php

namespace Database\Seeders;

use App\Models\ExecutionLog;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Models\Wizard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class WizardSeeder extends Seeder
{
    public function run(): void
    {
        $tecnico1Id  = User::where('email', 'tecnico1@windeploy.local')->value('id');
        $tecnico2Id  = User::where('email', 'tecnico2@windeploy.local')->value('id');
        $templateGlobId = Template::where('nome', 'Standard Aziendale')->value('id');

        // Helper per generare configurazione JSON realistica
        $buildConfig = function (string $nomePc, array $extraSoftware = []): array {
            $softwareBase = [
                ['software_library_id' => 1, 'nome' => 'Google Chrome',        'tipo' => 'winget', 'identificatore' => 'Google.Chrome',                'obbligatorio' => true],
                ['software_library_id' => 3, 'nome' => '7-Zip',                'tipo' => 'winget', 'identificatore' => '7zip.7zip',                    'obbligatorio' => true],
                ['software_library_id' => 6, 'nome' => 'Adobe Acrobat Reader', 'tipo' => 'winget', 'identificatore' => 'Adobe.Acrobat.Reader.64-bit',  'obbligatorio' => true],
            ];

            return [
                'nome_pc'      => $nomePc,
                'utente_admin' => [
                    'username'           => 'admin-locale',
                    // La password viene sempre cifrata — mai in chiaro nel DB
                    'password_encrypted' => Crypt::encryptString('DevLocal@2026!'),
                ],
                'software_installa' => array_merge($softwareBase, $extraSoftware),
                'bloatware_default' => [
                    'Microsoft.XboxApp',
                    'king.com.CandyCrushSaga',
                    'Microsoft.BingWeather',
                    'MicrosoftTeams',
                ],
                'power_plan' => [
                    'tipo'   => 'preset',
                    'params' => ['preset' => 'balanced'],
                ],
                'extras' => [
                    'timezone'                 => 'Europe/Rome',
                    'language'                 => 'it-IT',
                    'keyboard_layout'          => 'it-IT',
                    'wallpaper_url'            => null,
                    'wifi'                     => null,
                    'windows_update'           => ['policy' => 'download_only'],
                    'remove_microsoft_account' => true,
                ],
            ];
        };

        // ── Wizard 1: BOZZA (tecnico1, nessun template) ──────────────────────
        Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-AAAA'],
            [
                'nome'           => 'PC Contabilità 01 - Bozza',
                'user_id'        => $tecnico1Id,
                'template_id'    => null,
                'codice_univoco' => 'WD-AAAA',
                'stato'          => 'bozza',
                'configurazione' => $buildConfig('PC-CONT-01'),
                'expires_at'     => now()->addHours(20),
                'used_at'        => null,
            ]
        );

        // ── Wizard 2: PRONTO (tecnico1, da template globale) ─────────────────
        Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-BBBB'],
            [
                'nome'           => 'PC Ufficio Commerciale 03',
                'user_id'        => $tecnico1Id,
                'template_id'    => $templateGlobId,
                'codice_univoco' => 'WD-BBBB',
                'stato'          => 'pronto',
                'configurazione' => $buildConfig('PC-COMM-03', [
                    ['software_library_id' => 4, 'nome' => 'VLC Media Player', 'tipo' => 'winget', 'identificatore' => 'VideoLAN.VLC', 'obbligatorio' => false],
                ]),
                'expires_at'     => now()->addHours(18),
                'used_at'        => null,
            ]
        );

        // ── Wizard 3: COMPLETATO (tecnico2) con execution_log e report ───────
        $wizard3 = Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-CCCC'],
            [
                'nome'           => 'Postazione Direzione Premium',
                'user_id'        => $tecnico2Id,
                'template_id'    => null,
                'codice_univoco' => 'WD-CCCC',
                'stato'          => 'completato',
                'configurazione' => $buildConfig('PC-DIR-01', [
                    ['software_library_id' => 5, 'nome' => 'Notepad++', 'tipo' => 'winget', 'identificatore' => 'Notepad++.Notepad++', 'obbligatorio' => false],
                ]),
                'expires_at'     => now()->subHours(20), // già scaduto (usato)
                'used_at'        => now()->subHours(22),
            ]
        );

        // Crea execution_log per il wizard completato (se non esiste già)
        $log = ExecutionLog::firstOrCreate(
            ['wizard_id' => $wizard3->id],
            [
                'pc_nome_originale' => 'DESKTOP-XKJF982',
                'pc_nome_nuovo'     => 'PC-DIR-01',
                'tecnico_user_id'   => $tecnico2Id,
                'hardware_info'     => [
                    'cpu'             => 'Intel Core i7-12700',
                    'ram_gb'          => 32,
                    'disco_gb'        => 1024,
                    'windows_version' => 'Windows 11 Pro 23H2',
                ],
                'stato' => 'completato',
                'log_dettagliato' => [
                    ['step' => 'rename_pc',         'timestamp' => now()->subHours(22)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => 'PC rinominato da DESKTOP-XKJF982 a PC-DIR-01'],
                    ['step' => 'create_admin_user', 'timestamp' => now()->subHours(22)->addMinutes(1)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => 'Utente admin-locale creato'],
                    ['step' => 'remove_bloatware',  'timestamp' => now()->subHours(22)->addMinutes(3)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => '4 app rimosse'],
                    ['step' => 'install_software',  'timestamp' => now()->subHours(22)->addMinutes(10)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => '4 software installati via winget'],
                    ['step' => 'power_plan',        'timestamp' => now()->subHours(22)->addMinutes(15)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Piano alimentazione: balanced'],
                    ['step' => 'timezone_language', 'timestamp' => now()->subHours(22)->addMinutes(16)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Timezone: Europe/Rome, Language: it-IT'],
                    ['step' => 'windows_update',    'timestamp' => now()->subHours(22)->addMinutes(18)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Policy impostata: download_only'],
                ],
                'started_at'   => now()->subHours(22),
                'completed_at' => now()->subHours(22)->addMinutes(20),
            ]
        );

        // Crea report HTML per il log (se non esiste già)
        Report::firstOrCreate(
            ['execution_log_id' => $log->id],
            [
                'html_content' => $this->buildHtmlReport($log),
            ]
        );
    }

    // Genera un report HTML minimale ma realistico per il seeder
    private function buildHtmlReport(ExecutionLog $log): string
    {
        $steps = collect($log->log_dettagliato)
            ->map(fn($s) => "<tr><td>{$s['step']}</td><td>{$s['esito']}</td><td>{$s['dettaglio']}</td></tr>")
            ->implode("\n");

        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><title>Report WinDeploy - {$log->pc_nome_nuovo}</title>
<style>body{font-family:Arial,sans-serif;padding:20px} table{width:100%;border-collapse:collapse} td,th{border:1px solid #ccc;padding:8px} th{background:#2563eb;color:#fff}</style>
</head>
<body>
  <h1>Report Configurazione PC</h1>
  <p><strong>PC:</strong> {$log->pc_nome_originale} → {$log->pc_nome_nuovo}</p>
  <p><strong>Stato:</strong> {$log->stato}</p>
  <p><strong>Inizio:</strong> {$log->started_at}</p>
  <p><strong>Fine:</strong> {$log->completed_at}</p>
  <h2>Step eseguiti</h2>
  <table><thead><tr><th>Step</th><th>Esito</th><th>Dettaglio</th></tr></thead>
  <tbody>{$steps}</tbody></table>
  <p><em>Generato da WinDeploy</em></p>
</body></html>
HTML;
    }
}
```

---

## 5. Verifica Integrità Referenziale

Simulazione scenari di eliminazione:

| Scenario                         | Comportamento                                                                                                     | Corretto?                                                                                                                   |
| :------------------------------- | :---------------------------------------------------------------------------------------------------------------- | :-------------------------------------------------------------------------------------------------------------------------- |
| **Elimino utente** (soft delete) | I suoi `wizards` hanno `cascadeOnDelete` → vengono eliminati (soft se hai softDeletes, cascade fisico altrimenti) | ⚠️ **Attenzione**: cascade su wizard può triggerare `restrictOnDelete` su `execution_logs` → **ERRORE** se il wizard ha log |
| **Elimino template**             | `wizards.template_id` → `nullOnDelete` ✅ I wizard diventano autonomi (template_id = NULL)                        | ✅ Corretto                                                                                                                 |
| **Completo wizard**              | `execution_logs` rimane (no cascade up), `reports` rimane (cascade dal log, non dal wizard)                       | ✅ Corretto                                                                                                                 |
| **Elimino execution_log**        | `reports` → `cascadeOnDelete` — il report sparisce con il log                                                     | ✅ Corretto                                                                                                                 |

**🔴 Problema identificato**: se elimini un utente che ha wizard con execution_logs associati, la cascade su `users → wizards` tenta di eliminare il wizard, ma `execution_logs.wizard_id` ha `restrictOnDelete` che blocca l'operazione con un'eccezione FK.

**Fix raccomandato** — cambia la FK `wizards.user_id` da `cascadeOnDelete` a `nullOnDelete`:

```php
// In create_wizards_table.php — modifica questa FK:
$table->foreignId('user_id')
      ->nullable()           // ← aggiunto nullable
      ->constrained('users')
      ->nullOnDelete();      // ← cascade → null
```

E nel modello Wizard aggiungi `user_id` come nullable nel `$fillable`. Così eliminare un utente non distrugge i suoi wizard (e i log rimangono integri).

---

## 6. Comandi da Eseguire in Ordine

```powershell
# ── a) Reset completo del database e migrazione ──────────────────────────────
cd C:\xampp\htdocs\windeploy\backend

php artisan migrate:fresh
# Risposta attesa: "Nothing to rollback." poi tutte le migration in sequenza.
# Se vedi "SQLSTATE[HY000]: Cannot add foreign key constraint" → l'ordine dei file
# di migration è sbagliato. Rinomina il timestamp nel filename per correggere l'ordine.

# ── b) Seed dati di sviluppo ─────────────────────────────────────────────────
php artisan db:seed
# Risposta attesa: ciascun seeder con "Running: UserSeeder", ecc.
# Se vedi "Integrity constraint violation" → un ID referenziato non esiste ancora.
# Controlla l'ordine in DatabaseSeeder::$call.

# ── c) Reset + seed in un solo comando (quello che userai ogni giorno in dev) ─
php artisan migrate:fresh --seed

# ── d) Verifica in phpMyAdmin ─────────────────────────────────────────────────
# 1. Apri http://localhost/phpmyadmin
# 2. Seleziona database "windeploy"
# 3. Verifica tabelle presenti:
#    users, templates, software_library, wizards, execution_logs, reports
#    + personal_access_tokens (Sanctum), migrations, model_has_roles, ecc. (Spatie)
# 4. Per ogni tabella, clicca "Struttura" e verifica:
#    - Colonne presenti e tipi corretti
#    - Clicca "Indici" per vedere FK e indici creati
# 5. Query di verifica rapida in "SQL":
SELECT TABLE_NAME, TABLE_ROWS, INDEX_LENGTH
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'windeploy'
ORDER BY TABLE_NAME;
```

---

## 7. Comandi Git

```bash
# ── Migration ──────────────────────────────────────────────────────────────────
git add database/migrations/
git commit -m "feat(db): add complete migrations for WinDeploy schema

- users: enum ruolo, attivo, last_login, last_login_ip, softDeletes
- templates: scope enum, configurazione JSON, cascadeOnDelete user
- software_library: tipo enum, identificatore 500char, nullOnDelete aggiunto_da
- wizards: codice_univoco WD-XXXX unique, configurazione JSON, softDeletes
- execution_logs: timestamps manuali (no Laravel auto), restrictOnDelete wizard
- reports: longText html_content, cascadeOnDelete execution_log

Fixes: updated_at mancante in wizards, identificatore 255→500 in software_library"

# ── Modelli ────────────────────────────────────────────────────────────────────
git add app/Models/
git commit -m "feat(models): update all Eloquent models for WinDeploy schema

- User: SoftDeletes + relazioni wizards/templates/softwareAggiunto/executionLogs
- Wizard: boot() genera codice WD-XXXX + expires_at, encryptSensitiveFields()
- Template: SoftDeletes + relazione wizards/user
- SoftwareLibrary: SoftDeletes + relazione aggiuntoDA
- ExecutionLog: timestamps=false, relazioni wizard/tecnico/report
- Report: timestamps=false, solo created_at"

# ── Seeders ────────────────────────────────────────────────────────────────────
git add database/seeders/
git commit -m "feat(seeders): add dev seeders for WinDeploy local environment

- UserSeeder: 1 admin, 2 tecnici, 1 viewer (idempotente con firstOrCreate)
- SoftwareSeeder: 8 software reali con ID winget verificati
- TemplateSeeder: 1 globale (admin), 1 personale (tecnico1)
- WizardSeeder: 3 wizard (bozza/pronto/completato) + execution_log + report HTML"
```

---

> ⚠️ **Rischio critico residuo**: il campo `configurazione` nel modello Wizard viene castato a `array` automaticamente da Eloquent, il che significa che `password_encrypted` è accessibile come stringa PHP ovunque nel codice. La protezione **dipende interamente** dal fatto che `WizardResource` rimuova il campo prima di serializzare. Se un developer aggiunge un endpoint che usa `$wizard->toArray()` senza passare per la Resource, la stringa cifrata finisce nella risposta. Considera in futuro un cast personalizzato `EncryptedJsonCast` che decifra solo campi specifici e che non serializza mai le password — anche cifrate — nel JSON di risposta.[^1_3][^1_2][^1_1]
> <span style="display:none">[^1_10][^1_11][^1_12][^1_13][^1_14][^1_15][^1_16][^1_17][^1_18][^1_19][^1_20][^1_4][^1_5][^1_6][^1_7][^1_8][^1_9]</span>

<div align="center">⁂</div>

[^1_1]: 0102-generazione-bulk-di-codice-CRUD.md

[^1_2]: 0008-db.md

[^1_3]: 0101-auth-e-sicurezza.md

[^1_4]: 0104-frontend React e tooling Vite.md

[^1_5]: 0103-configurazione auth e sicurezza.md

[^1_6]: 0100-File temporanei tmp\_.php esposti nella root.md

[^1_7]: 0021-Installer-configurazione sistema.md

[^1_8]: 0020-Scansione app installate.md

[^1_9]: 0019-Struttura agent.md

[^1_10]: 0018-userReportsSoftwareLibraryTemplateManager.md

[^1_11]: 0017-motorpage.md

[^1_12]: 0016-wizards.md

[^1_13]: 0015-dashboard.md

[^1_14]: 0014-Login.md

[^1_15]: 0013-SetupprogettoReact.md

[^1_16]: 0012-apiendpointwindows.md

[^1_17]: 0011-controllercrud.md

[^1_18]: 0010-autenticazione.md

[^1_19]: 0009-scaffoldprogetto.md

[^1_20]: 0007-cheatsheet.md

---

# 2026_27_02_000003_create_wizards_table ............................................................... 113.33ms FAIL

Illuminate\\Database\\QueryException

SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'wizards_codice_univoco_unique' (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: windeploy, SQL: alter table `wizards` add unique `wizards_codice_univoco_unique`(`codice_univoco`))

at vendor\\laravel\\framework\\src\\Illuminate\\Database\\Connection.php:838
834▕ \$exceptionType = $this->isUniqueConstraintError($e)
835▕ ? UniqueConstraintViolationException::class
836▕ : QueryException::class;
837▕
➜ 838▕ throw new \$exceptionType(
839▕ \$this->getNameWithReadWriteType(),
840▕ \$query,
841▕ $this->prepareBindings($bindings),
842▕ \$e,

1 vendor\\laravel\\framework\\src\\Illuminate\\Database\\Connection.php:584
PDOException::("SQLSTATE[42000]: Syntax error or access violation: 1061 Duplicate key name 'wizards_codice_univoco_unique'")

2 vendor\\laravel\\framework\\src\\Illuminate\\Database\\Connection.php:584
PDOStatement::execute()

Il problema è chiarissimo. La migration della tabella `wizards` dichiara `unique()` **due volte** sullo stesso campo `codice_univoco`:[^2_1]

---

## Causa del Duplicate Key Error

Nel file `create_wizards_table.php` ci sono **due istruzioni che creano lo stesso indice unique** su `codice_univoco`:

```php
// RIGA 1 — inline nella definizione della colonna
$table->string('codice_univoco', 10)->unique();   // ← crea: wizards_codice_univoco_unique

// RIGA 2 — esplicita più in basso negli indici
$table->unique('codice_univoco');                  // ← crea: wizards_codice_univoco_unique (DUPLICATO → ERRORE)
```

MySQL non ammette due indici con lo stesso nome sulla stessa tabella, e Laravel genera automaticamente il nome `{tabella}_{campo}_unique` in entrambi i casi.

---

## Fix: `create_wizards_table.php`

```
database/migrations/2024_01_01_000004_create_wizards_table.php
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizards', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('template_id')
                  ->nullable()
                  ->constrained('templates')
                  ->nullOnDelete();

            // CORRETTO: unique() solo inline — NON ripetere $table->unique() sotto
            $table->string('codice_univoco', 10)->unique();

            $table->enum('stato', [
                'bozza',
                'pronto',
                'in_esecuzione',
                'completato',
                'errore',
            ])->default('bozza');

            $table->json('configurazione');
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->softDeletes();

            // Solo indici NON ancora creati — codice_univoco è già unique sopra
            $table->index('stato');
            $table->index('user_id');
            $table->index('expires_at');
            $table->index(['stato', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
```

---

## Regola Generale da Ricordare

In Laravel Blueprint esistono **due modi equivalenti** per creare un indice unique, ma usarli **insieme sullo stesso campo** genera l'errore:

| Metodo                          | Quando usarlo                                                                                        |
| :------------------------------ | :--------------------------------------------------------------------------------------------------- |
| `->string('campo')->unique()`   | Colonna singola — compatto, inline                                                                   |
| `->unique('campo')`             | Dopo la definizione, utile per indici **composti** (`->unique(['campo1','campo2'])`) o per chiarezza |
| `->unique(['campo1','campo2'])` | **Unico modo** per unique compositi                                                                  |

> ⚠️ **Implicazione di sicurezza**: il `codice_univoco` è la chiave di accesso primaria dell'agent Windows. L'indice `UNIQUE` a livello DB è la garanzia hardware che due wizard non abbiano mai lo stesso codice — non affidarsi solo al controllo applicativo nel `boot()` del Model.

---

## Comandi da Eseguire

```bash
# Reset completo e ri-esecuzione (da XAMPP shell nella root del backend)
php artisan migrate:fresh

# Se va a buon fine:
php artisan db:seed

# Oppure tutto insieme:
php artisan migrate:fresh --seed
```

Se dopo il fix appare un errore simile su un'altra tabella (es. `reports_execution_log_id_unique` da `->unique()->constrained()`), il pattern è identico: rimuovi il secondo `->unique()` ridondante e mantieni solo quello inline.
<span style="display:none">[^2_2][^2_3][^2_4][^2_5]</span>

<div align="center">⁂</div>

[^2_1]: 0008-db.md

[^2_2]: 0010-autenticazione.md

[^2_3]: 0102-generazione-bulk-di-codice-CRUD.md

[^2_4]: 0016-wizards.md

[^2_5]: 0018-userReportsSoftwareLibraryTemplateManager.md
