<?php

namespace App\HandHistory\Data;

/**
 * Un joueur assis à la table pendant une main.
 */
final readonly class ParsedPlayer
{
    public function __construct(
        public int $seat,
        public string $name,
        public int $stack,             // jetons en début de main
        public ?string $position,      // BTN, SB, BB (en heads-up, le bouton est noté BTN)
        public bool $isHero,
        public ?string $cards,         // « Th 9h » si connues
        public ?string $shownRank,     // combinaison annoncée à l'abattage (« Straight »)
        public int $invested,          // jetons réellement engagés (mise non suivie déduite)
        public int $won,               // jetons gagnés (pots)
        public ?int $finishPosition,   // place finale si le joueur sort ou gagne dans cette main
        public ?string $prize,         // gain en euros (« 10.00 ») si annoncé
    ) {}

    public function net(): int
    {
        return $this->won - $this->invested;
    }
}
