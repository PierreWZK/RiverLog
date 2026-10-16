<?php

namespace App\HandHistory\Data;

use Carbon\CarbonImmutable;

/**
 * Une action d'une main, normalisée.
 *
 * `amount` est le montant écrit dans le fichier (total pour « Raises to », ajout sinon) ;
 * `added` est toujours ce que le joueur ajoute réellement au pot avec cette action.
 */
final readonly class ParsedAction
{
    public const STREETS = ['preflop', 'flop', 'turn', 'river'];

    public function __construct(
        public int $sequence,
        public string $street,          // preflop, flop, turn, river
        public string $player,
        public string $type,            // post_sb, post_bb, fold, check, call, bet, raise
        public ?int $amount,
        public int $added,
        public int $streetTotal,        // total misé par le joueur sur la street après l'action
        public bool $isAllIn,
        public CarbonImmutable $actedAt,
        public ?int $decisionSeconds,   // temps écoulé depuis l'action précédente de la main
    ) {}

    /** Mise volontaire (les blindes ne comptent pas). */
    public function isVoluntary(): bool
    {
        return in_array($this->type, ['call', 'bet', 'raise'], true);
    }
}
