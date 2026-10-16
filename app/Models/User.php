<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Compte de démonstration : ses sessions sont affichées en lecture seule sur /demo. */
    public const DEMO_EMAIL = 'demo@riverlog.test';

    /**
     * Les sessions de poker de ce compte.
     *
     * @return HasMany<PokerSession, $this>
     */
    public function pokerSessions(): HasMany
    {
        return $this->hasMany(PokerSession::class);
    }

    /** @return HasMany<Tournament, $this> */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /** @return HasMany<HandImport, $this> */
    public function handImports(): HasMany
    {
        return $this->hasMany(HandImport::class);
    }

    /** @return HasMany<ChallengeResult, $this> */
    public function challengeResults(): HasMany
    {
        return $this->hasMany(ChallengeResult::class);
    }

    /** @return HasMany<Hand, $this> */
    public function hands(): HasMany
    {
        return $this->hasMany(Hand::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
