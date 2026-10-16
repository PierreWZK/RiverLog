<?php

namespace App\Support;

/**
 * Statistiques d'un ensemble de tournois complets (cahier des charges, section 8.6).
 * Les montants sont en euros ; un ratio vaut null quand il n'a pas de sens (aucun tournoi…).
 */
final readonly class TournamentStats
{
    /** En dessous, les chiffres sont affichés avec un avertissement : la variance domine. */
    public const SMALL_SAMPLE = 30;

    /**
     * @param  list<array{label: string, value: string, count: int, share: float, wins: int, profit: float, roi: ?float}>  $byMultiplier
     * @param  list<array{label: string, value: string, count: int, share: float, wins: int, profit: float, roi: ?float}>  $byBuyIn
     * @param  array<int, int>  $positions  place => nombre de tournois
     */
    public function __construct(
        public int $count,
        public int $incomplete,
        public int $hands,
        public int $minutes,
        public float $buyIns,
        public float $winnings,
        public float $profit,
        public int $wins,
        public int $paid,
        public int $headsUpReached,
        public int $headsUpWon,
        public array $byMultiplier,
        public array $byBuyIn,
        public array $positions,
        public int $jackpotThreshold,
        public ?float $roiWithoutJackpots,
        public ?float $jackpotShare,
    ) {}

    public function roi(): ?float
    {
        return $this->buyIns > 0 ? round($this->profit / $this->buyIns * 100, 1) : null;
    }

    public function winRate(): ?float
    {
        return $this->count > 0 ? round($this->wins / $this->count * 100, 1) : null;
    }

    public function itm(): ?float
    {
        return $this->count > 0 ? round($this->paid / $this->count * 100, 1) : null;
    }

    public function averageProfit(): ?float
    {
        return $this->count > 0 ? round($this->profit / $this->count, 2) : null;
    }

    public function headsUpConversion(): ?float
    {
        return $this->headsUpReached > 0 ? round($this->headsUpWon / $this->headsUpReached * 100, 1) : null;
    }

    public function hourlyRate(): ?float
    {
        return $this->minutes > 0 ? round($this->profit / ($this->minutes / 60), 2) : null;
    }

    public function isSmallSample(): bool
    {
        return $this->count < self::SMALL_SAMPLE;
    }
}
