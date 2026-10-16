<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Crée le compte de démonstration, ses sessions et ses tournois importés (affichés sur /demo).
     * Les vrais comptes se créent depuis la page d'inscription.
     */
    public function run(): void
    {
        $this->call([
            PokerSessionSeeder::class, // compte de démo et sessions saisies à la main
            DemoSpinSeeder::class,     // Spins fictifs importés (page Tournois de la démo)
        ]);
    }
}
