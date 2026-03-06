<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wizard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wizard>
 */
class WizardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->words(3, true),
            'user_id' => User::factory(),
            'codice_univoco' => 'WD-' . strtoupper(fake()->bothify('????')),
            'stato' => 'bozza',
            'configurazione' => [
                'version' => '1.0',
                'pc_name' => 'PC-' . fake()->bothify('??###'),
                'admin_user' => [
                    'username' => 'admin',
                    'password' => 'P@ssw0rd123!',
                    'remove_setup_account' => true,
                ],
                'software' => [],
                'bloatware' => [],
                'power_plan' => [
                    'type' => 'balanced',
                    'screen_timeout_ac' => 10,
                    'sleep_timeout_ac' => 30,
                ],
                'extras' => [
                    'timezone' => 'Europe/Rome',
                    'language' => 'it-IT',
                    'keyboard_layout' => 'Italian',
                ],
            ],
            'expires_at' => now()->addHours(24),
        ];
    }

    /**
     * Indicate that the wizard should be in active execution state.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'esecuzione',
        ]);
    }

    /**
     * Indicate that the wizard should be completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'completato',
        ]);
    }

    /**
     * Indicate that the wizard should be expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHours(1),
        ]);
    }
}
