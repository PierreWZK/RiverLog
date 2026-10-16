<?php

namespace App\Http\Controllers;

use App\Enums\PokerFormat;
use App\Http\Requests\PokerSessionRequest;
use App\Models\PokerSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ajout, modification et suppression des sessions du compte connecté.
 * La liste est affichée par le dashboard ; on y revient après chaque action.
 */
class PokerSessionController extends Controller
{
    public function create(): View
    {
        // Valeurs par défaut : maintenant (au quart d'heure), Cash Game
        $session = new PokerSession([
            'played_at' => now()->floorMinutes(15),
            'format' => PokerFormat::Cash,
        ]);

        return view('sessions.create', ['session' => $session, 'formats' => PokerFormat::options()]);
    }

    public function store(PokerSessionRequest $request): RedirectResponse
    {
        // La relation fixe user_id : la session appartient toujours au compte connecté
        $request->user()->pokerSessions()->create($request->validated());

        return to_route('dashboard')->with('status', 'Session ajoutée.');
    }

    public function edit(PokerSession $session): View
    {
        Gate::authorize('update', $session);

        return view('sessions.edit', ['session' => $session, 'formats' => PokerFormat::options()]);
    }

    public function update(PokerSessionRequest $request, PokerSession $session): RedirectResponse
    {
        Gate::authorize('update', $session);

        $session->update($request->validated());

        return to_route('dashboard')->with('status', 'Session modifiée.');
    }

    public function destroy(PokerSession $session): RedirectResponse
    {
        Gate::authorize('delete', $session);

        $session->delete();

        return to_route('dashboard')->with('status', 'Session supprimée.');
    }
}
