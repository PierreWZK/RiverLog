<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'un compte à un défi quotidien (une ligne par défi et par jour).
 *
 * @property \Illuminate\Support\Carbon $day
 * @property array<string, mixed> $details
 */
#[Fillable(['challenge', 'day', 'score', 'max_score', 'details'])]
class ChallengeResult extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'score' => 'integer',
            'max_score' => 'integer',
            'details' => 'array',
        ];
    }
}
