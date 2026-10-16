<?php

namespace App\Enums;

/**
 * Formats de jeu autorisés pour une session.
 *
 * La valeur (cash, mtt, sng) est stockée en base et utilisée dans l'URL
 * des filtres (?format=mtt) ; le libellé sert à l'affichage.
 */
enum PokerFormat: string
{
    case Cash = 'cash';
    case Mtt = 'mtt';
    case SitAndGo = 'sng';
    case Spin = 'spin'; // Spin & Rush, Expresso… (souvent créés par l'import d'historiques)

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash Game',
            self::Mtt => 'MTT',
            self::SitAndGo => 'Sit & Go',
            self::Spin => 'Spin',
        };
    }

    /** Format d'un tournoi importé, d'après le « Game Mode » du site. */
    public static function fromGameMode(string $gameMode): self
    {
        return match (strtolower($gameMode)) {
            'spin', 'expresso', 'twister' => self::Spin,
            'mtt', 'tournament' => self::Mtt,
            'cash', 'cash game' => self::Cash,
            default => self::SitAndGo,
        };
    }

    /**
     * Liste [valeur => libellé], pratique pour les listes déroulantes.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $format) {
            $options[$format->value] = $format->label();
        }

        return $options;
    }
}
