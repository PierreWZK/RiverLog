<?php

namespace App\Services;

use App\HandHistory\TournamentAggregator;
use App\Models\Tournament;
use App\Models\User;
use App\Support\TournamentFilters;
use App\Support\TournamentStats;
use Illuminate\Support\Collection;

/**
 * Calcule les statistiques d'un ensemble de tournois (déjà filtré).
 * Les calculs se font en centimes pour éviter toute erreur d'arrondi.
 */
class TournamentStatsService
{
    /** Multiplicateur à partir duquel un Spin compte comme « jackpot ». */
    public const JACKPOT_THRESHOLD = 10;

    /**
     * Données de la section « Parties importées » (page Import et démo) pour un compte.
     *
     * @return array<string, mixed>
     */
    public function overview(User $owner, TournamentFilters $filters): array
    {
        $tournaments = $filters->apply($owner->tournaments()->getQuery())
            ->orderByDesc('started_at')
            ->get();

        return [
            'filters' => $filters,
            'stats' => $this->compute($tournaments),
            'tournaments' => $tournaments,
            'hasTournaments' => $owner->tournaments()->exists(),
            // Valeurs proposées dans les filtres : celles réellement jouées
            'multipliers' => $owner->tournaments()->whereNotNull('multiplier')->distinct()->orderBy('multiplier')->pluck('multiplier'),
            'buyIns' => $owner->tournaments()->distinct()->orderBy('buy_in')->pluck('buy_in'),
        ];
    }

    /**
     * @param  Collection<int, Tournament>  $tournaments
     */
    public function compute(Collection $tournaments): TournamentStats
    {
        $complete = $tournaments->where('is_complete', true)->values();

        $buyIns = $this->sumCents($complete, 'buy_in');
        $winnings = $this->sumCents($complete, 'winnings');

        // ROI hors jackpots : montre à quel point le résultat dépend des gros multiplicateurs
        $small = $complete->filter(fn (Tournament $t) => ($t->multiplier ?? 0) < self::JACKPOT_THRESHOLD);
        $smallBuyIns = $this->sumCents($small, 'buy_in');
        $jackpotWinnings = $winnings - $this->sumCents($small, 'winnings');

        $headsUp = $complete->where('reached_heads_up', true);

        return new TournamentStats(
            count: $complete->count(),
            incomplete: $tournaments->count() - $complete->count(),
            hands: (int) $tournaments->sum('hands_count'),
            minutes: $this->playedMinutes($complete),
            buyIns: $buyIns / 100,
            winnings: $winnings / 100,
            profit: ($winnings - $buyIns) / 100,
            wins: $complete->where('finish_position', 1)->count(),
            paid: $complete->filter(fn (Tournament $t) => (float) $t->winnings > 0)->count(),
            headsUpReached: $headsUp->count(),
            headsUpWon: $headsUp->where('finish_position', 1)->count(),
            byMultiplier: $this->breakdown($complete, fn (Tournament $t) => $t->multiplier ?? 0, fn ($m) => $m ? "x{$m}" : '—'),
            byBuyIn: $this->breakdown($complete, fn (Tournament $t) => $t->buy_in, fn ($b) => str_replace('.', ',', $b).' €'),
            positions: $complete->whereNotNull('finish_position')->countBy('finish_position')->sortKeys()->all(),
            jackpotThreshold: self::JACKPOT_THRESHOLD,
            roiWithoutJackpots: $smallBuyIns > 0
                ? round(($this->sumCents($small, 'winnings') - $smallBuyIns) / $smallBuyIns * 100, 1)
                : null,
            jackpotShare: $winnings > 0 ? round($jackpotWinnings / $winnings * 100, 1) : null,
        );
    }

    /**
     * Résultats regroupés (par multiplicateur, par buy-in…), triés par valeur croissante.
     *
     * @param  Collection<int, Tournament>  $tournaments
     * @return list<array{label: string, value: string, count: int, share: float, wins: int, profit: float, roi: ?float}>
     */
    private function breakdown(Collection $tournaments, callable $key, callable $label): array
    {
        $total = max(1, $tournaments->count());

        return $tournaments
            ->groupBy($key)
            ->sortKeys(SORT_NUMERIC)
            ->map(function (Collection $group, $value) use ($total, $label) {
                $buyIns = $this->sumCents($group, 'buy_in');
                $profit = $this->sumCents($group, 'winnings') - $buyIns;

                return [
                    'label' => $label($value),
                    'value' => (string) $value,
                    'count' => $group->count(),
                    'share' => round($group->count() / $total * 100, 1),
                    'wins' => $group->where('finish_position', 1)->count(),
                    'profit' => $profit / 100.0,
                    'roi' => $buyIns > 0 ? round($profit / $buyIns * 100, 1) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Temps de jeu réel : union des intervalles des tournois (deux tournois en parallèle
     * ne comptent qu'une fois).
     *
     * @param  Collection<int, Tournament>  $tournaments
     */
    private function playedMinutes(Collection $tournaments): int
    {
        $seconds = 0;
        $start = null;
        $end = null;

        foreach ($tournaments->sortBy('started_at') as $tournament) {
            if ($end === null || $tournament->started_at->greaterThan($end)) {
                $seconds += $end ? $start->diffInSeconds($end, true) : 0;
                $start = $tournament->started_at;
                $end = $tournament->ended_at;
            } else {
                $end = $tournament->ended_at->max($end);
            }
        }
        $seconds += $end ? $start->diffInSeconds($end, true) : 0;

        return (int) round($seconds / 60);
    }

    /** @param  Collection<int, Tournament>  $tournaments */
    private function sumCents(Collection $tournaments, string $column): int
    {
        return (int) $tournaments->sum(fn (Tournament $t) => TournamentAggregator::cents($t->{$column}));
    }
}
