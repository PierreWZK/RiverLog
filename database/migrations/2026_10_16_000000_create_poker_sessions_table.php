<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une session de poker : à qui, quand, quel format, combien de temps, quel résultat.
     */
    public function up(): void
    {
        Schema::create('poker_sessions', function (Blueprint $table) {
            $table->id();
            // Chaque session appartient à un compte ; supprimer le compte supprime ses sessions
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('played_at'); // tri chronologique et filtre par période
            $table->string('format', 10); // valeur de App\Enums\PokerFormat
            $table->unsignedSmallInteger('duration_minutes');
            $table->decimal('net_result', 10, 2); // signé : une perte de 50 € vaut -50.00
            $table->text('notes')->nullable();
            $table->timestamps();

            // Toutes les requêtes portent sur les sessions d'un seul compte
            $table->index(['user_id', 'played_at']);
            $table->index(['user_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poker_sessions');
    }
};
