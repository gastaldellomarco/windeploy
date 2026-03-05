<?php
// File: app/Http/Controllers/Api/Software/SoftwareController.php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use App\Http\Requests\Software\SoftwareIndexRequest;
use App\Http\Requests\Software\SoftwareStoreRequest;
use App\Http\Requests\Software\SoftwareUpdateRequest;
use App\Http\Resources\SoftwareLibraryResource;
use App\Models\SoftwareLibrary;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SoftwareController extends Controller
{
    /**
     * Lista software con filtri e paginazione (20 per pagina).
     * Accesso: tutti i ruoli autenticati.
     */
    public function index(SoftwareIndexRequest $request)
    {
        $query = SoftwareLibrary::query();

        // Filtro per stato attivo (true/false)
        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        // Filtro per categoria
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        // Filtro per tipo (winget / exe / msi)
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        // Ricerca testuale sul nome
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('nome', 'like', '%' . $search . '%');
        }

        $software = $query->latest()->paginate(20);

        return SoftwareLibraryResource::collection($software);
    }

    /**
     * Dettaglio singolo software.
     * Accesso: tutti i ruoli autenticati.
     */
    public function show(SoftwareLibrary $software)
    {
        return new SoftwareLibraryResource($software);
    }

    /**
     * Crea una nuova entry software.
     * Accesso: solo admin.
     */
    public function store(SoftwareStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono aggiungere software.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validated();
        $data['aggiunto_da'] = $user->id;

        $software = SoftwareLibrary::create($data);

        return response()->json(new SoftwareLibraryResource($software), Response::HTTP_CREATED);
    }

    /**
     * Aggiorna una entry software esistente.
     * Accesso: solo admin.
     */
    public function update(SoftwareUpdateRequest $request, SoftwareLibrary $software)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Solo gli admin possono modificare software.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $software->update($request->validated());

        return new SoftwareLibraryResource($software);
    }

    /**
     * Elimina (soft delete) una entry software.
     * Se in futuro vorrai fare hard delete condizionato,
     * qui è il punto in cui verificare l'utilizzo nei wizard.
     * Accesso: solo admin.
     */
    public function destroy(SoftwareLibrary $software)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Azione non consentita.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Soft delete (SoftDeletes sul modello)
        $software->delete();

        return response()->json(['message' => 'Software eliminato.'], Response::HTTP_OK);
    }

    /**
     * Inverte il campo 'attivo' e restituisce il nuovo stato.
     * Accesso: solo admin.
     */
    public function toggleActive(SoftwareLibrary $software)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(
                ['message' => 'Azione non consentita.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $software->attivo = ! (bool) $software->attivo;
        $software->save();

        return response()->json([
            'id'     => $software->id,
            'attivo' => (bool) $software->attivo,
        ]);
    }
}
