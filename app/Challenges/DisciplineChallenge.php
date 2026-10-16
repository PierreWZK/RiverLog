<?php

namespace App\Challenges;

use App\Models\PokerSession;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Défi discipline : un objectif de gestion de bankroll par jour, vérifié sur les
 * sessions et tournois réels du compte. Rien n'est stocké : tout se recalcule.
 *
 * Statuts d'une journée :
 *  - success     objectif tenu
 *  - failed      objectif manqué
 *  - pending     aujourd'hui, pas encore joué ou respecté pour l'instant
 *  - not_played  journée passée sans jeu : neutre, ne casse pas la série
 */
class DisciplineChallenge
{
    public const MAX_MINUTES = 120;

    public const MAX_VOLUME = 15;

    public const MIN_BREAK_MINUTES = 15;

    public const LOSING_STREAK_STOP = 2;

    private const HISTORY_DAYS = 120;

    public const OBJECTIVES = [
        'max_duration' => ['title' => 'Session maîtrisée', 'rule' => 'Pas plus de 2 h de jeu aujourd\'hui.', 'icon' => '⏱'],
        'notes' => ['title' => 'Bilan écrit', 'rule' => 'Ajoutez une note à au moins une session saisie aujourd\'hui.', 'icon' => '✎'],
        'break' => ['title' => 'Pause obligatoire', 'rule' => 'Au moins 15 min de pause entre deux sessions.', 'icon' => '☕'],
        'stop_loss' => ['title' => 'Stop-loss', 'rule' => 'Arrêtez-vous après 2 sessions perdantes d\'affilée.', 'icon' => '✋'],
        'max_volume' => ['title' => 'Volume maîtrisé', 'rule' => 'Pas plus de 15 tournois ou sessions aujourd\'hui.', 'icon' => '♣'],
        'rest_day' => ['title' => 'Jour de repos', 'rule' => 'Aucune session aujourd\'hui : savoir ne pas jouer fait aussi partie de la discipline.', 'icon' => '☾'],
    ];

    /** Cycle de 7 jours : un jour de repos par semaine. */
    private const CYCLE = ['max_duration', 'notes', 'break', 'stop_loss', 'max_volume', 'max_duration', 'rest_day'];

    /** @return array{key: string, title: string, rule: string, icon: string} */
    public function objectiveFor(CarbonImmutable $day): array
    {
        $index = intdiv($day->startOfDay()->timestamp + $day->utcOffset() * 60, 86400) % count(self::CYCLE);
        $key = self::CYCLE[$index];

        return ['key' => $key] + self::OBJECTIVES[$key];
    }

    /**
     * Objectif du jour, son statut, la série et l'historique récent.
     *
     * @return array{today: array, status: string, progress: string, streak: int, history: list<array>}
     */
    public function summary(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $from = $today->subDays(self::HISTORY_DAYS);

        $sessions = $user->pokerSessions()
            ->where('played_at', '>=', $from)
            ->where('played_at', '<', $today->addDay())
            ->orderBy('played_at')
            ->get()
            ->groupBy(fn (PokerSession $s) => $s->played_at->toDateString());

        $tournaments = $user->tournaments()
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $today->addDay())
            ->get(['started_at'])
            ->countBy(fn ($t) => $t->started_at->toDateString());

        $playedOn = fn (CarbonImmutable $day) => $sessions->has($day->toDateString()) || $tournaments->has($day->toDateString());
        $accountStart = CarbonImmutable::parse($user->created_at)->setTimezone(config('app.timezone'))->startOfDay();

        $evaluate = function (CarbonImmutable $day) use ($today, $sessions, $tournaments, $playedOn, $accountStart) {
            // Avant la création du compte, il n'y a rien à juger
            if ($day->lessThan($accountStart)) {
                return ['status' => 'not_played', 'progress' => 'Compte pas encore créé.'];
            }

            // Un jour de repos ne compte que pour un joueur actif : au moins une session les 6 jours d'avant
            $activeWeek = collect(range(1, 6))->contains(fn (int $i) => $playedOn($day->subDays($i)));

            return $this->evaluate(
                $this->objectiveFor($day)['key'],
                $sessions->get($day->toDateString(), collect()),
                $tournaments->get($day->toDateString(), 0),
                isToday: $day->equalTo($today),
                activeWeek: $activeWeek,
            );
        };

        // Série : jours réussis consécutifs ; un jour sans jeu est neutre, un échec l'arrête
        $streak = 0;
        for ($day = $today->subDay(); $day->greaterThanOrEqualTo($from); $day = $day->subDay()) {
            $status = $evaluate($day)['status'];
            if ($status === 'failed') {
                break;
            }
            $streak += $status === 'success' ? 1 : 0;
        }

        $current = $evaluate($today);
        if ($current['status'] === 'success') {
            $streak++;
        }

        $history = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = $today->subDays($i);
            $history[] = ['day' => $day, 'objective' => $this->objectiveFor($day)] + $evaluate($day);
        }

        return [
            'today' => $this->objectiveFor($today),
            'status' => $current['status'],
            'progress' => $current['progress'],
            'streak' => $streak,
            'history' => $history,
        ];
    }

    /**
     * @param  Collection<int, PokerSession>  $sessions  sessions du jour, triées par heure
     * @return array{status: string, progress: string}
     */
    public function evaluate(string $objective, Collection $sessions, int $tournaments, bool $isToday, bool $activeWeek = true): array
    {
        $played = $sessions->isNotEmpty();
        $notYet = ['status' => $isToday ? 'pending' : 'not_played', 'progress' => $isToday ? 'Pas encore de session aujourd\'hui.' : 'Pas de jeu ce jour-là.'];
        // Contrainte respectée : réussie si la journée est finie, « en bonne voie » sinon
        $kept = fn (string $progress) => ['status' => $isToday ? 'pending' : 'success', 'progress' => $progress];
        $failed = fn (string $progress) => ['status' => 'failed', 'progress' => $progress];

        switch ($objective) {
            case 'max_duration':
                if (! $played) {
                    return $notYet;
                }
                $minutes = (int) $sessions->sum('duration_minutes');
                $text = Money::duration($minutes).' joué'.' / 2 h maximum';

                return $minutes > self::MAX_MINUTES ? $failed($text) : $kept($text);

            case 'notes':
                $manual = $sessions->reject(fn (PokerSession $s) => $s->isImported());
                if ($manual->isEmpty()) {
                    return $notYet;
                }
                if ($manual->contains(fn (PokerSession $s) => trim((string) $s->notes) !== '')) {
                    return ['status' => 'success', 'progress' => 'Note ajoutée.'];
                }

                return $isToday
                    ? ['status' => 'pending', 'progress' => 'Aucune note pour l\'instant : modifiez une session pour en ajouter une.']
                    : $failed('Aucune note ce jour-là.');

            case 'break':
                if (! $played) {
                    return $notYet;
                }
                $shortest = null;
                foreach ($sessions->values() as $i => $session) {
                    if ($i === 0) {
                        continue;
                    }
                    $previous = $sessions->values()[$i - 1];
                    $gap = (int) floor($previous->played_at->copy()->addMinutes($previous->duration_minutes)->diffInMinutes($session->played_at, false));
                    $shortest = $shortest === null ? $gap : min($shortest, $gap);
                }
                if ($shortest === null) {
                    return $kept('Une seule session : pas de pause à respecter.');
                }
                $text = 'Pause la plus courte : '.Money::duration(max(0, $shortest));

                return $shortest < self::MIN_BREAK_MINUTES ? $failed($text) : $kept($text);

            case 'stop_loss':
                if (! $played) {
                    return $notYet;
                }
                $losses = 0;
                foreach ($sessions as $session) {
                    if ($losses >= self::LOSING_STREAK_STOP) {
                        return $failed('Session jouée après '.self::LOSING_STREAK_STOP.' pertes d\'affilée.');
                    }
                    $losses = (float) $session->net_result < 0 ? $losses + 1 : 0;
                }
                $text = $losses >= self::LOSING_STREAK_STOP
                    ? 'Stop atteint : '.self::LOSING_STREAK_STOP.' sessions perdantes, on s\'arrête là.'
                    : "Sessions perdantes d'affilée : {$losses}";

                return $kept($text);

            case 'max_volume':
                $volume = $sessions->reject(fn (PokerSession $s) => $s->isImported())->count() + $tournaments;
                if ($volume === 0) {
                    return $notYet;
                }
                $text = "{$volume} tournoi".($volume > 1 ? 's' : '').' ou session'.($volume > 1 ? 's' : '').' / '.self::MAX_VOLUME.' maximum';

                return $volume > self::MAX_VOLUME ? $failed($text) : $kept($text);

            case 'rest_day':
                if ($played || $tournaments > 0) {
                    return $failed('Une session a été jouée.');
                }
                // Sans jeu les jours précédents, ne pas jouer n'est pas un effort : journée neutre
                if (! $activeWeek && ! $isToday) {
                    return ['status' => 'not_played', 'progress' => 'Pas de jeu cette semaine-là.'];
                }

                return $kept($isToday ? 'Aucune session jusqu\'ici.' : 'Journée de repos respectée.');
        }

        throw new \InvalidArgumentException("Objectif inconnu : {$objective}");
    }
}
