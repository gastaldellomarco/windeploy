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