<?php

namespace Database\Factories;

use App\Models\ExecutionLog;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $executedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'execution_id' => ExecutionLog::factory(),
            'pc_name' => 'PC-' . fake()->bothify('??###'),
            'status' => 'success',
            'duration_seconds' => fake()->numberBetween(300, 3600),
            'steps_executed' => fake()->numberBetween(10, 50),
            'steps_failed' => 0,
            'installed_software' => fake()->numberBetween(5, 20),
            'removed_bloatware' => fake()->numberBetween(2, 10),
            'errors' => [],
            'warnings' => [],
            'executed_at' => $executedAt,
            'completed_at' => (clone $executedAt)->modify('+' . fake()->numberBetween(300, 3600) . ' seconds'),
        ];
    }

    /**
     * Indicate that the report should show a failed execution.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'steps_failed' => fake()->numberBetween(1, 5),
            'errors' => [
                fake()->sentence(),
                fake()->sentence(),
            ],
        ]);
    }

    /**
     * Indicate that the report should show a partial success (with warnings).
     */
    public function withWarnings(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partial',
            'warnings' => [
                'Unable to remove legacy software: ' . fake()->word(),
                'Power plan configuration skipped due to admin privileges',
                'Network configuration pending user confirmation',
            ],
        ]);
    }

    /**
     * Indicate that the report should have been aborted.
     */
    public function aborted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'aborted',
            'steps_executed' => fake()->numberBetween(1, 10),
            'errors' => ['Execution aborted by user'],
        ]);
    }
}
