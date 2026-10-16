<?php

namespace Tests\Unit;

use App\HandHistory\Parsers\BetclicParser;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\SpinHistoryGenerator;
use Tests\TestCase;

/**
 * L'historique fictif de la démo doit toujours être accepté par le vrai parser.
 */
class SpinHistoryGeneratorTest extends TestCase
{
    public function test_les_tournois_generes_sont_valides_et_complets(): void
    {
        foreach ([1, 42, 2026, 31337] as $seed) {
            fake()->seed($seed);
            $generator = new SpinHistoryGenerator;

            for ($i = 0; $i < 25; $i++) {
                $text = $generator->tournament(CarbonImmutable::parse('2026-09-01 19:00:00', 'UTC'))['text'];
                $result = (new BetclicParser)->parse($text);

                $this->assertSame([], $result->errors, "Graine {$seed}, tournoi {$i}");

                // La dernière main (en premier dans le fichier) donne la place finale du Hero
                $hero = $result->hands[0]->hero();
                $this->assertNotNull($hero->finishPosition, "Graine {$seed}, tournoi {$i} : place finale absente");
            }
        }
    }
}
