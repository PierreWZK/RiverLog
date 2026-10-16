<?php

namespace Database\Factories;

use App\Enums\PokerFormat;
use App\Models\PokerSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sessions fictives mais réalistes : la durée et le résultat dépendent du format.
 *
 * @extends Factory<PokerSession>
 */
class PokerSessionFactory extends Factory
{
    private const NOTES = [
        'Table très passive, beaucoup de value bets.',
        'Gros bad beat sur la river.',
        'Bonne discipline, aucun tilt.',
        'Fatigué en fin de session, j\'aurais dû arrêter plus tôt.',
        'Bulle passée de justesse.',
        'Joué trop large en début de partie.',
        'Session courte, peu de mains jouables.',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $format = fake()->randomElement([
            PokerFormat::Cash, PokerFormat::Cash, PokerFormat::Cash,
            PokerFormat::Mtt, PokerFormat::Mtt,
            PokerFormat::SitAndGo,
        ]);

        [$duration, $result] = match ($format) {
            PokerFormat::Cash => $this->cashGame(),
            PokerFormat::Mtt => $this->tournament(),
            PokerFormat::SitAndGo => $this->sitAndGo(),
        };

        return [
            'user_id' => User::factory(),
            'played_at' => fake()->dateTimeBetween('-4 months', 'now'),
            'format' => $format,
            'duration_minutes' => $duration,
            'net_result' => number_format($result, 2, '.', ''),
            'notes' => fake()->boolean(30) ? fake()->randomElement(self::NOTES) : null,
        ];
    }

    /** Cash Game : 1 à 4 h, gain ou perte proportionnel au temps joué. */
    private function cashGame(): array
    {
        $minutes = fake()->numberBetween(4, 16) * 15;

        if (fake()->boolean(5)) {
            return [$minutes, 0]; // break-even
        }

        $perHour = fake()->numberBetween(-45, 55);

        return [$minutes, round($perHour * $minutes / 60 * 2) / 2]; // arrondi à 0,50 €
    }

    /** MTT : long, souvent perdu (le buy-in), parfois une place payée ou un gros résultat. */
    private function tournament(): array
    {
        $buyIn = fake()->randomElement([10, 20, 30, 50]);
        $roll = fake()->numberBetween(1, 100);

        [$minutes, $prize] = match (true) {
            $roll <= 72 => [fake()->numberBetween(6, 16) * 15, 0],
            $roll <= 95 => [fake()->numberBetween(14, 24) * 15, $buyIn * fake()->randomFloat(1, 1.5, 4)],
            default => [fake()->numberBetween(22, 30) * 15, $buyIn * fake()->randomFloat(1, 8, 20)],
        };

        return [$minutes, round($prize - $buyIn, 2)];
    }

    /** Sit & Go : 30 à 90 min, le résultat dépend de la place finale. */
    private function sitAndGo(): array
    {
        $buyIn = fake()->randomElement([5, 10, 20]);
        $minutes = fake()->numberBetween(6, 18) * 5;

        $result = fake()->randomElement([
            -$buyIn, -$buyIn, -$buyIn, // hors des places payées
            $buyIn * 0.8,              // 2e place
            $buyIn * 2,                // victoire
        ]);

        return [$minutes, $result];
    }
}
