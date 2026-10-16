<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Un tournoi importé n'est visible que par le compte qui l'a importé (404 sinon).
 */
class TournamentPolicy
{
    public function view(User $user, Tournament $tournament): Response
    {
        return $user->id === $tournament->user_id ? Response::allow() : Response::denyAsNotFound();
    }
}
