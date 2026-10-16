<?php

namespace Tests\Feature;

use App\Challenges\EquityQuiz;
use App\Challenges\MathQuiz;
use App\Http\Controllers\ChallengeController;
use App\Models\ChallengeResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function today(): CarbonImmutable
    {
        return ChallengeController::today();
    }

    /** Les bonnes réponses du calcul du jour. */
    private function mathAnswers(): array
    {
        return array_map(fn ($q) => $q['correct'], app(MathQuiz::class)->puzzle($this->today()));
    }

    public function test_le_meme_defi_pour_tout_le_monde_et_un_nouveau_chaque_jour(): void
    {
        $quiz = app(MathQuiz::class);
        $day = CarbonImmutable::parse('2026-10-16');

        $this->assertSame($quiz->puzzle($day), $quiz->puzzle($day));
        $this->assertNotSame($quiz->puzzle($day), $quiz->puzzle($day->addDay()));
        $this->assertCount(5, $quiz->puzzle($day));
    }

    public function test_les_situations_d_equite_sont_ni_evidentes_ni_pile_ou_face(): void
    {
        $puzzle = app(EquityQuiz::class)->puzzle(CarbonImmutable::parse('2026-10-16'));

        $this->assertSame(['Préflop', 'Préflop', 'Flop', 'Flop', 'Turn'], array_column($puzzle, 'street'));
        foreach ($puzzle as $q) {
            $favorite = max($q['equity_a'], 100 - $q['equity_a'] - $q['tie']);
            $this->assertGreaterThanOrEqual(55, $favorite);
            $this->assertLessThanOrEqual(90, max($q['equity_a'], 100 - $q['equity_a']));
        }
    }

    public function test_la_page_ne_revele_pas_les_reponses(): void
    {
        $puzzle = app(MathQuiz::class)->puzzle($this->today());

        $this->get('/defis/calcul')
            ->assertOk()
            ->assertSee(e($puzzle[0]['text']), false)
            ->assertDontSee(e($puzzle[0]['explanation']), false);
    }

    public function test_un_visiteur_joue_une_seule_fois_par_jour(): void
    {
        $this->post('/defis/calcul', ['day' => $this->today()->toDateString(), 'answers' => $this->mathAnswers()])
            ->assertRedirect('/defis/calcul');

        $this->get('/defis/calcul')->assertOk()->assertSee('100<small>/100</small>', false)->assertSee('Créez un compte');

        // Deuxième tentative refusée, le premier résultat reste
        $this->post('/defis/calcul', ['day' => $this->today()->toDateString(), 'answers' => [0, 0, 0, 0, 0]])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'déjà relevé'));
        $this->assertDatabaseEmpty('challenge_results');
    }

    public function test_un_compte_enregistre_son_resultat_et_sa_serie(): void
    {
        $user = User::factory()->create();
        // Déjà joué les deux jours précédents
        foreach ([1, 2] as $daysAgo) {
            $user->challengeResults()->create([
                'challenge' => 'calcul', 'day' => $this->today()->subDays($daysAgo)->toDateString(),
                'score' => 60, 'max_score' => 100, 'details' => [],
            ]);
        }

        $answers = $this->mathAnswers();
        $answers[0] = ($answers[0] + 1) % 2; // une erreur volontaire (réponse 0 ou 1, toujours valide)

        $this->actingAs($user)
            ->post('/defis/calcul', ['day' => $this->today()->toDateString(), 'answers' => $answers])
            ->assertRedirect('/defis/calcul');

        $result = ChallengeResult::where('day', $this->today()->toDateString())->sole();
        $this->assertSame(80, $result->score);
        $this->assertFalse($result->details[0]['graded']['correct']);

        $this->get('/defis/calcul')->assertSee('3 jours d\'affilée', false)->assertSee('meilleur score 80/100');
    }

    public function test_correction_de_l_equite(): void
    {
        $user = User::factory()->create();
        $puzzle = app(EquityQuiz::class)->puzzle($this->today());
        // Estimations exactes (arrondies) : chaque question rapporte presque 20 points
        $answers = array_map(fn ($q) => (int) round($q['equity_a']), $puzzle);

        $this->actingAs($user)->post('/defis/qui-est-devant', ['day' => $this->today()->toDateString(), 'answers' => $answers]);

        $this->assertGreaterThanOrEqual(98, $user->challengeResults()->sole()->score);
    }

    public function test_une_reponse_au_defi_d_hier_n_est_pas_corrigee(): void
    {
        $this->post('/defis/calcul', ['day' => $this->today()->subDay()->toDateString(), 'answers' => [0, 0, 0, 0, 0]])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'changé à minuit'));

        $this->get('/defis/calcul')->assertSee('Valider mes réponses');
    }

    public function test_reponses_incompletes_refusees(): void
    {
        $this->post('/defis/qui-est-devant', ['day' => $this->today()->toDateString(), 'answers' => [50, 50]])
            ->assertSessionHasErrors('answers');
    }

    public function test_pages_des_defis(): void
    {
        $this->get('/defis')->assertOk()->assertSee('Qui est devant ?')->assertSee('Le calcul du jour')->assertSee('Défi discipline');
        $this->get('/defis/discipline')->assertRedirect('/login');
        $this->get('/defis/autre')->assertNotFound();

        $this->actingAs(User::factory()->create())->get('/defis/discipline')->assertOk()->assertSee('Objectif du jour');
    }
}
