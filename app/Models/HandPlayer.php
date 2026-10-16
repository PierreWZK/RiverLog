<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un joueur assis pendant une main (servira aux profils adverses, phase 4).
 */
#[Fillable(['seat', 'name', 'stack', 'position', 'is_hero', 'cards', 'shown_rank', 'invested', 'won', 'finish_position', 'prize'])]
class HandPlayer extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_hero' => 'boolean', 'prize' => 'decimal:2'];
    }
}
