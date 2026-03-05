<?php
// File: app/Http/Controllers/Api/User/UserController.php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * Lista utenti con filtri (ruolo, attivo), paginazione 20.
     * Accesso: solo admin (già gestito da middleware role:admin sulle route).
     */
    public function index(UserIndexRequest $request)
    {
        $query = User::query();

        if ($request->filled('ruolo')) {
            $query->where('ruolo', $request->input('ruolo'));
        }

        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        $users = $query->latest()->paginate(20);

        return UserResource::collection($users);
    }

    /**
     * Dettaglio utente + statistiche di base.
     * - numero wizard creati
     * - ultimo accesso
     */
    public function show(User $user): JsonResponse
    {
        $wizardsCount = Wizard::where('user_id', $user->id)->count();

        return response()->json([
            'user'  => new UserResource($user),
            'stats' => [
                'wizards_count' => $wizardsCount,
                'last_login'    => $user->last_login?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Crea un nuovo utente.
     * - Password: se non fornita, viene generata automaticamente.
     * - La password generata viene restituita UNA SOLA VOLTA nella response.
     * - Assegna ruolo Spatie in base a $request->ruolo.
     */
    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $plainPassword = $data['password'] ?? Str::random(16);
        $data['password'] = Hash::make($plainPassword);
        $data['attivo'] = $data['attivo'] ?? true;

        $user = User::create([
            'nome'     => $data['nome'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'ruolo'    => $data['ruolo'],
            'attivo'   => $data['attivo'],
        ]);

        // Allinea ruoli Spatie con campo ruolo
        $user->assignRole($data['ruolo']);

        return response()->json([
            'user'              => new UserResource($user),
            'generated_password'=> $request->filled('password') ? null : $plainPassword,
        ], Response::HTTP_CREATED);
    }

    /**
     * Aggiorna un utente esistente.
     * - Password opzionale: se non inviata non viene modificata.
     * - Se cambia il ruolo, sincronizza anche le Spatie roles.
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $data = $request->validated();

        if (isset($data['nome'])) {
            $user->nome = $data['nome'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (array_key_exists('attivo', $data)) {
            $user->attivo = $data['attivo'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (isset($data['ruolo']) && $data['ruolo'] !== $user->ruolo) {
            $user->ruolo = $data['ruolo'];
            $user->syncRoles([$data['ruolo']]);
        }

        $user->save();

        return new UserResource($user);
    }

    /**
     * Disattiva un account utente.
     * - Mai hard delete: setta solo attivo = false.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->attivo = false;
        $user->save();

        return response()->json(['message' => 'Utente disattivato.'], Response::HTTP_OK);
    }

    /**
     * Attiva / disattiva un account utente.
     */
    public function toggleActive(User $user)
    {
        $user->attivo = ! (bool) $user->attivo;
        $user->save();

        return new UserResource($user);
    }

    /**
     * Reset password:
     * - Genera una password casuale sicura (16 caratteri).
     * - La ritorna UNA SOLA VOLTA.
     * - Invia una email (MAIL_MAILER=log in locale).
     */
    public function resetPassword(User $user): JsonResponse
    {
        $plainPassword = Str::random(16);
        $user->password = Hash::make($plainPassword);
        $user->save();

        // Invia una mail semplice con la nuova password (finisce nel log in dev)
        Mail::raw(
            "Ciao {$user->nome},\n\n" .
            "la tua password è stata reimpostata.\n" .
            "Nuova password: {$plainPassword}\n\n" .
            "Ti consigliamo di cambiarla al primo accesso.\n",
            function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Reset password WinDeploy');
            }
        );

        return response()->json([
            'user'         => new UserResource($user),
            'new_password' => $plainPassword,
        ], Response::HTTP_OK);
    }
}
