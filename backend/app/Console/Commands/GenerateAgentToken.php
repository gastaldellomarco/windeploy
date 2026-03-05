<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class GenerateAgentToken extends Command
{
    protected $signature = 'windeploy:generate-agent-token
                            {wizardId : The wizard ID}
                            {mac : MAC address of the target machine (e.g. aa:bb:cc:dd:ee:ff)}
                            {--hours=4 : Token expiry in hours}';

    protected $description = '[DEV ONLY] Generate a JWT agent token for a wizard deployment';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This command is not available in production.');
            return self::FAILURE;
        }

        $wizardId = (int) $this->argument('wizardId');
        $mac      = strtolower($this->argument('mac'));
        $hours    = (int) $this->option('hours');

        // Validate MAC format
        if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
            $this->error("Invalid MAC address format. Expected: aa:bb:cc:dd:ee:ff");
            return self::FAILURE;
        }

        $now    = Carbon::now();
        $expiry = $now->copy()->addHours($hours);

        $payload = JWTFactory::customClaims([
            'sub'         => $wizardId,
            'wizard_id'   => $wizardId,
            'mac_address' => $mac,
            'type'        => 'agent',
            'iat'         => $now->timestamp,
            'exp'         => $expiry->timestamp,
        ])->make();

        $token = JWTAuth::encode($payload)->get();

        $this->info("Agent token for wizard [{$wizardId}] — MAC [{$mac}] — expires in {$hours}h:");
        $this->line($token);
        $this->warn('WARNING: This token grants agent access. Do not share or log it.');

        return self::SUCCESS;
    }
}
