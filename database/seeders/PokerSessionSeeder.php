<?php

namespace Database\Seeders;

use App\Models\PokerSession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Compte de démonstration et ses 36 sessions, réparties sur environ 3 mois, jusqu'à ces derniers jours.
 * Relancer le seeder remplace les sessions du compte de démo (les autres comptes ne sont pas touchés).
 *
 * Le générateur aléatoire est initialisé avec une valeur fixe :
 * le seeder produit toujours les mêmes sessions (démos et captures reproductibles).
 * Certains soirs comptent plusieurs sessions, pour vérifier l'ordre chronologique,
 * et une session est à l'équilibre (0 €), pour le filtre « nulles ».
 */
class PokerSessionSeeder extends Seeder
{
    private const SESSIONS = 36;

    private const BREAK_EVEN_SESSION = 9; // index de la session forcée à 0 €

    public function run(): void
    {
        fake()->seed(2026);

        // Compte de démonstration : personne ne s'y connecte (mot de passe aléatoire),
        // ses sessions sont seulement affichées en lecture seule sur /demo
        $demo = User::updateOrCreate(
            ['email' => User::DEMO_EMAIL],
            ['name' => 'Démo RiverLog', 'password' => Str::random(40)],
        );
        $demo->pokerSessions()->delete();

        $date = Carbon::today()->subDays(90)->setTime(19, 0);
        $previousEnd = null;

        for ($i = 0; $i < self::SESSIONS; $i++) {
            $sameEvening = $previousEnd !== null && fake()->boolean(18);

            if ($sameEvening) {
                // Deuxième session du soir : elle commence 30 min après la fin de la précédente
                $date = $previousEnd->copy()->addMinutes(30);
            } else {
                $date = $date->copy()->addDays(fake()->numberBetween(1, 6))
                    ->setTime(fake()->numberBetween(18, 21), fake()->randomElement([0, 15, 30, 45]));
            }

            $attributes = ['played_at' => $date];
            if ($i === self::BREAK_EVEN_SESSION) {
                $attributes['net_result'] = '0.00';
            }

            $session = PokerSession::factory()->for($demo)->create($attributes);
            $previousEnd = $date->copy()->addMinutes($session->duration_minutes);
        }
    }
}
