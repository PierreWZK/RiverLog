<?php

namespace App\Support;

/**
 * Résultat des calculs du dashboard pour un ensemble de sessions.
 */
final readonly class BankrollStats
{
    /**
     * @param  list<array{date: string, label: string, value: float}>  $curve  profit cumulé, session après session
     */
    public function __construct(
        public float $profit,
        public int $minutes,
        public int $count,
        public array $curve,
    ) {}

    public function hours(): float
    {
        return $this->minutes / 60;
    }

    /** Gain moyen par heure ; null s'il n'y a aucune minute jouée (affiché « — »). */
    public function hourlyRate(): ?float
    {
        return $this->minutes > 0 ? round($this->profit / $this->hours(), 2) : null;
    }
}
