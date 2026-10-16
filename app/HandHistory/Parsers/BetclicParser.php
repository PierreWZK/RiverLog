<?php

namespace App\HandHistory\Parsers;

use App\HandHistory\Contracts\HandHistoryParser;
use App\HandHistory\Data\ParsedAction;
use App\HandHistory\Data\ParsedHand;
use App\HandHistory\Data\ParsedPlayer;
use App\HandHistory\Data\ParseResult;
use App\HandHistory\ParseException;
use Carbon\CarbonImmutable;

/**
 * Lecteur des exports d'historiques Betclic (« ExportHH »).
 *
 * Format (voir CAHIER_DES_CHARGES.md, section 8.2) : une main par bloc, blocs séparés par
 * « ------------ », sections « *** HEADER *** », « *** PLAYERS *** », etc., heures en UTC.
 */
final class BetclicParser implements HandHistoryParser
{
    /** À incrémenter quand la lecture change : les mains pourront être relues depuis leur texte brut. */
    public const VERSION = 1;

    private const CARD = '[2-9TJQKA][cdhs]';

    private const STREETS = ['PRE-FLOP' => 'preflop', 'FLOP' => 'flop', 'TURN' => 'turn', 'RIVER' => 'river'];

    private const VERBS = [
        'Posts SB' => 'post_sb',
        'Posts BB' => 'post_bb',
        'Folds' => 'fold',
        'Checks' => 'check',
        'Calls' => 'call',
        'Bets' => 'bet',
        'Raises to' => 'raise',
    ];

    public function site(): string
    {
        return 'betclic';
    }

    public function supports(string $content): bool
    {
        return (bool) preg_match('/^Site: Betclic/m', substr(self::normalize($content), 0, 4000));
    }

    public function parse(string $content): ParseResult
    {
        $hands = [];
        $errors = [];

        $blocks = preg_split('/^-{6,}[ \t]*$/m', self::normalize($content));

        foreach ($blocks as $index => $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            try {
                $hands[] = $this->parseHand($block);
            } catch (ParseException $e) {
                $errors[] = [
                    'block' => $index + 1,
                    'hand_id' => preg_match('/^Hand ID: (\S+)/m', $block, $m) ? $m[1] : null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return new ParseResult($hands, $errors);
    }

    /** Retire le BOM UTF-8 et uniformise les fins de ligne (fichiers Windows). */
    public static function normalize(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $content));
    }

    private function parseHand(string $block): ParsedHand
    {
        $sections = $this->sections($block);
        $header = $this->header($sections['HEADER']['lines'] ?? []);

        $playedAt = $this->parseDate($header['Date & Time']);
        [$smallBlind, $bigBlind] = $this->parseBlinds($header['Blinds']);
        $totalPot = $this->parseInt($header['Total Pot'], 'Total Pot');

        // ---------- Joueurs ----------
        $players = [];
        foreach ($sections['PLAYERS']['lines'] ?? [] as $line) {
            if (! preg_match('/^Seat (\d+): (.+) \((\d+)\)(?: \[([^\]]*)\])?$/u', $line, $m)) {
                throw new ParseException("Ligne de joueur illisible : « {$line} »");
            }
            $tags = preg_split('/\s+/', trim($m[4] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $players[$m[2]] = [
                'seat' => (int) $m[1],
                'name' => $m[2],
                'stack' => (int) $m[3],
                'position' => match (true) {
                    in_array('BTN', $tags, true) => 'BTN', // heads-up : le bouton est aussi petite blinde
                    in_array('SB', $tags, true) => 'SB',
                    in_array('BB', $tags, true) => 'BB',
                    default => null,
                },
                'is_hero' => in_array('Hero', $tags, true),
                'cards' => null,
                'shown_rank' => null,
                'invested' => 0,
                'won' => 0,
                'folded' => false,
                'finish_position' => null,
                'prize' => null,
            ];
        }

        $heroes = array_filter($players, fn ($p) => $p['is_hero']);
        if (count($heroes) !== 1) {
            throw new ParseException('Joueur « Hero » introuvable dans la main.');
        }

        // ---------- Cartes privées ----------
        foreach ($sections['HOLE CARDS']['lines'] ?? [] as $line) {
            if (! preg_match('/^(.+): \[('.self::CARD.' '.self::CARD.')\]$/u', $line, $m)) {
                throw new ParseException("Cartes illisibles : « {$line} »");
            }
            $player = &$this->requirePlayer($players, $m[1]);
            $player['cards'] = $m[2];
            unset($player);
        }

        // ---------- Actions, street par street ----------
        $actions = [];
        $board = null;
        $previousAt = null;
        $verbs = implode('|', array_map(fn ($verb) => preg_quote($verb, '/'), array_keys(self::VERBS)));

        foreach (self::STREETS as $sectionName => $street) {
            if (! isset($sections[$sectionName])) {
                continue;
            }
            if ($street !== 'preflop') {
                if (! preg_match('/^\[((?:'.self::CARD.' ?){3,5})\]$/', $sections[$sectionName]['rest'], $m)) {
                    throw new ParseException("Board illisible au {$sectionName}.");
                }
                $board = $m[1]; // la river contient le board complet
            }

            $committed = []; // jetons misés par joueur sur cette street

            foreach ($sections[$sectionName]['lines'] as $line) {
                if (! preg_match('/^(\d{2}:\d{2}:\d{2}) - (.+?): ('.$verbs.')(?: (\d+))?( and is all-in)?$/u', $line, $m)) {
                    throw new ParseException("Action illisible : « {$line} »");
                }

                [, $time, $name, $verb] = $m;
                $type = self::VERBS[$verb];
                $amount = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : null;
                $player = &$this->requirePlayer($players, $name);

                if (in_array($type, ['post_sb', 'post_bb', 'call', 'bet', 'raise'], true) && $amount === null) {
                    throw new ParseException("Montant manquant : « {$line} »");
                }

                $already = $committed[$name] ?? 0;
                $added = match ($type) {
                    'raise' => $amount - $already, // « Raises to » donne le total de la street
                    'fold', 'check' => 0,
                    default => $amount,
                };
                if ($added < 0) {
                    throw new ParseException("Relance inférieure à la mise déjà engagée : « {$line} »");
                }

                $committed[$name] = $already + $added;
                $player['invested'] += $added;
                if ($player['invested'] > $player['stack']) {
                    throw new ParseException("{$name} mise plus que son tapis ({$player['invested']} > {$player['stack']}).");
                }
                $player['folded'] = $player['folded'] || $type === 'fold';
                unset($player);

                $actedAt = $this->actionTime($playedAt, $time);
                $actions[] = new ParsedAction(
                    sequence: count($actions) + 1,
                    street: $street,
                    player: $name,
                    type: $type,
                    amount: $amount,
                    added: $added,
                    streetTotal: $committed[$name],
                    isAllIn: ($m[5] ?? '') !== '',
                    actedAt: $actedAt,
                    // Les blindes sont automatiques : pas de temps de décision
                    decisionSeconds: str_starts_with($type, 'post_') || $previousAt === null
                        ? null
                        : (int) $previousAt->diffInSeconds($actedAt, true),
                );
                $previousAt = $actedAt;
            }
        }

        // ---------- Abattage ----------
        $heroWentToShowdown = false;
        foreach ($sections['SHOWDOWN']['lines'] ?? [] as $line) {
            if (! preg_match('/^(.+) shows \[('.self::CARD.' '.self::CARD.')\](?: \(([^)]+)\))?/u', $line, $m)) {
                throw new ParseException("Abattage illisible : « {$line} »");
            }
            $player = &$this->requirePlayer($players, $m[1]);
            $player['cards'] = $m[2];
            $player['shown_rank'] = $m[3] ?? null;
            $heroWentToShowdown = $heroWentToShowdown || $player['is_hero'];
            unset($player);
        }

        // ---------- Résumé : pots gagnés, places finales ----------
        foreach ($sections['SUMMARY']['lines'] ?? [] as $line) {
            if (preg_match('/^(.+) wins (?:main pot|\d+(?:st|nd|rd|th) side pot) of (\d+)$/u', $line, $m)) {
                $player = &$this->requirePlayer($players, $m[1]);
                $player['won'] += (int) $m[2];
                unset($player);
            } elseif (preg_match('/^(.+) finished (\d+)(?:st|nd|rd|th)(?: and wins (\d+(?:[.,]\d{1,2})?) EUR)?$/u', $line, $m)) {
                $player = &$this->requirePlayer($players, $m[1]);
                $player['finish_position'] = (int) $m[2];
                $player['prize'] = isset($m[3]) ? $this->money($m[3]) : null;
                unset($player);
            } else {
                throw new ParseException("Ligne de résumé inconnue : « {$line} »");
            }
        }

        $this->returnUncalledExcess($players);
        $this->checkPot($players, $totalPot);

        return new ParsedHand(
            site: $this->site(),
            gameId: $header['Game ID'],
            gameName: $header['Game Name'] ?? $header['Game ID'],
            gameMode: $header['Game Mode'] ?? 'Spin',
            buyIn: $this->money($header['Buy In']),
            prizePool: isset($header['Prize pool']) ? $this->money($header['Prize pool']) : null,
            multiplier: isset($header['Multiplier']) && preg_match('/^x(\d+)$/i', $header['Multiplier'], $m) ? (int) $m[1] : null,
            currency: 'EUR',
            handId: $header['Hand ID'],
            tableId: $header['Table ID'] ?? null,
            playedAt: $playedAt->setTimezone(config('app.timezone')),
            endedAt: ($previousAt ?? $playedAt)->setTimezone(config('app.timezone')),
            smallBlind: $smallBlind,
            bigBlind: $bigBlind,
            totalPot: $totalPot,
            board: $board,
            wentToShowdown: $heroWentToShowdown,
            players: array_values(array_map(fn (array $p) => new ParsedPlayer(
                seat: $p['seat'],
                name: $p['name'],
                stack: $p['stack'],
                position: $p['position'],
                isHero: $p['is_hero'],
                cards: $p['cards'],
                shownRank: $p['shown_rank'],
                invested: $p['invested'],
                won: $p['won'],
                finishPosition: $p['finish_position'],
                prize: $p['prize'],
            ), $players)),
            actions: $actions,
            rawText: $block,
        );
    }

    /**
     * Découpe un bloc en sections : nom => [reste de la ligne de titre, lignes].
     *
     * @return array<string, array{rest: string, lines: list<string>}>
     */
    private function sections(string $block): array
    {
        $sections = [];
        $current = null;

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\*\*\* ([A-Z][A-Z \-]*?) \*\*\*\s*(.*)$/', $line, $m)) {
                $current = $m[1];
                $sections[$current] = ['rest' => $m[2], 'lines' => []];
            } elseif ($current !== null) {
                $sections[$current]['lines'][] = $line;
            } else {
                throw new ParseException("Texte inattendu avant l'en-tête : « {$line} »");
            }
        }

        return $sections;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function header(array $lines): array
    {
        $header = [];
        foreach ($lines as $line) {
            [$key, $value] = array_pad(explode(': ', $line, 2), 2, '');
            $header[$key] = trim($value);
        }

        foreach (['Game ID', 'Hand ID', 'Date & Time', 'Blinds', 'Buy In', 'Total Pot'] as $required) {
            if (($header[$required] ?? '') === '') {
                throw new ParseException("Champ « {$required} » absent de l'en-tête.");
            }
        }

        return $header;
    }

    private function parseDate(string $value): CarbonImmutable
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \(([A-Za-z_\/+\-0-9]+)\)$/', $value, $m)) {
            throw new ParseException("Date illisible : « {$value} »");
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $m[1], $m[2]);
        } catch (\Throwable) {
            throw new ParseException("Fuseau horaire inconnu : « {$m[2]} »");
        }
    }

    /** Heure d'une action : même jour que la main, ou le lendemain si la main passe minuit. */
    private function actionTime(CarbonImmutable $playedAt, string $time): CarbonImmutable
    {
        $at = $playedAt->setTimeFromTimeString($time);

        return $at->lessThan($playedAt) ? $at->addDay() : $at;
    }

    /** @return array{0: int, 1: int} */
    private function parseBlinds(string $value): array
    {
        if (! preg_match('/^(\d+)\/(\d+)$/', $value, $m)) {
            throw new ParseException("Blindes illisibles : « {$value} »");
        }

        return [(int) $m[1], (int) $m[2]];
    }

    private function parseInt(string $value, string $field): int
    {
        if (! ctype_digit($value)) {
            throw new ParseException("« {$field} » doit être un nombre entier : « {$value} »");
        }

        return (int) $value;
    }

    /** « 1.00€ », « 10,5 EUR » → « 1.00 », « 10.50 » */
    private function money(string $value): string
    {
        if (! preg_match('/^(\d+)(?:[.,](\d{1,2}))?\s*(?:€|EUR)?$/u', trim($value), $m)) {
            throw new ParseException("Montant illisible : « {$value} »");
        }

        return $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
    }

    /**
     * @param  array<string, array<string, mixed>>  $players
     * @return array<string, mixed>
     */
    private function &requirePlayer(array &$players, string $name): array
    {
        if (! isset($players[$name])) {
            throw new ParseException("Joueur inconnu dans la main : « {$name} »");
        }

        return $players[$name];
    }

    /**
     * Mise non suivie : quand un tapis est payé par un joueur plus court, l'excédent est rendu
     * et Betclic ne le compte pas dans le pot. Quand tout le monde se couche, Betclic laisse
     * la mise dans le pot (le gagnant la récupère avec « wins main pot »).
     *
     * @param  array<string, array<string, mixed>>  $players
     */
    private function returnUncalledExcess(array &$players): void
    {
        $stillIn = array_filter($players, fn ($p) => ! $p['folded']);
        if (count($stillIn) < 2) {
            return;
        }

        $byInvested = array_column($players, 'invested', 'name');
        arsort($byInvested);
        $names = array_keys($byInvested);
        $amounts = array_values($byInvested);

        // Le plus gros contributeur ne peut pas engager plus que le deuxième (couché ou non)
        $excess = $amounts[0] - ($amounts[1] ?? 0);
        if ($excess > 0) {
            $players[$names[0]]['invested'] -= $excess;
        }
    }

    /**
     * Contrôle de cohérence : les mises et les gains doivent tous deux égaler le pot annoncé.
     *
     * @param  array<string, array<string, mixed>>  $players
     */
    private function checkPot(array $players, int $totalPot): void
    {
        $invested = array_sum(array_column($players, 'invested'));
        if ($invested !== $totalPot) {
            throw new ParseException("Somme des mises ({$invested}) différente du pot total ({$totalPot}).");
        }

        $won = array_sum(array_column($players, 'won'));
        if ($won !== $totalPot) {
            throw new ParseException("Somme des gains ({$won}) différente du pot total ({$totalPot}).");
        }
    }
}
