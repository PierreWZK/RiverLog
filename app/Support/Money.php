<?php

namespace App\Support;

/**
 * Affichage des montants à la française : « +1 342,50 € », « −20,00 € ».
 */
final class Money
{
    private const NBSP = "\u{00A0}";

    private const MINUS = "\u{2212}"; // vrai signe moins, plus lisible que le tiret

    public static function format(float|int|string $amount, bool $signed = true): string
    {
        $value = round((float) $amount, 2);
        $sign = match (true) {
            $value < 0 => self::MINUS,
            $signed && $value > 0 => '+',
            default => '',
        };

        return $sign.number_format(abs($value), 2, ',', self::NBSP).self::NBSP.'€';
    }

    /** Taux horaire : « +6,46 €/h », ou « — » sans temps de jeu. */
    public static function hourly(?float $rate): string
    {
        return $rate === null ? '—' : self::format($rate).'/h';
    }

    /** Pourcentage : « +180,0 % » (signé, pour un ROI) ou « 40,0 % », « — » si non calculable. */
    public static function percent(?float $value, bool $signed = false, int $decimals = 1): string
    {
        if ($value === null) {
            return '—';
        }

        $sign = match (true) {
            $value < 0 => self::MINUS,
            $signed && $value > 0 => '+',
            default => '',
        };

        return $sign.number_format(abs($value), $decimals, ',', self::NBSP).self::NBSP.'%';
    }

    /** Durée : 165 → « 2 h 45 », 60 → « 1 h », 45 → « 45 min ». */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours === 0 => $rest.self::NBSP.'min',
            $rest === 0 => $hours.self::NBSP.'h',
            default => $hours.self::NBSP.'h'.self::NBSP.sprintf('%02d', $rest),
        };
    }
}
