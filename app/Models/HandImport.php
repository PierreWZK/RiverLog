<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un fichier d'historique importé, avec le résumé de l'import.
 *
 * @property list<array{block: int, hand_id: ?string, message: string, file?: string}>|null $errors
 */
#[Fillable(['filename', 'stored_path', 'sha256', 'site', 'hands_found', 'hands_imported', 'duplicates', 'tournaments_touched', 'errors'])]
class HandImport extends Model
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'hands_found' => 'integer',
            'hands_imported' => 'integer',
            'duplicates' => 'integer',
            'tournaments_touched' => 'integer',
        ];
    }
}
