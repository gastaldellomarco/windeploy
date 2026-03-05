<?php
// File: app/Http/Controllers/Api/Template/TemplateController.php

namespace App\Http\Controllers\Api\Template;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\TemplateIndexRequest;
use App\Http\Requests\Template\TemplateStoreRequest;
use App\Http\Requests\Template\TemplateUpdateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TemplateController extends Controller
{
    /**
     * Elenco template globali + personali dell'utente loggato.
     * Due liste distinte nello stesso payload.
     */
    public function index(TemplateIndexRequest $request): JsonResponse
    {
        $user = $request->user();

        $globalQuery = Template::with('user')
            ->where('scope', 'globale');

        $personalQuery = Template::with('user')
            ->where('scope', 'personale')
            ->where('user_id', $user->id);

        if ($request->filled('nome')) {
            $name = $request->input('nome');
            $globalQuery->where('nome', 'like', '%' . $name . '%');
            $personalQuery->where('nome', 'like', '%' . $name . '%');
        }

        $globalTemplates = $globalQuery->latest()->get();
        $personalTemplates = $personalQuery->latest()->get();

        return response()->json([
            'global'   => TemplateResource::collection($globalTemplates),
            'personal' => TemplateResource::collection($personalTemplates),
        ]);
    }

    /**
     * Crea un nuovo template.
     * - Admin: può creare globali o personali.
     * - Tecnico: solo personali (scope forzato a 'personale').
     */
    public function store(TemplateStoreRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $data['user_id'] = $user->id;

        // Gestione scope in base al ruolo
        if ($user->ruolo === 'admin') {
            $data['scope'] = $data['scope'] ?? 'personale';
        } else {
            // Tecnici e viewer possono creare solo personali
            $data['scope'] = 'personale';
        }

        $template = Template::create($data);

        return new TemplateResource($template->load('user'));
    }

    /**
     * Dettaglio template.
     * - Scope globale: visibile a tutti.
     * - Scope personale: visibile solo al proprietario o admin.
     */
    public function show(Request $request, Template $template)
    {
        $user = $request->user();

        if (
            $template->scope === 'personale' &&
            $user->ruolo !== 'admin' &&
            $template->user_id !== $user->id
        ) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return new TemplateResource($template->load('user'));
    }

    /**
     * Aggiorna template.
     * - Admin: può modificare tutti.
     * - Tecnico: solo i propri personali, senza poterli trasformare in globali.
     */
    public function update(TemplateUpdateRequest $request, Template $template)
    {
        $user = $request->user();

        if ($user->ruolo === 'admin') {
            $data = $request->validated();
        } else {
            // Tecnico: solo proprietario di template personale
            if ($template->scope !== 'personale' || $template->user_id !== $user->id) {
                return response()->json(
                    ['message' => 'Azione non consentita.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            $data = $request->validated();

            // Non permettere di cambiare lo scope in globale
            unset($data['scope']);
        }

        $template->update($data);

        return new TemplateResource($template->load('user'));
    }

    /**
     * Elimina template.
     * - Admin: può eliminare tutti.
     * - Tecnico: solo i propri personali.
     */
    public function destroy(Request $request, Template $template)
    {
        $user = $request->user();

        if ($user->ruolo === 'admin') {
            $template->delete();

            return response()->json(['message' => 'Template eliminato.'], Response::HTTP_OK);
        }

        if ($template->scope === 'personale' && $template->user_id === $user->id) {
            $template->delete();

            return response()->json(['message' => 'Template eliminato.'], Response::HTTP_OK);
        }

        return response()->json(
            ['message' => 'Azione non consentita.'],
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Duplica un template.
     * - Admin: può duplicare qualsiasi template, mantenendo scope originale.
     * - Tecnico: può duplicare template globali o propri personali;
     *   la copia è sempre personale e assegnata all'utente.
     */
    public function duplicate(Request $request, Template $template)
    {
        $user = $request->user();

        // Autorizzazione vista come in show()
        if (
            $template->scope === 'personale' &&
            $user->ruolo !== 'admin' &&
            $template->user_id !== $user->id
        ) {
            return response()->json(
                ['message' => 'Accesso negato.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $newTemplate = $template->replicate();
        $newTemplate->nome = 'Copia di ' . $template->nome;

        if ($user->ruolo === 'admin') {
            // Mantieni scope e proprietario originale
            $newTemplate->user_id = $template->user_id;
            $newTemplate->scope = $template->scope;
        } else {
            // Tecnico: copia personale di proprietà del tecnico
            $newTemplate->user_id = $user->id;
            $newTemplate->scope = 'personale';
        }

        $newTemplate->save();

        return new TemplateResource($newTemplate->load('user'));
    }
}
