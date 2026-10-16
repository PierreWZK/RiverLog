<?php

namespace App\Policies;

use App\Models\PokerSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Une session n'est visible et modifiable que par son propriétaire.
 *
 * Refus en 404 (et non 403) : on ne révèle pas qu'une session existe sur un autre compte.
 * Les sessions importées sont recalculées à chaque import : elles ne se modifient pas à la main.
 */
class PokerSessionPolicy
{
    public function update(User $user, PokerSession $session): Response
    {
        if ($user->id !== $session->user_id) {
            return Response::denyAsNotFound();
        }

        return $session->isImported()
            ? Response::deny('Cette session vient d\'un import : supprimez l\'import pour la retirer.')
            : Response::allow();
    }

    public function delete(User $user, PokerSession $session): Response
    {
        return $this->update($user, $session);
    }
}
