<?php

namespace Tests\Feature;

use App\Models\PokerSession;
use App\Services\BankrollStatsService;
use App\Support\SessionFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class BankrollStatsTest extends TestCase
{
    use RefreshDatabase;

    private function play(string $playedAt, string $format, int $minutes, string $result): PokerSession
    {
        return PokerSession::factory()->create([
            'played_at' => $playedAt,
            'format' => $format,
            'duration_minutes' => $minutes,
            'net_result' => $result,
        ]);
    }

    private function stats(array $query = [])
    {
        $filters = SessionFilters::fromRequest(Request::create('/dashboard', 'GET', $query));

        return app(BankrollStatsService::class)->compute($filters->apply(PokerSession::query()));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Créées dans le désordre, pour vérifier le tri chronologique de la courbe
        $this->play('2026-09-10 21:00', 'mtt', 240, '150.00');
        $this->play('2026-09-01 20:00', 'cash', 120, '-40.50');
        $this->play('2026-09-10 19:00', 'cash', 90, '0.00');
        $this->play('2026-09-20 20:00', 'sng', 60, '-10.00');
    }

    public function test_les_totaux_sont_corrects(): void
    {
        $stats = $this->stats();

        $this->assertSame(4, $stats->count);
        $this->assertSame(99.5, $stats->profit);
        $this->assertSame(510, $stats->minutes);
        $this->assertSame(11.71, $stats->hourlyRate()); // 99,50 € / 8,5 h
    }

    public function test_la_courbe_suit_l_ordre_chronologique(): void
    {
        $values = array_column($this->stats()->curve, 'value');

        // 01/09 (−40,50), 10/09 19 h (0), 10/09 21 h (+150), 20/09 (−10)
        $this->assertSame([-40.5, -40.5, 109.5, 99.5], $values);
    }

    public function test_aucune_session_donne_un_taux_horaire_vide(): void
    {
        $stats = $this->stats(['format' => 'mtt', 'to' => '2026-08-31']);

        $this->assertSame(0, $stats->count);
        $this->assertSame(0.0, $stats->profit);
        $this->assertNull($stats->hourlyRate());
        $this->assertSame([], $stats->curve);
    }

    public function test_filtre_par_format(): void
    {
        $stats = $this->stats(['format' => 'cash']);

        $this->assertSame(2, $stats->count);
        $this->assertSame(-40.5, $stats->profit);
    }

    public function test_filtre_par_periode_bornes_incluses(): void
    {
        $stats = $this->stats(['from' => '2026-09-10', 'to' => '2026-09-10']);

        $this->assertSame(2, $stats->count);
        $this->assertSame(150.0, $stats->profit);
    }

    public function test_filtre_par_resultat(): void
    {
        $this->assertSame(1, $this->stats(['result' => 'win'])->count);
        $this->assertSame(2, $this->stats(['result' => 'loss'])->count);
        $this->assertSame(1, $this->stats(['result' => 'even'])->count);
    }

    public function test_les_filtres_invalides_sont_ignores(): void
    {
        $filters = SessionFilters::fromRequest(Request::create('/dashboard', 'GET', [
            'format' => 'holdem', 'from' => '2026-02-31', 'to' => 'hier', 'result' => 'all',
        ]));

        $this->assertFalse($filters->isActive());
        $this->assertSame(4, $this->stats(['format' => 'holdem'])->count);
    }
}
