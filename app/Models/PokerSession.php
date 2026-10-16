<?php

namespace App\Models;

use App\Enums\PokerFormat;
use Database\Factories\PokerSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une session de poker, rattachée au compte qui l'a saisie.
 * user_id n'est pas « fillable » : il est fixé par la relation ($user->pokerSessions()->create()).
 *
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $played_at
 * @property PokerFormat $format
 * @property int $duration_minutes
 * @property string $net_result Montant décimal signé, ex. "-50.00"
 * @property string|null $notes
 */
#[Fillable(['played_at', 'format', 'duration_minutes', 'net_result', 'notes'])]
class PokerSession extends Model
{
    /** @use HasFactory<PokerSessionFactory> */
    use HasFactory;

    /** Session générée par un import d'historique (non fillable : jamais modifiable via le formulaire). */
    public const SOURCE_IMPORT = 'import';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tournois importés regroupés dans cette session.
     *
     * @return HasMany<Tournament, $this>
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /** Les sessions importées sont recalculées à chaque import : on ne les modifie pas à la main. */
    public function isImported(): bool
    {
        return $this->source === self::SOURCE_IMPORT;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'format' => PokerFormat::class,
            'duration_minutes' => 'integer',
            // Reste une chaîne exacte ("-50.00") : pas d'erreur d'arrondi des nombres à virgule
            'net_result' => 'decimal:2',
        ];
    }
}
