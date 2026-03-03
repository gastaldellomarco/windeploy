<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea utente admin se non esiste già
        $user = User::firstOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'name'     => 'Admin WinDeploy',
                'password' => Hash::make('ChangeThisAdminPassword!'),
            ]
        );

        // Assegna ruolo "admin" (devi aver creato il ruolo prima)
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }
    }
}
