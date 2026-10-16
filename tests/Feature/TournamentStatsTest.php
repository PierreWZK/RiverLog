<?php

namespace Tests\Feature;

use App\HandHistory\HandImporter;
use App\Models\User;
use App\Services\TournamentStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Statistiques de la page Tournois sur le fichier de test (cahier des charges, section 8.3).
 */
class TournamentStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        app(HandImporter::class)->importTexts(
            $this->user,
            'spin.txt',
            str_repeat('b', 64),
            ['spin.txt' => file_get_contents(base_path('tests/Fixtures/betclic/spin-2026-01-29.txt'))],
        );
    }

    public function test_statistiques_globales(): void
    {
        $stats = app(TournamentStatsService::class)->compute($this->user->tournaments()->get());

        $this->assertSame([5, 0, 41], [$stats->count, $stats->incomplete, $stats->hands]);
        $this->assertSame([5.0, 14.0, 9.0], [$stats->buyIns, $stats->winnings, $stats->profit]);
        $this->assertSame(180.0, $stats->roi());
        $this->assertSame([2, 40.0, 40.0], [$stats->wins, $stats->winRate(), $stats->itm()]);
        $this->assertSame(1.8, $stats->averageProfit());
        $this->assertSame([3, 2, 66.7], [$stats->headsUpReached, $stats->headsUpWon, $stats->headsUpConversion()]);
        $this->assertSame([1 => 2, 2 => 1, 3 => 2], $stats->positions);
        // Temps réel des 5 tournois : 3:24 + 3:13 + 0:56 + 0:45 + 4:48 = 13 min
        $this->assertSame(13, $stats->minutes);
        $this->assertTrue($stats->isSmallSample());
    }

    public function test_par_multiplicateur_et_jackpots(): void
    {
        $stats = app(TournamentStatsService::class)->compute($this->user->tournaments()->get());

        $rows = collect($stats->byMultiplier)->keyBy('label');
        $this->assertSame(['x2', 'x4', 'x10'], $rows->keys()->all());
        $this->assertSame([3, 0, -3.0, -100.0], [$rows['x2']['count'], $rows['x2']['wins'], $rows['x2']['profit'], $rows['x2']['roi']]);
        $this->assertSame([1, 1, 9.0, 900.0], [$rows['x10']['count'], $rows['x10']['wins'], $rows['x10']['profit'], $rows['x10']['roi']]);
        $this->assertSame(60.0, $rows['x2']['share']);

        // Sans le x10 : 4 € de buy-ins pour 4 € de gains
        $this->assertSame(0.0, $stats->roiWithoutJackpots);
        $this->assertSame(71.4, $stats->jackpotShare); // 10 € sur 14 € de gains
    }

    public function test_parties_importees_sur_la_page_import(): void
    {
        $this->actingAs($this->user)
            ->get('/imports')
            ->assertOk()
            ->assertSee('Parties importées')
            ->assertSee("+180,0\u{00A0}%", false)
            ->assertSee('Échantillon de 5 parties', false)
            ->assertSee('Spin &amp; Rush 1€', false);

        $this->get('/imports?multiplier=2')
            ->assertOk()
            ->assertSee("\u{2212}100,0\u{00A0}%", false)
            ->assertSee('<option value="2" selected>', false);
    }

    public function test_l_ancienne_page_tournois_redirige_vers_import(): void
    {
        $this->actingAs($this->user)
            ->get('/tournaments?multiplier=2')
            ->assertRedirect('/imports?multiplier=2#parties');
    }

    public function test_detail_d_un_tournoi(): void
    {
        $tournament = $this->user->tournaments()->where('multiplier', 10)->sole();

        $this->actingAs($this->user)
            ->get("/tournaments/{$tournament->id}")
            ->assertOk()
            ->assertSee('1re / 3')
            ->assertSee('victoire')
            ->assertSee('class="card card-h">10<b>♥</b>', false); // Th 9h de la dernière main
    }

    public function test_demo_publique_des_tournois(): void
    {
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        app(HandImporter::class)->importTexts($demo, 'demo.txt', str_repeat('c', 64), [
            'demo.txt' => file_get_contents(base_path('tests/Fixtures/betclic/spin-2026-01-29.txt')),
        ]);
        $own = $this->user->tournaments()->first();

        $this->get('/demo/tournaments')->assertOk()->assertSee('Exemple de parties importées', false);
        $this->get('/demo/tournaments/'.$demo->tournaments()->first()->id)->assertOk();
        // Un tournoi d'un vrai compte n'est pas visible via la démo
        $this->get("/demo/tournaments/{$own->id}")->assertNotFound();
    }
}
