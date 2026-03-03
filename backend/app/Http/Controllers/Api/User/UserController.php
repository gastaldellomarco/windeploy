<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
    // Restituisce una lista di utenti per la web app (admin)
    // La risposta è normalizzata come { data: [...] } per compatibilità con il frontend
    $users = User::select(['id', 'nome', 'email', 'ruolo', 'attivo', 'last_login', 'last_login_ip', 'created_at'])->get();

    return response()->json(['data' => $users]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Valida i campi in arrivo
        $v = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'ruolo' => ['required', 'in:admin,tecnico,viewer'],
            'password_temporanea' => ['required', 'string', 'min:8'],
        ]);

        if ($v->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $v->errors()], 422);
        }

        // Crea utente
        $user = User::create([
            'nome' => $request->input('nome'),
            'email' => $request->input('email'),
            'ruolo' => $request->input('ruolo'),
            'password' => Hash::make($request->input('password_temporanea')),
            'attivo' => true,
            // registra IP reale del client (non mocked)
            'last_login_ip' => $request->ip(),
        ]);

        // Assegna ruolo con Spatie (se disponibile)
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($request->input('ruolo'));
        }

        return response()->json(['data' => $user], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json(['data' => $user]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Action-based updates
        $action = $request->input('action');
        if ($action === 'reset_password') {
            // Generate a temporary password and return it once
            $temp = bin2hex(random_bytes(6)); // 12 hex chars
            $user->password = Hash::make($temp);
            $user->save();

            // Do NOT log the plaintext password server-side. Return it only once.
            return response()->json(['password_temporanea' => $temp]);
        }

        // Update fields: ruolo, attivo
        $changed = false;
        if ($request->has('ruolo')) {
            $ruolo = $request->input('ruolo');
            if (in_array($ruolo, ['admin','tecnico','viewer'])) {
                $user->ruolo = $ruolo;
                // sync Spatie roles
                if (method_exists($user, 'syncRoles')) {
                    $user->syncRoles([$ruolo]);
                }
                $changed = true;
            }
        }

        if ($request->has('attivo')) {
            $user->attivo = boolval($request->input('attivo'));
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return response()->json(['data' => $user]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent deleting the last admin — basic safety (optional)
        try {
            $user->delete();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Unable to delete user'], 500);
        }

        return response()->json(null, 204);
    }
}
