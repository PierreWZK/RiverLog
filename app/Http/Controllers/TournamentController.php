<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentStatsService;
use App\Support\TournamentFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Page Tournois (statistiques des tournois importés) et détail d'un tournoi.
 * Les mêmes pages servent la démo publique, en lecture seule.
 */
class TournamentController extends Controller
{
    public function __construct(private TournamentStatsService $statsService) {}

    public function index(Request $request): View
    {
        return $this->renderIndex($request, $request->user(), demo: false);
    }

    public function show(Tournament $tournament): View
    {
        Gate::authorize('view', $tournament);

        return $this->renderShow($tournament, demo: false);
    }

    public function demoIndex(Request $request): View
    {
        return $this->renderIndex($request, $this->demoUser(), demo: true);
    }

    public function demoShow(Tournament $tournament): View
    {
        abort_unless($tournament->user_id === $this->demoUser()->id, 404);

        return $this->renderShow($tournament, demo: true);
    }

    private function renderIndex(Request $request, User $owner, bool $demo): View
    {
        $filters = TournamentFilters::fromRequest($request);
        $tournaments = $filters->apply($owner->tournaments()->getQuery())
            ->orderByDesc('started_at')
            ->get();

        return view('tournaments.index', [
            'demo' => $demo,
            'filters' => $filters,
            'stats' => $this->statsService->compute($tournaments),
            'tournaments' => $tournaments,
            'hasTournaments' => $owner->tournaments()->exists(),
            // Valeurs proposées dans les filtres : celles réellement jouées
            'multipliers' => $owner->tournaments()->whereNotNull('multiplier')->distinct()->orderBy('multiplier')->pluck('multiplier'),
            'buyIns' => $owner->tournaments()->distinct()->orderBy('buy_in')->pluck('buy_in'),
        ]);
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
