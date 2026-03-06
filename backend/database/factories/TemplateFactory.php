<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template>
 */
class TemplateFactory extends Factory
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
            'descrizione' => fake()->sentence(),
            'user_id' => User::factory(),
            'configurazione' => [
                'version' => '1.0',
                'pc_name' => 'TEMPLATE-' . strtoupper(fake()->bothify('????')),
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
        ];
    }

    /**
     * Indicate that the template should be for developer workstations.
     */
    public function forDevelopers(): static
    {
        return $this->state(fn (array $attributes) => [
            'nome' => 'Developer Workstation',
            'descrizione' => 'Template con tools di sviluppo (VS Code, Git, Node.js, Docker)',
            'configurazione' => array_merge($attributes['configurazione'], [
                'software' => [
                    ['winget_id' => 'Microsoft.VisualStudioCode', 'name' => 'Visual Studio Code'],
                    ['winget_id' => 'Git.Git', 'name' => 'Git'],
                    ['winget_id' => 'OpenJS.NodeJS', 'name' => 'Node.js'],
                    ['winget_id' => 'Docker.DockerDesktop', 'name' => 'Docker Desktop'],
                ],
            ]),
        ]);
    }

    /**
     * Indicate that the template should be for general office use.
     */
    public function forOffice(): static
    {
        return $this->state(fn (array $attributes) => [
            'nome' => 'Office Workstation',
            'descrizione' => 'Template per lavori d\'ufficio con browser e utility comuni',
            'configurazione' => array_merge($attributes['configurazione'], [
                'software' => [
                    ['winget_id' => 'Google.Chrome', 'name' => 'Google Chrome'],
                    ['winget_id' => '7zip.7zip', 'name' => '7-Zip'],
                    ['winget_id' => 'VideoLAN.VLC', 'name' => 'VLC Media Player'],
                ],
            ]),
        ]);
    }
}
