## File 1: WizardRequestTest

```php
<?php
// backend/tests/Feature/Requests/WizardRequestTest.php

namespace Tests\Feature\Requests;

use App\Models\User;
use App\Models\Wizard;
use App\Models\SoftwareLibrary;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WizardRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Crea un utente tecnico di default (può creare wizard)
        $this->tecnico = User::factory()->create(['ruolo' => 'tecnico']);
        $this->tecnico->assignRole('tecnico');

        // Crea un viewer per test 403
        $this->viewer = User::factory()->create(['ruolo' => 'viewer']);
        $this->viewer->assignRole('viewer');

        // Crea un software e un template necessari per test validi
        $this->software = SoftwareLibrary::factory()->create();
        $this->template = Template::factory()->create(['user_id' => $this->tecnico->id]);
    }

    /**
     * Test 1: POST /api/wizards senza campo 'nome' -> 422 con errore su 'nome'
     */
    public function test_store_wizard_rejects_missing_nome()
    {
        Sanctum::actingAs($this->tecnico);

        $payload = [
            'configurazione' => [
                'pc_name' => 'PC-TEST',
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'Password123!',
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['nome']);
    }

    /**
     * Test 2: pc_name con caratteri non validi (spazi, caratteri speciali) -> 422
     */
    public function test_store_wizard_rejects_invalid_configurazione_pc_name()
    {
        Sanctum::actingAs($this->tecnico);

        $payload = [
            'nome' => 'Wizard di test',
            'configurazione' => [
                'pc_name' => 'PC CON SPAZI!',  // non valido
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'Password123!',
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['configurazione.pc_name']);
    }

    /**
     * Test 3: pc_name con 16 caratteri (supera limite 15) -> 422
     */
    public function test_store_wizard_rejects_pc_name_too_long()
    {
        Sanctum::actingAs($this->tecnico);

        $payload = [
            'nome' => 'Wizard di test',
            'configurazione' => [
                'pc_name' => 'COMPUTERNAME123456',  // 16 caratteri
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'Password123!',
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['configurazione.pc_name']);
    }

    /**
     * Test 4: software_list con ID inesistente -> 422
     */
    public function test_store_wizard_rejects_nonexistent_software_id()
    {
        Sanctum::actingAs($this->tecnico);

        $payload = [
            'nome' => 'Wizard con software inesistente',
            'configurazione' => [
                'pc_name' => 'PC-TEST',
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'Password123!',
                ],
                'software_list' => [
                    ['id' => 99999]  // ID inesistente
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['configurazione.software_list.0.id']);
    }

    /**
     * Test 5: payload completo e valido -> 201 Created
     */
    public function test_store_wizard_succeeds_with_valid_payload()
    {
        Sanctum::actingAs($this->tecnico);

        $payload = [
            'nome' => 'Wizard valido',
            'template_id' => $this->template->id,
            'configurazione' => [
                'pc_name' => 'PC-TEST-01',
                'admin_user' => [
                    'username' => 'adminlocale',
                    'password' => 'SecurePass123!',
                ],
                'software_list' => [
                    ['id' => $this->software->id]
                ],
                'power_plan' => 'balanced',
                'bloatware_to_remove' => ['Microsoft.XboxApp'],
                'extras' => [
                    'timezone' => 'Europe/Rome',
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'data' => ['id', 'nome', 'codice_univoco']
                 ]);

        // Verifica che il wizard sia stato creato nel DB
        $this->assertDatabaseHas('wizards', [
            'nome' => 'Wizard valido',
            'user_id' => $this->tecnico->id,
        ]);
    }

    /**
     * Test 6: viewer tenta di creare wizard -> 403 Forbidden
     */
    public function test_store_wizard_forbidden_for_viewer_role()
    {
        Sanctum::actingAs($this->viewer);

        $payload = [
            'nome' => 'Tentativo viewer',
            'configurazione' => [
                'pc_name' => 'PC-TEST',
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'Password123!',
                ],
            ],
        ];

        $response = $this->postJson('/api/wizards', $payload);

        $response->assertStatus(403);  // o 403 se il middleware role:admin,tecnico non presente, ma nel controller c'è il check
        // In realtà il controller WizardController ha if ($user->ruolo === 'viewer') → 403
    }

    /**
     * Test 7: PATCH /api/wizards/{id} con solo campo 'nome' -> 200 OK (aggiornamento parziale)
     */
    public function test_update_wizard_accepts_partial_payload()
    {
        Sanctum::actingAs($this->tecnico);

        // Crea un wizard esistente
        $wizard = Wizard::factory()->create([
            'user_id' => $this->tecnico->id,
            'stato' => 'bozza',  // per poterlo modificare
        ]);

        $response = $this->patchJson('/api/wizards/' . $wizard->id, [
            'nome' => 'Nuovo nome modificato'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('wizards', [
            'id' => $wizard->id,
            'nome' => 'Nuovo nome modificato'
        ]);
    }
}
```

---

## File 2: UserRequestTest

```php
<?php
// backend/tests/Feature/Requests/UserRequestTest.php

namespace Tests\Feature\Requests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crea un admin autenticato per i test di store/update
        $this->admin = User::factory()->create(['ruolo' => 'admin']);
        $this->admin->assignRole('admin');

        // Crea un tecnico per testare permessi negati
        $this->tecnico = User::factory()->create(['ruolo' => 'tecnico']);
        $this->tecnico->assignRole('tecnico');
    }

    /**
     * Test 1: POST /api/users con email già usata -> 422
     */
    public function test_store_user_rejects_duplicate_email()
    {
        Sanctum::actingAs($this->admin);

        $existingUser = User::factory()->create(['email' => 'duplicate@test.com']);

        $payload = [
            'nome' => 'Nuovo Utente',
            'email' => 'duplicate@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'ruolo' => 'tecnico',
            'attivo' => true,
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test 2: password troppo corta (7 caratteri) -> 422
     */
    public function test_store_user_rejects_weak_password()
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'nome' => 'Nuovo Utente',
            'email' => 'new@test.com',
            'password' => 'Pass12!', // 7 caratteri
            'password_confirmation' => 'Pass12!',
            'ruolo' => 'tecnico',
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test 3: password e conferma non corrispondono -> 422
     */
    public function test_store_user_rejects_password_confirmation_mismatch()
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'nome' => 'Nuovo Utente',
            'email' => 'new@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Diversa123!',
            'ruolo' => 'tecnico',
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test 4: ruolo non valido -> 422
     */
    public function test_store_user_rejects_invalid_role()
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'nome' => 'Nuovo Utente',
            'email' => 'new@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'ruolo' => 'superadmin', // non valido
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['ruolo']);
    }

    /**
     * Test 5: admin tenta di modificare il proprio ruolo -> 422 (custom rule)
     */
    public function test_update_user_cannot_change_own_role()
    {
        Sanctum::actingAs($this->admin);  // admin che fa la richiesta su se stesso

        $payload = [
            'ruolo' => 'viewer',  // tenta di degradarsi
        ];

        $response = $this->patchJson('/api/users/' . $this->admin->id, $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['ruolo']);
    }

    /**
     * Test 6: tecnico tenta di creare un utente -> 403 Forbidden
     */
    public function test_store_user_forbidden_for_tecnico()
    {
        Sanctum::actingAs($this->tecnico);  // tecnico autenticato

        $payload = [
            'nome' => 'Tentativo non autorizzato',
            'email' => 'hacker@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'ruolo' => 'viewer',
        ];

        $response = $this->postJson('/api/users', $payload);

        $response->assertStatus(403);
    }
}
```

---

## File 3: AgentRequestTest

```php
<?php
// backend/tests/Feature/Requests/AgentRequestTest.php

namespace Tests\Feature\Requests;

use App\Models\Wizard;
use App\Models\ExecutionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AgentRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Helper per generare un JWT valido per un dato wizard.
     * Simula la logica di AgentAuthController.
     */
    protected function generateAgentToken(Wizard $wizard): string
    {
        $payload = JWTFactory::customClaims([
            'sub'         => $wizard->id,
            'wizard_id'   => $wizard->id,
            'wizard_code' => $wizard->codice_univoco,
            'mac_address' => str_replace([':', '-', '.'], '', strtolower($wizard->configurazione['mac_address'] ?? '')),
            'type'        => 'agent',
            'iat'         => now()->timestamp,
            'exp'         => now()->addHours(4)->timestamp,
        ])->make();

        return JWTAuth::encode($payload)->get();
    }

    /**
     * Test 1: POST /agent/auth con wizard_code formato errato -> 422
     */
    public function test_agent_start_rejects_invalid_wizard_code_format()
    {
        $payload = [
            'wizard_code' => 'INVALID',  // non rispetta regex WD-XXXX
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ];

        $response = $this->postJson('/api/agent/auth', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['wizard_code']);
    }

    /**
     * Test 2: POST /agent/auth con wizard_code valido ma wizard scaduto -> 404 o 422?
     * Secondo 0111, il controller restituisce 404 per wizard non trovato o scaduto.
     */
    public function test_agent_start_rejects_expired_wizard()
    {
        // Crea wizard con expires_at nel passato
        $wizard = Wizard::factory()->create([
            'codice_univoco' => 'WD-TEST1',
            'expires_at' => now()->subDay(),
            'stato' => 'pronto',
        ]);

        $payload = [
            'wizard_code' => 'WD-TEST1',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ];

        $response = $this->postJson('/api/agent/auth', $payload);

        $response->assertStatus(404);  // come da 0111
    }

    /**
     * Test 3: POST /agent/step con status non valido -> 422
     */
    public function test_agent_step_rejects_invalid_status()
    {
        // Crea wizard e execution log attivo
        $wizard = Wizard::factory()->create(['codice_univoco' => 'WD-STEP1']);
        $log = ExecutionLog::factory()->create([
            'wizard_id' => $wizard->id,
            'completed_at' => null,
        ]);

        $token = $this->generateAgentToken($wizard);

        $payload = [
            'wizard_code' => 'WD-STEP1',
            'step'        => 'test_step',
            'status'      => 'unknown_status', // non nella whitelist
            'message'     => 'Test message',
            'progress'    => 50,
            'timestamp'   => now()->toIso8601String() . 'Z',
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->postJson('/api/agent/step', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test 4: POST /agent/step con progress fuori range (150) -> 422
     */
    public function test_agent_step_rejects_progress_out_of_range()
    {
        $wizard = Wizard::factory()->create(['codice_univoco' => 'WD-STEP2']);
        $log = ExecutionLog::factory()->create([
            'wizard_id' => $wizard->id,
            'completed_at' => null,
        ]);

        $token = $this->generateAgentToken($wizard);

        $payload = [
            'wizard_code' => 'WD-STEP2',
            'step'        => 'test_step',
            'status'      => 'in_progress',
            'message'     => 'Test message',
            'progress'    => 150, // > 100
            'timestamp'   => now()->toIso8601String() . 'Z',
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->postJson('/api/agent/step', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['progress']);
    }

    /**
     * Test 5: POST /agent/step senza campo 'message' -> 422
     */
    public function test_agent_step_rejects_missing_message()
    {
        $wizard = Wizard::factory()->create(['codice_univoco' => 'WD-STEP3']);
        $log = ExecutionLog::factory()->create([
            'wizard_id' => $wizard->id,
            'completed_at' => null,
        ]);

        $token = $this->generateAgentToken($wizard);

        $payload = [
            'wizard_code' => 'WD-STEP3',
            'step'        => 'test_step',
            'status'      => 'in_progress',
            // 'message' mancante
            'progress'    => 50,
            'timestamp'   => now()->toIso8601String() . 'Z',
        ];

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->postJson('/api/agent/step', $payload);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['message']);
    }
}
```

---

## Factory mancanti (da creare se non esistono)

Nei test sopra abbiamo usato factory per `Wizard`, `SoftwareLibrary`, `Template`, `ExecutionLog`, `User`. Se non esistono già, ecco le definizioni minime necessarie:

```php
// database/factories/UserFactory.php (già esistente in Laravel, eventualmente aggiungere ruolo)
$factory->define(User::class, function (Faker $faker) {
    return [
        'nome' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => bcrypt('password'),
        'ruolo' => 'tecnico',
        'attivo' => true,
    ];
});

// database/factories/WizardFactory.php
$factory->define(Wizard::class, function (Faker $faker) {
    return [
        'nome' => $faker->words(3, true),
        'user_id' => User::factory(),
        'codice_univoco' => 'WD-' . strtoupper($faker->bothify('????')),
        'stato' => 'bozza',
        'configurazione' => [
            'pc_name' => 'PC-' . $faker->bothify('??###'),
            'admin_user' => ['username' => 'admin', 'password_encrypted' => 'encrypted'],
        ],
        'expires_at' => now()->addHours(24),
    ];
});

// database/factories/SoftwareLibraryFactory.php
$factory->define(SoftwareLibrary::class, function (Faker $faker) {
    return [
        'nome' => $faker->words(2, true),
        'tipo' => 'winget',
        'identificatore' => $faker->uuid,
    ];
});

// database/factories/TemplateFactory.php
$factory->define(Template::class, function (Faker $faker) {
    return [
        'nome' => $faker->words(3, true),
        'user_id' => User::factory(),
        'configurazione' => [],
    ];
});

// database/factories/ExecutionLogFactory.php
$factory->define(ExecutionLog::class, function (Faker $faker) {
    return [
        'wizard_id' => Wizard::factory(),
        'stato' => 'avviato',
        'started_at' => now(),
    ];
});
```

---

## Commit finale

```bash
git add tests/Feature/Requests/WizardRequestTest.php
git add tests/Feature/Requests/UserRequestTest.php
git add tests/Feature/Requests/AgentRequestTest.php
git add database/factories/  # se nuove factory

git commit -m "test(validation): add feature tests for FormRequest layer

- WizardRequestTest: 7 test covering missing fields, invalid pc_name, software_id, authorization, partial update
- UserRequestTest: 6 test covering duplicate email, weak password, confirmation, invalid role, self-role-change protection, authorization
- AgentRequestTest: 5 test covering invalid wizard_code, expired wizard, invalid status, progress range, missing message
- All tests use RefreshDatabase, Sanctum actingAs for web routes, JWT token generation for agent routes"
```