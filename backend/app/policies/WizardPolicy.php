<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wizard;

class WizardPolicy
{
    public function view(User $user, Wizard $wizard)
    {
        return $user->ruolo === 'admin' || $wizard->user_id === $user->id;
    }

    public function update(User $user, Wizard $wizard)
    {
        return ($user->ruolo === 'admin' || $wizard->user_id === $user->id);
    }

    public function delete(User $user, Wizard $wizard)
    {
        return ($user->ruolo === 'admin' || $wizard->user_id === $user->id);
    }
}