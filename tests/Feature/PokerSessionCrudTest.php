<?php

namespace Tests\Feature;

use App\Enums\PokerFormat;
use App\Models\PokerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PokerSessionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'played_at' => '2026-10-10T20:30',
            'format' => 'mtt',
            'duration_minutes' => 180,
            'net_result' => '-12,50',
            'notes' => 'Bulle ratée.',
        ], $overrides);
    }

    public function test_une_session_est_ajoutee(): void
    {
        $this->post('/sessions', $this->validData())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Session ajoutée.');

        $session = PokerSession::sole();
        $this->assertTrue($session->user->is($this->user)); // rattachée au compte connecté
        $this->assertSame(PokerFormat::Mtt, $session->format);
        $this->assertSame('-12.50', $session->net_result); // virgule française acceptée
        $this->assertSame('2026-10-10 20:30:00', $session->played_at->toDateTimeString());
    }

    public function test_le_resultat_accepte_un_signe_plus_et_des_espaces(): void
    {
        $this->post('/sessions', $this->validData(['net_result' => '+ 1 250,5']));

        $this->assertSame('1250.50', PokerSession::sole()->net_result);
    }

    public function test_user_id_envoye_dans_le_formulaire_est_ignore(): void
    {
        $other = User::factory()->create();

        $this->post('/sessions', $this->validData(['user_id' => $other->id]));

        $this->assertTrue(PokerSession::sole()->user->is($this->user));
    }

    public function test_une_session_est_modifiee(): void
    {
        $session = PokerSession::factory()->for($this->user)->create();

        $this->put("/sessions/{$session->id}", $this->validData(['net_result' => '0', 'notes' => null]))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Session modifiée.');

        $session->refresh();
        $this->assertSame('0.00', $session->net_result);
        $this->assertNull($session->notes);
    }

    public function test_une_session_est_supprimee(): void
    {
        $session = PokerSession::factory()->for($this->user)->create();

        $this->delete("/sessions/{$session->id}")
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Session supprimée.');

        $this->assertModelMissing($session);
    }

    public function test_les_donnees_invalides_sont_refusees(): void
    {
        $this->from('/sessions/create')
            ->post('/sessions', [
                'played_at' => now()->addDay()->format('Y-m-d\TH:i'),
                'format' => 'poker-ouvert',
                'duration_minutes' => 0,
                'net_result' => '12,345',
            ])
            ->assertRedirect('/sessions/create')
            ->assertSessionHasErrors([
                'played_at' => 'La session ne peut pas être dans le futur.',
                'format' => 'Ce format n\'existe pas.',
                'duration_minutes' => 'La durée doit être d\'au moins 1 minute.',
                'net_result' => 'Le résultat doit être un montant, ex. 42,50 ou -20.',
            ]);

        $this->assertDatabaseEmpty('poker_sessions');
    }

    public function test_une_session_inconnue_renvoie_404(): void
    {
        $this->delete('/sessions/999')->assertNotFound();
    }

    public function test_impossible_de_toucher_la_session_d_un_autre_compte(): void
    {
        $other = PokerSession::factory()->create(['net_result' => '50.00']);

        // 404 et non 403 : on ne révèle même pas que la session existe
        $this->get("/sessions/{$other->id}/edit")->assertNotFound();
        $this->put("/sessions/{$other->id}", $this->validData())->assertNotFound();
        $this->delete("/sessions/{$other->id}")->assertNotFound();

        $this->assertSame('50.00', $other->fresh()->net_result);
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        auth()->logout();
        $session = PokerSession::factory()->create();

        $this->get('/sessions/create')->assertRedirect('/login');
        $this->post('/sessions', $this->validData())->assertRedirect('/login');
        $this->delete("/sessions/{$session->id}")->assertRedirect('/login');

        $this->assertModelExists($session);
    }
}
