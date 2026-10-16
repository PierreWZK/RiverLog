<?php

namespace Database\Seeders\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Génère un historique Betclic fictif (Spin & Rush à 3 joueurs) pour le compte de démonstration.
 *
 * Le texte produit passe par le vrai import : il respecte donc les mêmes règles que les
 * exports réels (sommes des mises = pot, mise non suivie rendue, places finales…).
 * Les mains restent simples : vol de blindes, walk, mise au flop ou tapis préflop.
 * L'abattage n'annonce pas de combinaison : le gagnant est tiré au sort (données fictives).
 */
class SpinHistoryGenerator
{
    private const LEVELS = [[10, 20], [15, 30], [20, 40], [30, 60], [40, 80], [50, 100], [75, 150], [100, 200], [150, 300], [200, 400]];

    private const HANDS_PER_LEVEL = 3;

    /** Multiplicateurs fictifs (pour 1000 tournois) : la moyenne (≈ x2,66) inclut des frais. */
    private const MULTIPLIERS = [2 => 780, 3 => 140, 5 => 60, 10 => 18, 100 => 2];

    private const OPPONENTS = ['Bluffeur', 'Zoltan88', 'Valmy', 'Néné9', 'Mascara', 'BX55', 'Pagnol13', 'Ouistiti', 'Requin591', 'ColinNeuf', 'RiverRat', 'Tilteur', 'Nitro', 'AceFace', 'Calimero'];

    private const STARTING_STACK = 500;

    public function __construct(
        private string $heroName = 'Vous',
        private int $heroAllInWinPercent = 62, // un léger avantage : le compte de démo est un joueur gagnant
    ) {}

    /**
     * Un tournoi complet, du point de vue du Hero, mains les plus récentes en premier.
     *
     * @return array{text: string, ended_at: CarbonImmutable}
     */
    public function tournament(CarbonImmutable $startUtc, string $buyIn = '1.00'): array
    {
        $multiplier = $this->weighted(self::MULTIPLIERS);
        $game = [
            'id' => (string) Str::ulid(),
            'table' => (string) Str::ulid(),
            'multiplier' => $multiplier,
            'prize' => number_format((float) $buyIn * $multiplier, 2, '.', ''),
            'buy_in' => $buyIn,
        ];

        $names = fake()->randomElements(self::OPPONENTS, 2);
        array_splice($names, fake()->numberBetween(0, 2), 0, [$this->heroName]);
        $players = [];
        foreach ($names as $i => $name) {
            $players[$name] = ['seat' => $i + 1, 'stack' => self::STARTING_STACK];
        }

        $hands = [];
        $time = $startUtc;
        $button = fake()->numberBetween(0, 2);

        for ($handNumber = 0; $handNumber < 200; $handNumber++) {
            $level = self::LEVELS[min(intdiv($handNumber, self::HANDS_PER_LEVEL), count(self::LEVELS) - 1)];
            [$text, $time, $finished] = $this->hand($game, $players, $button, $level, $time);
            $hands[] = $text;
            $button++;

            if ($finished) {
                break; // le Hero est éliminé, ou il ne reste qu'un joueur
            }
            $time = $time->addSeconds(fake()->numberBetween(3, 6));
        }

        return ['text' => implode("\n\n------------\n\n", array_reverse($hands)), 'ended_at' => $time];
    }

    /**
     * @param  array<string, array{seat: int, stack: int}>  $players  modifié : tapis après la main
     * @param  array{0: int, 1: int}  $level
     * @return array{0: string, 1: CarbonImmutable, 2: bool}
     */
    private function hand(array $game, array &$players, int $button, array $level, CarbonImmutable $start): array
    {
        [$sb, $bb] = $level;
        $active = array_keys(array_filter($players, fn ($p) => $p['stack'] > 0));
        $count = count($active);
        $btn = $active[$button % $count];

        // Ordre d'action préflop et positions
        if ($count === 3) {
            $i = array_search($btn, $active, true);
            $order = [$active[$i], $active[($i + 1) % 3], $active[($i + 2) % 3]]; // BTN, SB, BB
            $positions = [$order[0] => 'BTN', $order[1] => 'SB', $order[2] => 'BB'];
            [$sbPlayer, $bbPlayer] = [$order[1], $order[2]];
        } else {
            $other = $active[0] === $btn ? $active[1] : $active[0];
            $order = [$btn, $other];
            $positions = [$btn => 'BTN SB', $other => 'BB'];
            [$sbPlayer, $bbPlayer] = [$btn, $other];
        }

        $stacks = [];
        foreach ($active as $name) {
            $stacks[$name] = $players[$name]['stack'];
        }
        $total = array_fill_keys($active, 0);       // misé sur toute la main
        $streetBets = array_fill_keys($active, 0);  // misé sur la street en cours
        $folded = [];
        $lines = ['PRE-FLOP' => [], 'FLOP' => []];
        $time = $start;

        $act = function (string $street, string $name, string $verb, ?int $amount = null) use (&$lines, &$time, &$total, &$streetBets, $stacks, &$folded) {
            $time = $time->addSeconds(str_starts_with($verb, 'Posts') ? 0 : fake()->numberBetween(1, 8));
            $added = match ($verb) {
                'Raises to' => $amount - $streetBets[$name],
                'Folds', 'Checks' => 0,
                default => $amount,
            };
            $streetBets[$name] += $added;
            $total[$name] += $added;
            if ($verb === 'Folds') {
                $folded[$name] = true;
            }
            $allIn = $added > 0 && $total[$name] === $stacks[$name] ? ' and is all-in' : '';
            $lines[$street][] = $time->format('H:i:s')." - {$name}: {$verb}".($amount !== null ? " {$amount}" : '').$allIn;
        };
        // Fonction classique (pas fléchée) : $total est lu par référence, à jour à chaque appel
        $remaining = function (string $name) use ($stacks, &$total): int {
            return $stacks[$name] - $total[$name];
        };

        $act('PRE-FLOP', $sbPlayer, 'Posts SB', min($sb, $stacks[$sbPlayer]));
        $act('PRE-FLOP', $bbPlayer, 'Posts BB', min($bb, $stacks[$bbPlayer]));

        $first = $order[0];
        $blindAllIn = collect($active)->first(fn ($name) => $remaining($name) === 0);
        $short = collect($active)->first(fn ($name) => $stacks[$name] <= 4 * $bb);
        $roll = fake()->numberBetween(1, 100);

        $scenario = match (true) {
            $blindAllIn !== null, $short !== null, $roll <= 25 => 'allin',
            $roll <= 40 => 'walk',
            $roll <= 70 => 'steal',
            default => 'flop',
        };

        $deck = fake()->shuffleArray($this->deck());
        $cards = [];
        foreach ($active as $name) {
            $cards[$name] = array_shift($deck).' '.array_shift($deck);
        }
        $board = null;
        $showdown = [];

        if ($scenario === 'walk') {
            foreach ($order as $name) {
                if ($name !== $bbPlayer) {
                    $act('PRE-FLOP', $name, 'Folds');
                }
            }
            $winner = $bbPlayer;
        } elseif ($scenario === 'steal' || $scenario === 'flop') {
            $act('PRE-FLOP', $first, 'Raises to', 2 * $bb);
            $caller = null;
            foreach (array_slice($order, 1) as $name) {
                if ($scenario === 'flop' && $caller === null) {
                    $act('PRE-FLOP', $name, 'Calls', $total[$first] - $total[$name]);
                    $caller = $name;
                } else {
                    $act('PRE-FLOP', $name, 'Folds');
                }
            }
            $winner = $first;

            if ($caller !== null) {
                // Flop : un joueur mise environ la moitié du pot, l'autre se couche
                $board = implode(' ', array_splice($deck, 0, 3));
                $bettor = fake()->boolean() ? $caller : $first;
                $other = $bettor === $first ? $caller : $first;
                $bet = min(max($bb, intdiv(array_sum($total), 2)), $remaining($bettor));
                $streetBets = array_fill_keys($active, 0);
                $act('FLOP', $bettor, 'Bets', $bet);
                $act('FLOP', $other, 'Folds');
                $winner = $bettor;
            }
        } else {
            // Tapis préflop : un joueur pousse, un autre paie, abattage
            $shover = $blindAllIn ?? $short ?? $first;
            $others = array_values(array_filter($order, fn ($name) => $name !== $shover));
            $maxOther = max(array_map(fn ($name) => $total[$name], $others));

            if ($blindAllIn !== null || $stacks[$shover] <= $maxOther) {
                // Tapis plus court que la blinde : paie celui qui a déjà le plus misé (pas de pot secondaire)
                $caller = collect($others)->sortByDesc(fn ($name) => $total[$name])->first();
            } else {
                $eligible = array_values(array_filter($others, fn ($name) => $stacks[$name] >= $maxOther));
                $caller = in_array($this->heroName, $eligible, true) && fake()->boolean(60)
                    ? $this->heroName
                    : fake()->randomElement($eligible ?: $others);
            }

            foreach ($order as $name) {
                if ($name === $shover && $remaining($name) > 0) {
                    $stacks[$name] > max($total)
                        ? $act('PRE-FLOP', $name, 'Raises to', $stacks[$name])
                        : $act('PRE-FLOP', $name, 'Calls', $remaining($name));
                } elseif ($name !== $shover && $name !== $caller) {
                    $act('PRE-FLOP', $name, 'Folds');
                }
            }
            // Le joueur qui paie agit après le tapis (s'il doit encore compléter)
            $target = min($stacks[$caller], max($total));
            if ($total[$caller] < $target) {
                $act('PRE-FLOP', $caller, 'Calls', $target - $total[$caller]);
            }

            $board = implode(' ', array_splice($deck, 0, 5));
            $showdown = [$shover, $caller];
            if (in_array($this->heroName, $showdown, true)) {
                $villain = $shover === $this->heroName ? $caller : $shover;
                $winner = fake()->boolean($this->heroAllInWinPercent) ? $this->heroName : $villain;
            } else {
                $winner = fake()->randomElement($showdown);
            }
        }

        // Mise non suivie rendue quand au moins deux joueurs restent (même règle que Betclic)
        if (count($active) - count($folded) >= 2) {
            $sorted = $total;
            arsort($sorted);
            [$top, $second] = array_keys($sorted);
            $total[$top] -= $total[$top] - $total[$second];
        }
        $pot = array_sum($total);

        foreach ($active as $name) {
            $players[$name]['stack'] += ($name === $winner ? $pot : 0) - $total[$name];
        }

        // Places finales
        $summary = ["{$winner} wins main pot of {$pot}"];
        $left = array_keys(array_filter($players, fn ($p) => $p['stack'] > 0));
        $heroOut = false;
        foreach ($active as $name) {
            if ($players[$name]['stack'] === 0) {
                $summary[] = "{$name} finished ".($count === 3 ? '3rd' : '2nd');
                $heroOut = $heroOut || $name === $this->heroName;
            }
        }
        if (count($left) === 1) {
            $summary[] = "{$left[0]} finished 1st and wins {$game['prize']} EUR";
        }

        // ---------- Texte au format Betclic ----------
        $text = [
            '*** HEADER ***',
            'Site: Betclic.fr',
            'Game Mode: Spin',
            "Game Type: NL Texas Hold'em",
            'Game Name: Spin & Rush '.rtrim(rtrim($game['buy_in'], '0'), '.').'€',
            "Game ID: {$game['id']}",
            "Prize pool: {$game['prize']}€",
            "Multiplier: x{$game['multiplier']}",
            "Buy In: {$game['buy_in']}€",
            'Hand ID: '.Str::ulid(),
            'Date & Time: '.$start->format('Y-m-d H:i:s').' (UTC)',
            "Table ID: {$game['table']}",
            "Blinds: {$sb}/{$bb}",
            "Total Pot: {$pot}",
            'Rake: 0',
            '*** PLAYERS ***',
        ];
        foreach ($active as $name) {
            $tags = $positions[$name].($name === $this->heroName ? ' Hero' : '');
            $text[] = "Seat {$players[$name]['seat']}: {$name} ({$stacks[$name]}) [{$tags}]";
        }
        $text[] = '*** HOLE CARDS ***';
        foreach ($active as $name) {
            if ($name === $this->heroName || in_array($name, $showdown, true)) {
                $text[] = "{$name}: [{$cards[$name]}]";
            }
        }
        $text[] = '*** PRE-FLOP ***';
        array_push($text, ...$lines['PRE-FLOP']);
        if ($board !== null) {
            $boardCards = explode(' ', $board);
            $text[] = '*** FLOP *** ['.implode(' ', array_slice($boardCards, 0, 3)).']';
            array_push($text, ...$lines['FLOP']);
            if (count($boardCards) === 5) {
                $text[] = '*** TURN *** ['.implode(' ', array_slice($boardCards, 0, 4)).']';
                $text[] = '*** RIVER *** ['.$board.']';
            }
        }
        if ($showdown !== []) {
            $text[] = '*** SHOWDOWN ***';
            foreach ($showdown as $name) {
                $text[] = "{$name} shows [{$cards[$name]}]";
            }
        }
        $text[] = '*** SUMMARY ***';
        array_push($text, ...$summary);

        return [implode("\n", $text), $time, $heroOut || count($left) === 1];
    }

    /** @return list<string> */
    private function deck(): array
    {
        $deck = [];
        foreach (str_split('23456789TJQKA') as $rank) {
            foreach (['s', 'h', 'd', 'c'] as $suit) {
                $deck[] = $rank.$suit;
            }
        }

        return $deck;
    }

    /** @param  array<int, int>  $weights */
    private function weighted(array $weights): int
    {
        $roll = fake()->numberBetween(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
