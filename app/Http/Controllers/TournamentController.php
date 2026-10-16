<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentStatsService;
use App\Support\TournamentFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Détail d'une partie importée. Les statistiques et la liste des parties sont sur la page Import ;
 * la démo publique (lecture seule, sans import possible) garde sa propre page.
 */
class TournamentController extends Controller
{
    public function __construct(private TournamentStatsService $statsService) {}

    /** Ancienne page Tournois : les liens et favoris existants mènent à la page Import. */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->to(route('imports.index', $request->query()).'#parties');
    }

    public function show(Tournament $tournament): View
    {
        Gate::authorize('view', $tournament);

        return $this->renderShow($tournament, demo: false);
    }

    public function demoIndex(Request $request): View
    {
        return view('tournaments.index', [
            'demo' => true,
            ...$this->statsService->overview($this->demoUser(), TournamentFilters::fromRequest($request)),
        ]);
    }

    public function demoShow(Tournament $tournament): View
    {
        abort_unless($tournament->user_id === $this->demoUser()->id, 404);

        return $this->renderShow($tournament, demo: true);
    }

    private function renderShow(Tournament $tournament, bool $demo): View
    {
        return view('tournaments.show', [
            'demo' => $demo,
            'tournament' => $tournament,
            'hands' => $tournament->hands()->orderBy('played_at')->orderBy('id')->get(),
        ]);
    }

    private function demoUser(): User
    {
        $demo = User::where('email', User::DEMO_EMAIL)->first();
        abort_if($demo === null, 404, 'Lancez « php artisan db:seed » pour créer la démo.');

        return $demo;
    }
}
