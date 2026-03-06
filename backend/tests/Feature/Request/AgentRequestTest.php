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