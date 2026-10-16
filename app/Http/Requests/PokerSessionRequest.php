<?php

namespace App\Http\Requests;

use App\Enums\PokerFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation d'une session (ajout et modification).
 *
 * Le résultat peut être saisi à la française (« -12,50 ») :
 * il est normalisé en « -12.50 » avant la validation.
 */
class PokerSessionRequest extends FormRequest
{
    /** Une session ne dépasse pas 24 h. */
    private const MAX_MINUTES = 1440;

    public function authorize(): bool
    {
        return true; // l'accès à la session elle-même est vérifié par PokerSessionPolicy
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->net_result)) {
            // « + 1 250,50 » → « 1250.50 »
            $this->merge([
                'net_result' => str_replace([' ', "\u{00A0}", '+', ','], ['', '', '', '.'], trim($this->net_result)),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'played_at' => ['required', 'date', 'before_or_equal:now'],
            'format' => ['required', Rule::enum(PokerFormat::class)],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MINUTES],
            'net_result' => ['required', 'regex:/^-?\d{1,8}(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'played_at.required' => 'Indique la date de la session.',
            'played_at.date' => 'Cette date n\'est pas valide.',
            'played_at.before_or_equal' => 'La session ne peut pas être dans le futur.',
            'format.required' => 'Choisis un format de jeu.',
            'format.enum' => 'Ce format n\'existe pas.',
            'duration_minutes.required' => 'Indique la durée de la session.',
            'duration_minutes.integer' => 'La durée doit être un nombre entier de minutes.',
            'duration_minutes.min' => 'La durée doit être d\'au moins 1 minute.',
            'duration_minutes.max' => 'Une session ne peut pas dépasser 24 h.',
            'net_result.required' => 'Indique le résultat (0 pour une session à l\'équilibre).',
            'net_result.regex' => 'Le résultat doit être un montant, ex. 42,50 ou -20.',
            'notes.max' => 'Les notes ne peuvent pas dépasser 1000 caractères.',
        ];
    }
}
