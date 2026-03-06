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