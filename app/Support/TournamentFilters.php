<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Filtres de la page Tournois, lus dans l'URL : ?from=2026-01-01&to=2026-01-31&multiplier=10&buy_in=1.00
 * Une valeur invalide est ignorée.
 */
final readonly class TournamentFilters
{
    public function __construct(
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public ?int $multiplier = null,
        public ?string $buyIn = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $multiplier = $request->query('multiplier');
        $buyIn = $request->query('buy_in');

        return new self(
            from: self::date($request->query('from')),
            to: self::date($request->query('to')),
            multiplier: is_string($multiplier) && ctype_digit($multiplier) ? (int) $multiplier : null,
            buyIn: is_string($buyIn) && preg_match('/^\d{1,6}\.\d{2}$/', $buyIn) ? $buyIn : null,
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
            ->when($this->from, fn ($q, $from) => $q->where('started_at', '>=', $from->copy()->startOfDay()))
            ->when($this->to, fn ($q, $to) => $q->where('started_at', '<=', $to->copy()->endOfDay()))
            ->when($this->multiplier, fn ($q, $multiplier) => $q->where('multiplier', $multiplier))
            ->when($this->buyIn, fn ($q, $buyIn) => $q->where('buy_in', $buyIn));
    }

    public function isActive(): bool
    {
        return $this->toQuery() !== [];
    }

    /** @return array<string, string> */
    public function toQuery(): array
    {
        return array_filter([
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'multiplier' => $this->multiplier ? (string) $this->multiplier : null,
            'buy_in' => $this->buyIn,
        ]);
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->toDateString() === $value ? $date : null;
    }
}
