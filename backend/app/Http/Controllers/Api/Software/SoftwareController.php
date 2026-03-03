<?php

namespace App\Http\Controllers\Api\Software;

use App\Http\Controllers\Controller;
use App\Http\Requests\Software\SoftwareLibraryStoreRequest;
use App\Http\Requests\Software\SoftwareLibraryUpdateRequest;
use App\Http\Resources\SoftwareLibraryResource;
use App\Models\SoftwareLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // per chiamata a winget.run (esempio)
use Symfony\Component\HttpFoundation\Response;

class SoftwareController extends Controller
{
    /**
     * Elenco software (accessibile a tutti).
     * Filtri: categoria, attivo, tipo, search (nome)
     */
    public function index(Request $request)
    {
        $query = SoftwareLibrary::query();

        if ($request->has('attivo')) {
            $query->where('attivo', $request->boolean('attivo'));
        }

        if ($request->has('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('search')) {
            $query->where('nome', 'like', '%' . $request->search . '%');
        }

        $software = $query->latest()->paginate(20);

        return SoftwareLibraryResource::collection($software);
    }

    /**
     * Crea nuovo software (solo admin).
     */
    public function store(SoftwareLibraryStoreRequest $request)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono aggiungere software.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $data['aggiunto_da'] = $user->id;

        $software = SoftwareLibrary::create($data);

        return new SoftwareLibraryResource($software);
    }

    /**
     * Mostra dettaglio software.
     */
    public function show(SoftwareLibrary $softwareLibrary)
    {
        // Accessibile a tutti (anche viewer)
        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Aggiorna software (solo admin).
     */
    public function update(SoftwareLibraryUpdateRequest $request, SoftwareLibrary $softwareLibrary)
    {
        $user = $request->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Solo gli admin possono modificare software.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->update($request->validated());

        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Elimina software (soft delete, solo admin).
     */
    public function destroy(SoftwareLibrary $softwareLibrary)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->delete();

        return response()->json(['message' => 'Software eliminato.']);
    }

    /**
     * Attiva/disattiva software (solo admin).
     */
    public function toggleActive(SoftwareLibrary $softwareLibrary)
    {
        $user = request()->user();

        if ($user->ruolo !== 'admin') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $softwareLibrary->attivo = !$softwareLibrary->attivo;
        $softwareLibrary->save();

        return new SoftwareLibraryResource($softwareLibrary);
    }

    /**
     * Cerca software su winget.run (o altra fonte) e restituisce lista.
     * Accessibile a tutti (per ricerca in fase di creazione wizard).
     */
    public function searchWinget(Request $request)
    {
        $request->validate(['query' => 'required|string|min:2']);

        // Esempio: chiamata a winget.run API (non ufficiale, solo dimostrativa)
        // In produzione potresti usare un database locale di pacchetti winget
        $response = Http::get('https://api.winget.run/v2/packages/search', [
            'query' => $request->query,
            'take' => 20,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Servizio di ricerca non disponibile.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $packages = $response->json()['Packages'] ?? [];

        // Mappa i dati in un formato utile per il frontend
        $results = collect($packages)->map(function ($pkg) {
            return [
                'id' => $pkg['Id'],
                'nome' => $pkg['Name'],
                'versione' => $pkg['Latest']['Version'] ?? null,
                'publisher' => $pkg['Publisher'],
                'tipo' => 'winget',
                'identificatore' => $pkg['Id'],
            ];
        });

        return response()->json($results);
    }
}