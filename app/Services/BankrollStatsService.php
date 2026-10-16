<?php

namespace App\Services;

use App\Models\PokerSession;
use App\Support\BankrollStats;
use Illuminate\Database\Eloquent\Builder;

/**
 * Calcule les statistiques d'un ensemble de sessions (déjà filtré).
 */
class BankrollStatsService
{
    /**
     * @param  Builder<PokerSession>  $query
     */
    public function compute(Builder $query): BankrollStats
    {
        // Totaux calculés en SQL. Les montants sont additionnés en centimes
        // pour éviter les erreurs d'arrondi des nombres à virgule.
        $totals = $query->clone()
            ->toBase()
            ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(ROUND(net_result * 100)), 0) as cents, COALESCE(SUM(duration_minutes), 0) as minutes')
            ->first();

        return new BankrollStats(
            profit: (int) $totals->cents / 100,
            minutes: (int) $totals->minutes,
            count: (int) $totals->sessions,
            curve: $this->curve($query),
        );
    }

    /**
     * Profit cumulé dans l'ordre chronologique (deux sessions le même soir restent dans l'ordre).
     *
     * @param  Builder<PokerSession>  $query
     * @return list<array{date: string, label: string, value: float}>
     */
    private function curve(Builder $query): array
    {
        $cents = 0;
        $points = [];

        $sessions = $query->clone()
            ->reorder()
            ->orderBy('played_at')
            ->orderBy('id')
            ->get(['id', 'played_at', 'net_result']);

        foreach ($sessions as $session) {
            $cents += (int) round((float) $session->net_result * 100);
            $points[] = [
                'date' => $session->played_at->toDateTimeString(),
                'label' => $session->played_at->format('d/m/Y'),
                'value' => $cents / 100,
            ];
        }

        return $points;
    }
}
