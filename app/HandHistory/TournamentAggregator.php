<?php

namespace App\HandHistory;

use App\Models\Tournament;

/**
 * Recalcule les chiffres d'un tournoi à partir de TOUTES ses mains en base
 * (un tournoi peut être réparti sur plusieurs exports).
 */
class TournamentAggregator
{
    public function refresh(Tournament $tournament): void
    {
        $hands = $tournament->hands()
            ->orderBy('played_at')
            ->orderBy('id')
            ->get(['played_at', 'ended_at', 'players_count', 'hero_finish_position', 'hero_prize']);

        if ($hands->isEmpty()) {
            $tournament->delete(); // ses mains ont été supprimées avec leur import

            return;
        }

        // La main où le Hero sort ou gagne donne sa place finale ; sans elle, le tournoi est incomplet
        $final = $hands->first(fn ($hand) => $hand->hero_finish_position !== null);
        $winnings = $final?->hero_prize ?? '0.00';

        $tournament->update([
            'started_at' => $hands->first()->played_at,
            'ended_at' => $hands->max('ended_at'),
            'hands_count' => $hands->count(),
            'players_count' => $hands->max('players_count'),
            'finish_position' => $final?->hero_finish_position,
            'winnings' => $winnings,
            'net_result' => $final ? self::euros(self::cents($winnings) - self::cents($tournament->buy_in)) : null,
            'is_complete' => $final !== null,
            'reached_heads_up' => $hands->contains(fn ($hand) => (int) $hand->players_count === 2),
        ]);
    }

    /** Calculs en centimes, sans erreur d'arrondi. */
    public static function cents(string|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** 900 → « 9.00 » */
    public static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
