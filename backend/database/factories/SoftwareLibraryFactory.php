<?php

namespace Database\Factories;

use App\Models\SoftwareLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SoftwareLibrary>
 */
class SoftwareLibraryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $softwareNames = [
            'Google Chrome' => 'Google.Chrome',
            'Mozilla Firefox' => 'Mozilla.Firefox',
            'Visual Studio Code' => 'Microsoft.VisualStudioCode',
            '7-Zip' => '7zip.7zip',
            'VLC Media Player' => 'VideoLAN.VLC',
            'Notepad++' => 'Notepad++.Notepad++',
            'Git' => 'Git.Git',
            'Python 3' => 'Python.Python.3',
            'Node.js' => 'OpenJS.NodeJS',
            'Docker Desktop' => 'Docker.DockerDesktop',
        ];

        $name = fake()->randomElement(array_keys($softwareNames));
        $wingetId = $softwareNames[$name];

        return [
            'nome' => $name,
            'tipo' => 'winget',
            'identificatore' => $wingetId,
            'download_url' => null,
        ];
    }

    /**
     * Indicate that the software should be a custom/manual installation.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'manuale',
            'download_url' => fake()->url(),
        ]);
    }
}
