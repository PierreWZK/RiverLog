<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_pages_de_connexion_et_d_inscription_s_affichent(): void
    {
        $this->get('/login')->assertOk()->assertSee('Connexion');
        $this->get('/register')->assertOk()->assertSee('Créer un compte');
    }

    public function test_inscription_puis_connexion_automatique(): void
    {
        $this->post('/register', [
            'name' => 'Pierre',
            'email' => 'Pierre@Exemple.FR',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])->assertRedirect('/dashboard');

        $user = User::sole();
        $this->assertSame('pierre@exemple.fr', $user->email); // adresse mise en minuscules
        $this->assertTrue(Hash::check('motdepasse', $user->password)); // mot de passe haché
        $this->assertAuthenticatedAs($user);
    }

    public function test_inscription_refusee_si_l_adresse_existe_ou_mots_de_passe_differents(): void
    {
        User::factory()->create(['email' => 'pierre@exemple.fr']);

        $this->post('/register', [
            'name' => 'Pierre',
            'email' => 'pierre@exemple.fr',
            'password' => 'motdepasse',
            'password_confirmation' => 'autre-chose',
        ])->assertSessionHasErrors([
            'email' => 'Un compte existe déjà avec cette adresse.',
            'password' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $this->assertGuest();
    }

    public function test_connexion_et_deconnexion(): void
    {
        $user = User::factory()->create(['email' => 'pierre@exemple.fr', 'password' => 'motdepasse']);

        $this->post('/login', ['email' => 'pierre@exemple.fr', 'password' => 'motdepasse'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'pierre@exemple.fr', 'password' => 'motdepasse']);

        $this->post('/login', ['email' => 'pierre@exemple.fr', 'password' => 'faux'])
            ->assertSessionHasErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.']);

        $this->assertGuest();
    }

    public function test_blocage_apres_cinq_essais(): void
    {
        User::factory()->create(['email' => 'pierre@exemple.fr', 'password' => 'motdepasse']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'pierre@exemple.fr', 'password' => 'faux']);
        }

        // Même avec le bon mot de passe, il faut attendre
        $this->post('/login', ['email' => 'pierre@exemple.fr', 'password' => 'motdepasse'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_le_compte_de_demo_ne_permet_pas_de_se_connecter(): void
    {
        User::factory()->create(['email' => User::DEMO_EMAIL, 'password' => 'motdepasse']);

        $this->post('/login', ['email' => User::DEMO_EMAIL, 'password' => 'motdepasse'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_compte_connecte_ne_revoit_pas_la_page_de_connexion(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/login')
            ->assertRedirect('/dashboard');
    }
}
