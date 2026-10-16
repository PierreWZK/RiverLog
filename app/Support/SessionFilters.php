<?php

namespace App\Support;

use App\Enums\PokerFormat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Filtres du dashboard, lus dans l'URL : ?format=mtt&from=2026-09-01&to=2026-09-30&result=win
 *
 * Une valeur invalide est simplement ignorée : une URL bricolée ne casse pas la page.
 * La même requête filtrée alimente le tableau, les cartes et la courbe.
 */
final readonly class SessionFilters
{
    public const RESULTS = ['win' => 'Gagnantes', 'loss' => 'Perdantes', 'even' => 'À l\'équilibre'];

    public function __construct(
        public ?PokerFormat $format = null,
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public ?string $result = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $result = $request->query('result');

        return new self(
            format: PokerFormat::tryFrom((string) $request->query('format')),
            from: self::parseDate($request->query('from')),
            to: self::parseDate($request->query('to')),
            result: is_string($result) && array_key_exists($result, self::RESULTS) ? $result : null,
        );
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->format, fn ($q, $format) => $q->where('format', $format->value))
            ->when($this->from, fn ($q, $from) => $q->where('played_at', '>=', $from->copy()->startOfDay()))
            ->when($this->to, fn ($q, $to) => $q->where('played_at', '<=', $to->copy()->endOfDay()))
            ->when($this->result === 'win', fn ($q) => $q->where('net_result', '>', 0))
            ->when($this->result === 'loss', fn ($q) => $q->where('net_result', '<', 0))
            ->when($this->result === 'even', fn ($q) => $q->where('net_result', '=', 0));
    }

    public function isActive(): bool
    {
        return $this->toQuery() !== [];
    }

    /**
     * Paramètres d'URL des filtres actifs (pour les liens et le formulaire).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'format' => $this->format?->value,
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'result' => $this->result,
        ]);
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        // createFromFormat accepte « 2026-02-31 » (→ 3 mars) : on refuse ces dates
        return $date && $date->toDateString() === $value ? $date : null;
    }
}
