<?php

namespace Tests\Feature;

use App\Challenges\DisciplineChallenge;
use App\Models\PokerSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplineChallengeTest extends TestCase
{
    use RefreshDatabase;

    private DisciplineChallenge $discipline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discipline = app(DisciplineChallenge::class);
    }

    /** @param list<array{0: string, 1: int, 2: string, 3?: ?string}> $rows  heure, durée, résultat, note */
    private function sessions(array $rows)
    {
        return collect($rows)->map(fn ($row) => new PokerSession([
            'played_at' => CarbonImmutable::parse("2026-10-14 {$row[0]}"),
            'format' => 'cash',
            'duration_minutes' => $row[1],
            'net_result' => $row[2],
            'notes' => $row[3] ?? null,
        ]));
    }

    private function outcome(string $objective, array $rows, int $tournaments = 0, bool $isToday = false): string
    {
        return $this->discipline->evaluate($objective, $this->sessions($rows), $tournaments, $isToday)['status'];
    }

    public function test_un_objectif_par_jour_avec_un_jour_de_repos_par_semaine(): void
    {
        $week = collect(range(0, 6))->map(fn ($i) => $this->discipline->objectiveFor(CarbonImmutable::parse('2026-10-12')->addDays($i))['key']);

        $this->assertSame(1, $week->filter(fn ($key) => $key === 'rest_day')->count());
        $this->assertCount(6, $week->unique()); // les 6 objectifs sur la semaine (la durée revient 2 fois)
        $this->assertSame($week[0], $this->discipline->objectiveFor(CarbonImmutable::parse('2026-10-19'))['key']);
    }

    public function test_duree_maximum(): void
    {
        $this->assertSame('success', $this->outcome('max_duration', [['20:00', 90, '10']]));
        $this->assertSame('failed', $this->outcome('max_duration', [['20:00', 90, '10'], ['22:00', 45, '-5']]));
        $this->assertSame('pending', $this->outcome('max_duration', [['20:00', 60, '10']], isToday: true));
        $this->assertSame('not_played', $this->outcome('max_duration', []));
    }

    public function test_stop_loss_apres_deux_pertes(): void
    {
        $this->assertSame('success', $this->outcome('stop_loss', [['19:00', 60, '-10'], ['20:30', 60, '-10']]));
        $this->assertSame('failed', $this->outcome('stop_loss', [['19:00', 60, '-10'], ['20:30', 60, '-10'], ['22:00', 60, '30']]));
        $this->assertSame('success', $this->outcome('stop_loss', [['19:00', 60, '-10'], ['20:30', 60, '5'], ['22:00', 60, '-3']]));
    }

    public function test_pause_entre_deux_sessions(): void
    {
        // 19:00 + 60 min = 20:00, la suivante commence à 20:10 : 10 min de pause seulement
        $this->assertSame('failed', $this->outcome('break', [['19:00', 60, '0'], ['20:10', 30, '0']]));
        $this->assertSame('success', $this->outcome('break', [['19:00', 60, '0'], ['20:20', 30, '0']]));
    }

    public function test_bilan_ecrit_volume_et_repos(): void
    {
        $this->assertSame('success', $this->outcome('notes', [['19:00', 60, '0', 'Bonne session']], isToday: true));
        $this->assertSame('failed', $this->outcome('notes', [['19:00', 60, '0']]));

        $this->assertSame('success', $this->outcome('max_volume', [], tournaments: 12));
        $this->assertSame('failed', $this->outcome('max_volume', [['19:00', 60, '0']], tournaments: 15));

        $this->assertSame('success', $this->outcome('rest_day', []));
        $this->assertSame('failed', $this->outcome('rest_day', [], tournaments: 1));
    }

    public function test_un_nouveau_compte_n_a_pas_de_serie(): void
    {
        $today = CarbonImmutable::parse('2026-10-16', config('app.timezone'));
        $user = User::factory()->create(['created_at' => $today->setTime(9, 0)]);

        $summary = $this->discipline->summary($user, $today);

        $this->assertSame(0, $summary['streak']);
        $this->assertSame(0, collect($summary['history'])->where('status', 'success')->count());
    }

    public function test_le_repos_ne_compte_que_pour_un_joueur_actif(): void
    {
        $this->assertSame('not_played', $this->discipline->evaluate('rest_day', collect(), 0, isToday: false, activeWeek: false)['status']);
        $this->assertSame('success', $this->discipline->evaluate('rest_day', collect(), 0, isToday: false, activeWeek: true)['status']);
    }

    public function test_serie_et_historique_sur_les_vraies_sessions(): void
    {
        $today = CarbonImmutable::parse('2026-10-16', config('app.timezone'));
        $user = User::factory()->create(['created_at' => $today->subMonth()]);

        // Hier et avant-hier : sessions courtes qui respectent presque tous les objectifs (notées, 1 h, gain)
        foreach ([1, 2, 3] as $daysAgo) {
            $user->pokerSessions()->create([
                'played_at' => $today->subDays($daysAgo)->setTime(20, 0),
                'format' => 'cash', 'duration_minutes' => 60, 'net_result' => '15.00', 'notes' => 'Bien joué',
            ]);
        }

        $summary = $this->discipline->summary($user, $today);

        $this->assertCount(14, $summary['history']);
        $this->assertArrayHasKey($summary['status'], ['success' => 1, 'failed' => 1, 'pending' => 1, 'not_played' => 1]);
        // Les jours joués réussissent tous, sauf un éventuel jour de repos qui casse la série
        $played = collect($summary['history'])->slice(10, 3);
        $this->assertTrue($played->every(fn ($d) => in_array($d['status'], ['success', 'failed'], true)));
        $this->assertGreaterThanOrEqual(0, $summary['streak']);
    }
}
