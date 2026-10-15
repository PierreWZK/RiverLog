<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// Page vitrine : présente l'outil en cours de développement
Route::get('/', LandingController::class)->name('landing');
