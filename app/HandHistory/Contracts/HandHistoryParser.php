<?php

namespace App\HandHistory\Contracts;

use App\HandHistory\Data\ParseResult;

/**
 * Lecteur d'historiques de mains d'un site de poker.
 *
 * Un parser par site (Betclic pour commencer) : en ajouter un ne change rien au reste de l'import.
 */
interface HandHistoryParser
{
    /** Identifiant court du site, stocké avec les tournois (ex. « betclic »). */
    public function site(): string;

    /** Le contenu vient-il de ce site ? */
    public function supports(string $content): bool;

    /** Découpe le fichier en mains ; une main illisible devient une erreur sans bloquer les autres. */
    public function parse(string $content): ParseResult;
}
