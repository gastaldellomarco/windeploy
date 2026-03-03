<?php

use App\Models\ExecutionLog;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('wizard.{wizardId}', function ($user, $wizardId) {
    // Autorizza l'utente a ricevere aggiornamenti per quel wizard
    // Puoi controllare se l'utente è admin o tecnico proprietario
    return true; // implementa logica appropriata
});
