<?php

namespace App\Poker;

use Random\Randomizer;

/**
 * Équité de deux mains à tapis : part du pot gagnée en moyenne (égalité = moitié du pot).
 *
 * Exact quand il reste 1 ou 2 cartes à tirer (turn ou flop) ; préflop, il y aurait
 * 1,7 million de boards à tester : on tire un échantillon (Monte-Carlo) avec un générateur
 * fourni, donc reproductible.
 */
final class EquityCalculator
{
    public const PREFLOP_SAMPLES = 30000;

    /**
     * @param  list<string>  $handA  « As Kd »
     * @param  list<string>  $handB
     * @param  list<string>  $board  0, 3 ou 4 cartes
     * @return array{a: float, b: float, tie: float, exact: bool}  pourcentages
     */
    public function equity(array $handA, array $handB, array $board, ?Randomizer $random = null): array
    {
        $a = array_map(HandEvaluator::code(...), $handA);
        $b = array_map(HandEvaluator::code(...), $handB);
        $known = array_map(HandEvaluator::code(...), $board);

        $used = [...$a, ...$b, ...$known];
        if (count(array_unique($used)) !== count($used)) {
            throw new \InvalidArgumentException('Une carte apparaît deux fois.');
        }
        $deck = array_values(array_diff(HandEvaluator::deck(), $used));
        $missing = 5 - count($known);

        $wins = $ties = $total = 0;
        $tally = function (array $runout) use ($a, $b, $known, &$wins, &$ties, &$total) {
            $board = [...$known, ...$runout];
            $scoreA = HandEvaluator::evaluateCodes([...$a, ...$board]);
            $scoreB = HandEvaluator::evaluateCodes([...$b, ...$board]);
            $total++;
            if ($scoreA > $scoreB) {
                $wins++;
            } elseif ($scoreA === $scoreB) {
                $ties++;
            }
        };

        $exact = $missing <= 2;
        if ($missing === 0) {
            $tally([]);
        } elseif ($missing === 1) {
            foreach ($deck as $card) {
                $tally([$card]);
            }
        } elseif ($missing === 2) {
            $n = count($deck);
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $tally([$deck[$i], $deck[$j]]);
                }
            }
        } else {
            $random ??= new Randomizer;
            for ($s = 0; $s < self::PREFLOP_SAMPLES; $s++) {
                $keys = $random->pickArrayKeys($deck, $missing);
                $tally(array_map(fn ($k) => $deck[$k], $keys));
            }
        }

        $losses = $total - $wins - $ties;

        return [
            'a' => round(($wins + $ties / 2) / $total * 100, 1),
            'b' => round(($losses + $ties / 2) / $total * 100, 1),
            'tie' => round($ties / $total * 100, 1),
            'exact' => $exact,
        ];
    }
}
