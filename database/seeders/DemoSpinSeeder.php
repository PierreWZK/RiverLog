<?php

namespace Database\Seeders;

use App\HandHistory\HandImporter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\SpinHistoryGenerator;
use Illuminate\Database\Seeder;

/**
 * Spins fictifs du compte de démonstration (page /demo/tournaments).
 *
 * L'historique est généré au format Betclic puis importé avec le vrai importeur :
 * la démo passe par exactement le même code que les imports réels.
 */
class DemoSpinSeeder extends Seeder
{
    private const SESSIONS = 14;

    public function run(HandImporter $importer): void
    {
        $demo = User::where('email', User::DEMO_EMAIL)->firstOrFail();

        // Repart de zéro : anciens imports, tournois et sessions importées de la démo
        $demo->handImports()->delete();
        $demo->tournaments()->delete();

        $generator = new SpinHistoryGenerator(heroName: 'Vous');
        $parts = [];
        $day = CarbonImmutable::today(config('app.timezone'))->subDays(58);

        for ($s = 0; $s < self::SESSIONS; $s++) {
            $day = $day->addDays(fake()->numberBetween(2, 5));
            // Soirée à Paris, convertie en UTC comme dans les vrais exports
            $time = $day->setTime(fake()->numberBetween(19, 22), fake()->randomElement([0, 15, 30, 45]))->utc();

            for ($t = 0, $spins = fake()->numberBetween(3, 8); $t < $spins; $t++) {
                $tournament = $generator->tournament($time);
                $parts[] = $tournament['text'];
                $time = $tournament['ended_at']->addSeconds(fake()->numberBetween(15, 90));
            }
        }

        $content = implode("\n\n------------\n\n", array_reverse($parts)); // les plus récentes en premier
        $import = $importer->importTexts($demo, 'demo-spins.txt', hash('sha256', $content), ['demo-spins.txt' => $content]);

        // Le générateur doit produire des mains que le parser accepte toutes
        if ($import->errors) {
            throw new \RuntimeException('Historique de démo invalide : '.json_encode(array_slice($import->errors, 0, 3), JSON_UNESCAPED_UNICODE));
        }
    }
}
