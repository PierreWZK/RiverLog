<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Résultats des défis quotidiens (« Qui est devant ? », « Le calcul du jour »).
 * Le défi discipline n'est pas stocké : il se calcule à partir des sessions du jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('challenge', 20);
            $table->date('day');
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('max_score');
            $table->json('details'); // réponses et correction, pour réafficher le résultat
            $table->timestamps();

            $table->unique(['user_id', 'challenge', 'day']); // une seule tentative par jour
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_results');
    }
};
