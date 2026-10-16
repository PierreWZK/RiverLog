<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Affichage des cartes : « Th 9h » → 10♥ 9♥, avec un jeu 4 couleurs
 * (♠ clair, ♥ rouge, ♦ bleu, ♣ vert) pour lire les couleurs d'un coup d'œil.
 */
final class Cards
{
    private const SUITS = ['s' => '♠', 'h' => '♥', 'd' => '♦', 'c' => '♣'];

    public static function html(?string $cards): HtmlString
    {
        if ($cards === null || trim($cards) === '') {
            return new HtmlString('<span class="cards-none">—</span>');
        }

        $html = '';
        foreach (preg_split('/\s+/', trim($cards)) as $card) {
            $rank = $card[0] === 'T' ? '10' : $card[0];
            $suit = $card[1] ?? '';
            $html .= '<span class="card card-'.e($suit).'">'.e($rank).'<b>'.(self::SUITS[$suit] ?? '?').'</b></span>';
        }

        return new HtmlString('<span class="cards">'.$html.'</span>');
    }
}
