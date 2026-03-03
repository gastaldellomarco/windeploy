<?php

namespace App\Http\Controllers\Api\Template;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\TemplateStoreRequest;
use App\Http\Requests\Template\TemplateUpdateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /**
     * Elenco template.
     * - Admin: tutti i template
     * - Tecnico: globali + personali
     * - Viewer: solo globali (o nessuno? qui diamo globali + propri per viewer? per semplicità diamo globali)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Template::with('user');

        if ($user->ruolo === 'admin') {
            // Admin vede tutto
        } elseif ($user->ruolo === 'tecnico') {
            // Tecnico vede globali + propri
            $query->where(function ($q) use ($user) {
                $q->where('scope', 'globale')
                  ->orWhere('user_id', $user->id);
            });
        } else { // viewer
            // Viewer vede solo globali
            $query->where('scope', 'globale');
        }

        // Filtri aggiuntivi (es. nome, descrizione)
        if ($request->has('nome')) {
            $query->where('nome', 'like', '%' . $request->nome . '%');
        }

        $templates = $query->latest()->paginate(20);

        return TemplateResource::collection($templates);
    }

    /**
     * Crea un nuovo template.
     */
    public function store(TemplateStoreRequest $request)
    {
        $user = $request->user();

        // Viewer non può creare
        if ($user->ruolo === 'viewer') {
            return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $data['user_id'] = $user->id;

        // Se l'utente non è admin, forza scope = personale
        if ($user->ruolo !== 'admin' && ($data['scope'] ?? 'personale') === 'globale') {
            return response()->json(['message' => 'Solo gli admin possono creare template globali.'], Response::HTTP_FORBIDDEN);
        }

        $template = Template::create($data);

        return new TemplateResource($template);
    }

    /**
     * Mostra dettaglio template.
     */
    public function show(Template $template)
    {
        $user = request()->user();

        // Admin vede tutto, altrimenti deve essere proprietario o globale
        if ($user->ruolo !== 'admin') {
            if ($template->scope === 'personale' && $template->user_id !== $user->id) {
                return response()->json(['message' => 'Accesso negato.'], Response::HTTP_FORBIDDEN);
            }
        }

        return new TemplateResource($template->load('user'));
    }

    /**
     * Aggiorna template.
     */
    public function update(TemplateUpdateRequest $request, Template $template)
    {
        $user = $request->user();

        // Admin può modificare qualsiasi template
        if ($user->ruolo === 'admin') {
            // ok
        } else {
            // Tecnico può modificare solo i propri personali
            if ($template->user_id !== $user->id) {
                return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
            }
            // Non può trasformare in globale
            if ($request->has('scope') && $request->scope === 'globale') {
                return response()->json(['message' => 'Non puoi trasformare un template personale in globale.'], Response::HTTP_FORBIDDEN);
            }
        }

        $template->update($request->validated());

        return new TemplateResource($template);
    }

    /**
     * Elimina template (soft delete).
     */
    public function destroy(Template $template)
    {
        $user = request()->user();

        // Admin può eliminare qualsiasi template
        if ($user->ruolo === 'admin') {
            $template->delete();
            return response()->json(['message' => 'Template eliminato.']);
        }

        // Tecnico può eliminare solo i propri personali
        if ($template->user_id === $user->id && $template->scope === 'personale') {
            $template->delete();
            return response()->json(['message' => 'Template eliminato.']);
        }

        return response()->json(['message' => 'Azione non consentita.'], Response::HTTP_FORBIDDEN);
    }
}