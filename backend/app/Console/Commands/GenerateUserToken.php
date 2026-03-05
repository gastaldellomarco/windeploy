<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;

class GenerateUserToken extends Command
{
    protected $signature = 'windeploy:generate-user-token {userId : The user ID to generate a JWT for}';
    protected $description = '[DEV ONLY] Generate a JWT token for a given user ID';

    public function handle(): int
    {
        // Enforce dev-only usage
        if (app()->isProduction()) {
            $this->error('This command is not available in production.');
            return self::FAILURE;
        }

        $userId = (int) $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User ID {$userId} not found.");
            return self::FAILURE;
        }

        // Cast TTL values to int to avoid Carbon errors
        config(['jwt.ttl' => (int) config('jwt.ttl')]);
        config(['jwt.refresh_ttl' => (int) config('jwt.refresh_ttl')]);

        $token = JWTAuth::fromUser($user);

        $this->info("Token for user [{$user->email}]:");
        $this->line($token);

        return self::SUCCESS;
    }
}
