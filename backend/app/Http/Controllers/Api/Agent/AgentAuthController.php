<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\AgentAuthRequest;
use App\Models\Wizard;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class AgentAuthController extends Controller
{
    /**
     * Authenticate Windows agent by wizard code and MAC address.
     */
    public function auth(AgentAuthRequest $request)
    {
        $wizardCode = strtoupper($request->input('codice_wizard'));
        $macAddress = strtolower($request->input('mac_address'));

        // Lookup wizard by unique code
        $wizard = Wizard::where('codice_univoco', $wizardCode)->first();

        if (! $wizard) {
            return response()->json([
                'message' => 'Invalid wizard code.',
            ], 404);
        }

        // Check wizard not expired
        if ($wizard->expires_at && $wizard->expires_at->isPast()) {
            return response()->json([
                'message' => 'Wizard code has expired.',
            ], 410);
        }

        // Check wizard not already used (monouso)
        if ($wizard->used_at !== null || $wizard->stato === 'completato') {
            return response()->json([
                'message' => 'Wizard code already used.',
            ], 410);
        }

        // Optional: avoid starting from drafts
        if ($wizard->stato === 'bozza') {
            return response()->json([
                'message' => 'Wizard is not ready for execution.',
            ], 422);
        }

        $now   = Carbon::now();
        $expiry = $now->copy()->addHours(4);

        // Build custom JWT payload for agent
        $payload = JWTFactory::customClaims([
            'sub'         => $wizard->id,      // subject = wizard id
            'wizard_id'   => $wizard->id,
            'wizard_code' => $wizard->codice_univoco,
            'mac_address' => $macAddress,
            'type'        => 'agent',
            'iat'         => $now->timestamp,
            'exp'         => $expiry->timestamp,
        ])->make();

        $token = JWTAuth::encode($payload)->get();

        // NOTE: do NOT set used_at here; mark used_at on /api/agent/complete
        // to reflect a completed execution, not just an auth attempt.

        return response()->json([
            'token'       => $token,
            'expires_in'  => 4 * 60 * 60, // 4 hours in seconds
            'expires_at'  => $expiry->toIso8601String(),
            'wizard_id'   => $wizard->id,
        ]);
    }
}
