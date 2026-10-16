<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HandImportController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PokerSessionController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

// Page vitrine : présente l'outil
Route::get('/', LandingController::class)->name('landing');

// Exemple public, en lecture seule (compte de démonstration)
Route::get('/demo', [DashboardController::class, 'demo'])->name('demo');
Route::get('/demo/tournaments', [TournamentController::class, 'demoIndex'])->name('demo.tournaments.index');
Route::get('/demo/tournaments/{tournament}', [TournamentController::class, 'demoShow'])->name('demo.tournaments.show');

// Comptes : inscription et connexion, réservées aux visiteurs non connectés
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Espace personnel : chaque compte ne voit et ne modifie que ses propres données
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Dashboard : statistiques, courbe et liste des sessions (filtrables via l'URL)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Sessions : /sessions/create, /sessions/{session}/edit…
    Route::resource('sessions', PokerSessionController::class)->except(['index', 'show']);

    // Import d'historiques de mains et tournois importés
    Route::resource('imports', HandImportController::class)->only(['index', 'store', 'destroy']);
    Route::resource('tournaments', TournamentController::class)->only(['index', 'show']);
});
