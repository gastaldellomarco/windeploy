# 🗄️ Database Schema MySQL — WinDeploy

**Data aggiornamento:** 2026-03-06  
**Stack:** Laravel 11 (PHP 8.3) | MySQL 8 | XAMPP  
**Versione schema:** 1.0

---

## 📋 Panoramica Tabelle

| Tabella            | Scopo                           | Soft Delete |
| ------------------ | ------------------------------- | ----------- |
| `users`            | Utenti (admin, tecnico, viewer) | ✅ Sì       |
| `templates`        | Template configurazione         | ✅ Sì       |
| `wizards`          | Wizard creati dagli utenti      | ✅ Sì       |
| `software_library` | Libreria software installabile  | ❌ No       |
| `execution_logs`   | Log esecuzioni su PC            | ❌ No       |
| `reports`          | Report HTML finali              | ❌ No       |

---

## 📁 Struttura File

```
backend/database/
├── migrations/
│   ├── 2024_01_01_000001_create_users_table.php
│   ├── 2024_01_01_000002_create_templates_table.php
│   ├── 2024_01_01_000003_create_wizards_table.php
│   ├── 2024_01_01_000004_create_software_library_table.php
│   ├── 2024_01_01_000005_create_execution_logs_table.php
│   └── 2024_01_01_000006_create_reports_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── UserSeeder.php
    ├── SoftwareLibrarySeeder.php
    └── WizardSeeder.php
```

---

## ✅ Migration 1 — `users`

**Utenti del sistema (admin, tecnico, viewer).**

```php
<?php
// database/migrations/2024_01_01_000001_create_users_table.php

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
            $table->enum('ruolo', ['admin', 'tecnico', 'viewer'])->default('tecnico');
            $table->boolean('attivo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('last_login')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();

            $table->index('ruolo');
            $table->index('attivo');
            $table->index(['email', 'attivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

## ✅ Migration 2 — `templates`

**Template di configurazione riutilizzabili.**

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
            $table->unsignedBigInteger('user_id');
            $table->enum('scope', ['globale', 'personale'])->default('personale');
            $table->json('configurazione')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
```

---

## ✅ Migration 3 — `software_library`

**Libreria centralizzata di software installabili.**

```php
<?php
// database/migrations/2024_01_01_000004_create_software_library_table.php

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
            $table->string('versione', 50)->nullable();
            $table->string('publisher', 255)->nullable();
            $table->enum('tipo', ['winget', 'exe', 'msi'])->default('winget');
            $table->string('identificatore', 500);  // winget_id or path
            $table->string('categoria', 100)->nullable();
            $table->string('icona_url', 500)->nullable();
            $table->unsignedBigInteger('aggiunto_da')->nullable();
            $table->boolean('attivo')->default(true);
            $table->timestamps();

            $table->foreign('aggiunto_da')->references('id')->on('users')->onDelete('setNull');
            $table->index('tipo');
            $table->index('attivo');
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_library');
    }
};
```

---

## ✅ Migration 4 — `wizards`

**Wizard creati dagli utenti (contiene configurazione completa).**

```php
<?php
// database/migrations/2024_01_01_000003_create_wizards_table.php

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
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('codice_univoco', 10)->unique(); // WD-XXXX
            $table->enum('stato', ['bozza', 'pronto', 'in_esecuzione', 'completato', 'errore'])
                ->default('bozza');
            $table->json('configurazione')->nullable();
            $table->timestamp('expires_at')->nullable();     // 24h from creation
            $table->timestamp('used_at')->nullable();         // First execution
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('template_id')->references('id')->on('templates')->onDelete('setNull');
            $table->unique('codice_univoco');
            $table->index('stato');
            $table->index('expires_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
```

---

## ✅ Migration 5 — `execution_logs`

**Log delle esecuzioni dei wizard sui PC.**

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
            $table->unsignedBigInteger('wizard_id');
            $table->string('pc_nome_originale', 255)->nullable();
            $table->string('pc_nome_nuovo', 255)->nullable();
            $table->unsignedBigInteger('tecnico_user_id')->nullable();
            $table->json('hardware_info')->nullable();
            $table->enum('stato', ['avviato', 'in_corso', 'completato', 'errore', 'abortito'])
                ->default('avviato');
            $table->json('log_dettagliato')->nullable();      // Array di step
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('wizard_id')->references('id')->on('wizards')->onDelete('cascade');
            $table->foreign('tecnico_user_id')->references('id')->on('users')->onDelete('setNull');
            $table->index('wizard_id');
            $table->index('stato');
            $table->index('tecnico_user_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
```

---

## ✅ Migration 6 — `reports`

**Report HTML finali generati dopo esecuzione.**

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
            $table->unsignedBigInteger('execution_log_id');
            $table->longText('html_content');
            $table->timestamps();

            $table->foreign('execution_log_id')->references('id')->on('execution_logs')
                ->onDelete('cascade');
            $table->index('execution_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

---

## 📝 JSON Schema — Campo `configurazione` (Wizards)

Il campo `configurazione` in `wizards` tabella memorizza il contratto canonico WizardConfig v1.0.

Vedi: `docs/schemas/wizard-config.schema.json` per lo schema completo.

**Esempio minificato:**

```json
{
  "version": "1.0",
  "pc_name": "PC-UFFICIO-01",
  "admin_user": {
    "username": "admin_locale",
    "password": "...",
    "remove_setup_account": true
  },
  "software": [...],
  "bloatware": [...],
  "power_plan": {...},
  "extras": {...}
}
```

---

## 🔐 Sicurezza Dati Sensibili

### Password Admin (non salvare in chiaro)

```php
// Nel controller, prima di salvare:
$config = $request->validated('configurazione');

// Cifra la password admin con AES-256-GCM
$config['admin_user']['password'] = EncryptionService::encrypt(
    $config['admin_user']['password'],
    config('app.key')
);

// Salva config cifrata
$wizard->configurazione = $config;
$wizard->save();
```

### Cosa NON salvare mai in chiaro

- ❌ Password Windows
- ❌ Password WiFi
- ❌ API key esterne
- ✅ Tutto il resto (nomi, software, power plan, ecc.) va in chiaro nel JSON

---

## 🌱 Seeder Esempio

```php
<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SoftwareLibrarySeeder::class,
            WizardSeeder::class,
        ]);
    }
}
```

---

## 📊 Relazioni Modelli (Eloquent)

```php
// User
public function wizards()       { return $this->hasMany(Wizard::class); }
public function templates()     { return $this->hasMany(Template::class); }
public function executionLogs() { return $this->hasMany(ExecutionLog::class, 'tecnico_user_id'); }

// Template
public function user()          { return $this->belongsTo(User::class); }
public function wizards()       { return $this->hasMany(Wizard::class); }

// Wizard
public function user()          { return $this->belongsTo(User::class); }
public function template()      { return $this->belongsTo(Template::class); }
public function executionLogs() { return $this->hasMany(ExecutionLog::class); }

// ExecutionLog
public function wizard()        { return $this->belongsTo(Wizard::class); }
public function tecnico()       { return $this->belongsTo(User::class, 'tecnico_user_id'); }
public function report()        { return $this->hasOne(Report::class); }

// Report
public function executionLog()  { return $this->belongsTo(ExecutionLog::class); }
```

---

## 🚀 Commands Util

```bash
# Crea migration nuova
php artisan make:migration create_table_name

# Esegui migration
php artisan migrate

# Rollback ultima batch
php artisan migrate:rollback

# Seeding
php artisan db:seed

# Seeding con classe specifica
php artisan db:seed --class=UserSeeder

# Refresca DB (drop + migrate + seed)
php artisan migrate:fresh --seed
```

---

## 📌 Nota: Soft Deletes

Implementato su:

- ✅ `users` — permette revocare accesso senza perdere audit trail
- ✅ `templates` — permette mantenere integrità wizard storici
- ✅ `wizards` — permette mantenere history completa

Non implementato su:

- ❌ `software_library` — i record sono stateless
- ❌ `execution_logs` — dati storici immutabili
- ❌ `reports` — dati archiviati

---

**[Modifiche apportate: Unione 0008 + 0105, 6 migration complete, seeder example, sicurezza dati, relazioni Eloquent]**

**Vedi anche:**

- `docs/schemas/wizard-config.schema.json` — Struttura JSON configurazione
- `docs/0120-agent-api-reference.md` — API che usa questi dati
