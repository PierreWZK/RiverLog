<?php

namespace App\HandHistory\Data;

use Carbon\CarbonImmutable;

/**
 * Une main lue dans un historique, avec les informations du tournoi répétées dans l'en-tête.
 * Les heures sont déjà converties dans le fuseau de l'application (Europe/Paris).
 */
final readonly class ParsedHand
{
    /**
     * @param  list<ParsedPlayer>  $players
     * @param  list<ParsedAction>  $actions
     */
    public function __construct(
        // Tournoi
        public string $site,
        public string $gameId,
        public string $gameName,
        public string $gameMode,
        public string $buyIn,          // « 1.00 »
        public ?string $prizePool,     // « 10.00 »
        public ?int $multiplier,       // 10 pour « x10 »
        public string $currency,
        // Main
        public string $handId,
        public ?string $tableId,
        public CarbonImmutable $playedAt,
        public CarbonImmutable $endedAt,
        public int $smallBlind,
        public int $bigBlind,
        public int $totalPot,
        public ?string $board,         // « Jh 8c Qh 6c Ks »
        public bool $wentToShowdown,
        public array $players,
        public array $actions,
        public string $rawText,
    ) {}

    public function hero(): ParsedPlayer
    {
        foreach ($this->players as $player) {
            if ($player->isHero) {
                return $player;
            }
        }

        throw new \LogicException('Main sans Hero.'); // impossible : vérifié par le parser
    }

    public function heroAllIn(): bool
    {
        $hero = $this->hero()->name;

        foreach ($this->actions as $action) {
            if ($action->player === $hero && $action->isAllIn) {
                return true;
            }
        }

        return false;
    }
}
