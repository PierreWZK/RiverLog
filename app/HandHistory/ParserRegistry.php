<?php

namespace App\HandHistory;

use App\HandHistory\Contracts\HandHistoryParser;
use App\HandHistory\Parsers\BetclicParser;

/**
 * Choisit le bon parser selon le contenu du fichier.
 * Ajouter un site = ajouter son parser dans la liste.
 */
class ParserRegistry
{
    /** @var list<HandHistoryParser> */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [new BetclicParser];
    }

    public function for(string $content): ?HandHistoryParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($content)) {
                return $parser;
            }
        }

        return null;
    }
}
