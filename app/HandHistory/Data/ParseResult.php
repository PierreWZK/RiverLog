<?php

namespace App\HandHistory\Data;

/**
 * Résultat de la lecture d'un fichier : les mains lues et les erreurs rencontrées.
 */
final readonly class ParseResult
{
    /**
     * @param  list<ParsedHand>  $hands
     * @param  list<array{block: int, hand_id: ?string, message: string}>  $errors
     */
    public function __construct(
        public array $hands,
        public array $errors,
    ) {}
}
