<?php

namespace App\HandHistory;

use App\Enums\PokerFormat;
use App\Models\PokerSession;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Regroupe les tournois importés en sessions (cahier des charges, section 8.6) :
 * une nouvelle session commence quand plus de 30 minutes séparent deux tournois.
 *
 * Les sessions importées sont entièrement recalculées à chaque import ; le dashboard
 * (profit, taux horaire, courbe, filtres) les prend en compte sans changement.
 */
class SessionBuilder
{
    public const GAP_MINUTES = 30;

    public function rebuild(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tournaments()->update(['poker_session_id' => null]);
            $user->pokerSessions()->where('source', PokerSession::SOURCE_IMPORT)->delete();

            // Un tournoi incomplet n'a pas encore de résultat : il rejoindra une session à l'import suivant
            $tournaments = $user->tournaments()->where('is_complete', true)->orderBy('started_at')->get();

            foreach ($this->groups($tournaments) as $group) {
                $this->createSession($user, $group);
            }
        });
    }

    /**
     * @param  Collection<int, Tournament>  $tournaments  triés par heure de début
     * @return list<Collection<int, Tournament>>
     */
    private function groups(Collection $tournaments): array
    {
        $groups = [];
        $current = null;
        $currentEnd = null;

        foreach ($tournaments as $tournament) {
            if ($current === null || $tournament->started_at->greaterThan($currentEnd->copy()->addMinutes(self::GAP_MINUTES))) {
                $groups[] = $current = collect();
                $currentEnd = $tournament->ended_at;
            }
            $current->push($tournament);
            // Tournois joués en parallèle : la session dure jusqu'à la fin du dernier
            $currentEnd = $tournament->ended_at->max($currentEnd);
        }

        return $groups;
    }

    /** @param  Collection<int, Tournament>  $group */
    private function createSession(User $user, Collection $group): void
    {
        $start = $group->first()->started_at;
        $end = $group->max('ended_at');
        $netCents = $group->sum(fn (Tournament $t) => TournamentAggregator::cents($t->net_result));

        // Format le plus fréquent du groupe
        $format = $group->countBy(fn (Tournament $t) => PokerFormat::fromGameMode($t->game_mode)->value)
            ->sortDesc()
            ->keys()
            ->first();

        $session = new PokerSession([
            'played_at' => $start,
            'format' => $format,
            'duration_minutes' => max(1, (int) ceil($start->diffInSeconds($end, true) / 60)),
            'net_result' => TournamentAggregator::euros($netCents),
            'notes' => $this->summary($group),
        ]);
        $session->user()->associate($user);
        $session->source = PokerSession::SOURCE_IMPORT;
        $session->save();

        Tournament::whereKey($group->pluck('id'))->update(['poker_session_id' => $session->id]);
    }

    /** « Import · 3 tournois : x2 ×2, x10 ×1 » */
    private function summary(Collection $group): string
    {
        $multipliers = $group->filter(fn (Tournament $t) => $t->multiplier)
            ->countBy(fn (Tournament $t) => $t->multiplier)
            ->sortKeys()
            ->map(fn (int $count, int $multiplier) => "x{$multiplier} ×{$count}")
            ->implode(', ');

        $count = $group->count();
        $label = "Import · {$count} tournoi".($count > 1 ? 's' : '');

        return $multipliers === '' ? $label : "{$label} : {$multipliers}";
    }
}
