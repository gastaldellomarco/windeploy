<?php

namespace Database\Factories;

use App\Models\ExecutionLog;
use App\Models\Wizard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExecutionLog>
 */
class ExecutionLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'wizard_id' => Wizard::factory(),
            'stato' => 'avviato',
            'started_at' => $startedAt,
            'completed_at' => null,
            'errore' => null,
        ];
    }

    /**
     * Indicate that the execution should be completed successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'completato',
            'completed_at' => fake()->dateTimeBetween($attributes['started_at'], 'now'),
            'errore' => null,
        ]);
    }

    /**
     * Indicate that the execution should have failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'errore',
            'completed_at' => fake()->dateTimeBetween($attributes['started_at'], 'now'),
            'errore' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the execution should be in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'in_esecuzione',
        ]);
    }

    /**
     * Indicate that the execution should be aborted.
     */
    public function aborted(): static
    {
        return $this->state(fn (array $attributes) => [
            'stato' => 'abortito',
            'completed_at' => fake()->dateTimeBetween($attributes['started_at'], 'now'),
            'errore' => 'Esecuzione abortita dall\'utente',
        ]);
    }
}
