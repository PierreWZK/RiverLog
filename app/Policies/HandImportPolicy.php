<?php

namespace App\Policies;

use App\Models\HandImport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Un import n'est accessible qu'à son propriétaire (404 sinon, comme pour les sessions).
 */
class HandImportPolicy
{
    public function delete(User $user, HandImport $import): Response
    {
        return $user->id === $import->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function retry(User $user, HandImport $import): Response
    {
        return $this->delete($user, $import);
    }
}
