<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un tournoi importé (identifié par son Game ID).
 * Ses chiffres sont recalculés à partir de ses mains par TournamentAggregator.
 *
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon $ended_at
 * @property string $buy_in
 * @property string $winnings
 * @property string|null $net_result null tant que le tournoi est incomplet
 */
#[Fillable([
    'site', 'external_id', 'game_name', 'game_mode', 'buy_in', 'prize_pool', 'multiplier', 'currency',
    'players_count', 'started_at', 'ended_at', 'hands_count', 'finish_position', 'winnings', 'net_result',
    'is_complete', 'reached_heads_up', 'poker_session_id',
])]
class Tournament extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Hand, $this> */
    public function hands(): HasMany
    {
        return $this->hasMany(Hand::class);
    }

    /** @return BelongsTo<PokerSession, $this> */
    public function pokerSession(): BelongsTo
    {
        return $this->belongsTo(PokerSession::class);
    }

    public function multiplierLabel(): string
    {
        return $this->multiplier ? 'x'.$this->multiplier : '—';
    }

    public function durationMinutes(): int
    {
        return (int) ceil($this->started_at->diffInSeconds($this->ended_at, true) / 60);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'buy_in' => 'decimal:2',
            'prize_pool' => 'decimal:2',
            'winnings' => 'decimal:2',
            'net_result' => 'decimal:2',
            'multiplier' => 'integer',
            'players_count' => 'integer',
            'hands_count' => 'integer',
            'finish_position' => 'integer',
            'is_complete' => 'boolean',
            'reached_heads_up' => 'boolean',
        ];
    }
}
