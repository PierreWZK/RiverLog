<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une main importée (identifiée par son Hand ID), avec les chiffres du Hero.
 * Le texte d'origine est gardé : la main peut être relue si le parser évolue.
 *
 * @property \Illuminate\Support\Carbon $played_at
 */
#[Fillable([
    'tournament_id', 'hand_import_id', 'external_id', 'table_id', 'played_at', 'ended_at', 'small_blind',
    'big_blind', 'players_count', 'hero_position', 'hero_cards', 'hero_stack', 'hero_stack_bb', 'board',
    'total_pot', 'hero_invested', 'hero_won', 'hero_net', 'went_to_showdown', 'hero_all_in',
    'hero_finish_position', 'hero_prize', 'raw_text', 'parser_version',
])]
class Hand extends Model
{
    /** @return BelongsTo<Tournament, $this> */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** @return HasMany<HandPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(HandPlayer::class);
    }

    /** @return HasMany<HandAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(HandAction::class)->orderBy('sequence');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'ended_at' => 'datetime',
            'hero_stack_bb' => 'decimal:2',
            'hero_prize' => 'decimal:2',
            'hero_net' => 'integer',
            'players_count' => 'integer',
            'hero_finish_position' => 'integer',
            'went_to_showdown' => 'boolean',
            'hero_all_in' => 'boolean',
        ];
    }
}
