<?php

namespace App\Http\Controllers;

use App\Enums\PokerFormat;
use App\Models\PokerSession;
use App\Models\User;
use App\Services\BankrollStatsService;
use App\Support\SessionFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard : cartes de stats, courbe cumulée et tableau des sessions.
 * Les trois utilisent la même requête filtrée, donc ils restent cohérents.
 */
class DashboardController extends Controller
{
    public function __construct(private BankrollStatsService $statsService) {}

    /** Le dashboard du compte connecté. */
    public function index(Request $request): View
    {
        return $this->render($request, $request->user(), demo: false);
    }

    /** Exemple public, en lecture seule : les sessions du compte de démonstration. */
    public function demo(Request $request): View
    {
        $demo = User::where('email', User::DEMO_EMAIL)->first();

        abort_if($demo === null, 404, 'Lancez « php artisan db:seed » pour créer la démo.');

        return $this->render($request, $demo, demo: true);
    }

    private function render(Request $request, User $owner, bool $demo): View
    {
        $filters = SessionFilters::fromRequest($request);
        // Uniquement les sessions de ce compte, puis les filtres de l'URL
        $query = $filters->apply(PokerSession::query()->whereBelongsTo($owner));

        return view('dashboard', [
            'demo' => $demo,
            'filters' => $filters,
            'stats' => $this->statsService->compute($query),
            // Tableau : les plus récentes en premier
            'sessions' => $query->clone()->orderByDesc('played_at')->orderByDesc('id')->get(),
            'hasSessions' => $owner->pokerSessions()->exists(),
            'formats' => PokerFormat::options(),
            'results' => SessionFilters::RESULTS,
        ]);
    }
}
