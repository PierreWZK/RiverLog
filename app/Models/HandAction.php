<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Une action d'une main, normalisée (voir App\HandHistory\Data\ParsedAction).
 */
#[Fillable(['sequence', 'street', 'player', 'type', 'amount', 'added', 'street_total', 'is_all_in', 'acted_at', 'decision_seconds'])]
class HandAction extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_all_in' => 'boolean', 'acted_at' => 'datetime'];
    }
}
