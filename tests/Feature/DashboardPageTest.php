<?php

namespace Tests\Feature;

use App\Models\PokerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_le_dashboard_vide_invite_a_ajouter_une_session(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Aucune session pour l\'instant', false)
            ->assertSee('Voir un exemple')
            ->assertDontSee('profit-chart');
    }

    public function test_le_dashboard_affiche_stats_courbe_et_tableau(): void
    {
        PokerSession::factory()->for($this->user)->create(['played_at' => '2026-09-01 20:00', 'format' => 'cash', 'duration_minutes' => 150, 'net_result' => '42.50']);
        PokerSession::factory()->for($this->user)->create(['played_at' => '2026-09-02 20:00', 'format' => 'mtt', 'duration_minutes' => 90, 'net_result' => '-20.00']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(["+22,50\u{00A0}€", "+5,63\u{00A0}€/h", '2', "4\u{00A0}h"])
            ->assertSee('profit-chart')
            // Les plus récentes en premier
            ->assertSeeInOrder(['02/09/2026', '01/09/2026']);
    }

    public function test_le_dashboard_n_affiche_que_les_sessions_du_compte(): void
    {
        PokerSession::factory()->for($this->user)->create(['net_result' => '10.00']);
        PokerSession::factory()->create(['net_result' => '-999.00', 'notes' => 'Session d\'un autre compte']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee("+10,00\u{00A0}€", false)
            ->assertDontSee('999')
            ->assertDontSee('Session d\'un autre compte', false);
    }

    public function test_les_filtres_s_appliquent_et_restent_dans_le_formulaire(): void
    {
        PokerSession::factory()->for($this->user)->create(['format' => 'cash', 'net_result' => '10.00']);
        PokerSession::factory()->for($this->user)->create(['format' => 'mtt', 'net_result' => '-30.00']);

        $this->get('/dashboard?format=mtt')
            ->assertOk()
            ->assertSee("\u{2212}30,00\u{00A0}€", false)
            ->assertDontSee("+10,00\u{00A0}€", false)
            ->assertSee('<option value="mtt" selected>', false)
            ->assertSee('Réinitialiser');
    }

    public function test_aucun_resultat_avec_les_filtres(): void
    {
        PokerSession::factory()->for($this->user)->create(['format' => 'cash']);

        $this->get('/dashboard?format=sng')
            ->assertOk()
            ->assertSee('Aucune session ne correspond à ces filtres', false)
            ->assertSee('—'); // taux horaire sans temps de jeu
    }

    public function test_les_formulaires_s_affichent(): void
    {
        $session = PokerSession::factory()->for($this->user)->create(['net_result' => '-12.50']);

        $this->get('/sessions/create')->assertOk()->assertSee('Nouvelle session');
        $this->get("/sessions/{$session->id}/edit")
            ->assertOk()
            ->assertSee('value="-12,50"', false); // montant affiché à la française
    }

    public function test_les_erreurs_sont_affichees_en_francais(): void
    {
        $this->from('/sessions/create')
            ->followingRedirects()
            ->post('/sessions', ['format' => 'cash'])
            ->assertSee('Certains champs sont à corriger.', false)
            ->assertSee('Indique la durée de la session.', false);
    }

    public function test_le_dashboard_demande_une_connexion(): void
    {
        auth()->logout();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_la_demo_est_publique_et_en_lecture_seule(): void
    {
        auth()->logout();
        $demo = User::factory()->create(['email' => User::DEMO_EMAIL]);
        $session = PokerSession::factory()->for($demo)->create(['net_result' => '42.00']);

        $this->get('/demo?format='.$session->format->value)
            ->assertOk()
            ->assertSee('Exemple de dashboard')
            ->assertSee("+42,00\u{00A0}€", false)
            ->assertSee('action="'.route('demo').'"', false) // les filtres restent sur la démo
            ->assertDontSee('/sessions/'.$session->id.'/edit')
            ->assertDontSee('_method');
    }

    public function test_la_demo_n_existe_pas_sans_le_seeder(): void
    {
        $this->get('/demo')->assertNotFound();
    }
}
