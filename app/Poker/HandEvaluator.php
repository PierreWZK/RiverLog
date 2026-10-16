<?php

namespace App\Poker;

/**
 * Évalue la meilleure main de 5 cartes parmi 5 à 7 cartes (Texas Hold'em).
 *
 * Les cartes s'écrivent « As », « Td », « 9h »… Le score renvoyé est un entier :
 * plus il est grand, plus la main est forte (deux mains égales ont le même score).
 * Codage : catégorie sur 4 bits, puis jusqu'à 5 rangs départageants sur 4 bits chacun.
 */
final class HandEvaluator
{
    public const CATEGORIES = [
        0 => 'Hauteur',
        1 => 'Paire',
        2 => 'Double paire',
        3 => 'Brelan',
        4 => 'Quinte',
        5 => 'Couleur',
        6 => 'Full',
        7 => 'Carré',
        8 => 'Quinte flush',
    ];

    private const RANKS = ['2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, 'T' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14];

    private const SUITS = ['s' => 0, 'h' => 1, 'd' => 2, 'c' => 3];

    /**
     * @param  list<string>  $cards
     */
    public static function evaluate(array $cards): int
    {
        return self::evaluateCodes(array_map(self::code(...), $cards));
    }

    /** Nom de la combinaison : « Double paire ». */
    public static function categoryName(int $score): string
    {
        return self::CATEGORIES[$score >> 20];
    }

    /** « As » → 14 * 4 + 0 (rang * 4 + couleur), pour des calculs rapides. */
    public static function code(string $card): int
    {
        if (! isset(self::RANKS[$card[0] ?? ''], self::SUITS[$card[1] ?? ''])) {
            throw new \InvalidArgumentException("Carte invalide : « {$card} »");
        }

        return self::RANKS[$card[0]] * 4 + self::SUITS[$card[1]];
    }

    /** 56 → « As » */
    public static function card(int $code): string
    {
        return array_search(intdiv($code, 4), self::RANKS, true).array_search($code % 4, self::SUITS, true);
    }

    /** Les 52 cartes, codées. @return list<int> */
    public static function deck(): array
    {
        $deck = [];
        for ($rank = 2; $rank <= 14; $rank++) {
            for ($suit = 0; $suit < 4; $suit++) {
                $deck[] = $rank * 4 + $suit;
            }
        }

        return $deck;
    }

    /**
     * @param  list<int>  $codes  cartes codées (voir code())
     */
    public static function evaluateCodes(array $codes): int
    {
        $counts = array_fill(2, 13, 0);
        $bySuit = [[], [], [], []];
        $mask = 0;

        foreach ($codes as $code) {
            $rank = intdiv($code, 4);
            $counts[$rank]++;
            $bySuit[$code % 4][] = $rank;
            $mask |= 1 << $rank;
        }

        // Couleur et quinte flush
        foreach ($bySuit as $ranks) {
            if (count($ranks) >= 5) {
                $suitMask = 0;
                foreach ($ranks as $rank) {
                    $suitMask |= 1 << $rank;
                }
                if ($high = self::straightHigh($suitMask)) {
                    return self::score(8, [$high]);
                }
                rsort($ranks);

                return self::score(5, array_slice($ranks, 0, 5));
            }
        }

        // Groupes de cartes de même rang, du plus fort au plus faible
        $quads = $trips = $pairs = $singles = [];
        for ($rank = 14; $rank >= 2; $rank--) {
            match ($counts[$rank]) {
                4 => $quads[] = $rank,
                3 => $trips[] = $rank,
                2 => $pairs[] = $rank,
                1 => $singles[] = $rank,
                default => null,
            };
        }

        if ($quads) {
            $kicker = max([...array_slice($quads, 1), ...$trips, ...$pairs, ...$singles, 0]);

            return self::score(7, [$quads[0], $kicker]);
        }

        if ($trips && (count($trips) > 1 || $pairs)) {
            return self::score(6, [$trips[0], max($trips[1] ?? 0, $pairs[0] ?? 0)]);
        }

        if ($high = self::straightHigh($mask)) {
            return self::score(4, [$high]);
        }

        if ($trips) {
            return self::score(3, [$trips[0], ...array_slice($singles, 0, 2)]);
        }

        if (count($pairs) >= 2) {
            $kicker = max($pairs[2] ?? 0, $singles[0] ?? 0);

            return self::score(2, [$pairs[0], $pairs[1], $kicker]);
        }

        if ($pairs) {
            return self::score(1, [$pairs[0], ...array_slice($singles, 0, 3)]);
        }

        return self::score(0, array_slice($singles, 0, 5));
    }

    /** Carte haute de la meilleure quinte contenue dans le masque de rangs (0 si aucune). */
    private static function straightHigh(int $mask): int
    {
        if ($mask & (1 << 14)) {
            $mask |= 1 << 1; // l'As compte aussi comme 1 (quinte A-2-3-4-5)
        }
        for ($high = 14; $high >= 5; $high--) {
            if ((($mask >> ($high - 4)) & 0b11111) === 0b11111) {
                return $high;
            }
        }

        return 0;
    }

    /** @param  list<int>  $ranks */
    private static function score(int $category, array $ranks): int
    {
        $score = $category;
        for ($i = 0; $i < 5; $i++) {
            $score = ($score << 4) | ($ranks[$i] ?? 0);
        }

        return $score;
    }
}
