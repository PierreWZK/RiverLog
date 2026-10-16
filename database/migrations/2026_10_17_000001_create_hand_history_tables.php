<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historiques de mains importés (cahier des charges, section 8.4).
 * Montants en euros en décimal, jetons en entiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Les sessions peuvent maintenant être générées par un import
        Schema::table('poker_sessions', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('user_id'); // manual, import
        });

        // Un fichier importé et son résumé
        Schema::create('hand_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('stored_path')->nullable();
            $table->char('sha256', 64);
            $table->string('site', 30)->nullable();
            $table->unsignedInteger('hands_found')->default(0);
            $table->unsignedInteger('hands_imported')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('tournaments_touched')->default(0);
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sha256']); // le même fichier n'est jamais importé deux fois
        });

        // Un tournoi : ses chiffres sont recalculés à partir de toutes ses mains
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poker_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site', 30);
            $table->string('external_id', 64); // Game ID
            $table->string('game_name');
            $table->string('game_mode', 30);
            $table->decimal('buy_in', 10, 2);
            $table->decimal('prize_pool', 10, 2)->nullable();
            $table->unsignedSmallInteger('multiplier')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->unsignedTinyInteger('players_count')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('hands_count')->default(0);
            $table->unsignedTinyInteger('finish_position')->nullable();
            $table->decimal('winnings', 10, 2)->default(0);
            $table->decimal('net_result', 10, 2)->nullable(); // null tant que le tournoi est incomplet
            $table->boolean('is_complete')->default(false);
            $table->boolean('reached_heads_up')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'site', 'external_id']);
            $table->index(['user_id', 'started_at']);
        });

        // Une main, avec les chiffres du Hero déjà calculés
        Schema::create('hands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hand_import_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64); // Hand ID
            $table->string('table_id', 64)->nullable();
            $table->dateTime('played_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('small_blind');
            $table->unsignedInteger('big_blind');
            $table->unsignedTinyInteger('players_count');
            $table->string('hero_position', 5)->nullable();
            $table->string('hero_cards', 5)->nullable();
            $table->unsignedInteger('hero_stack');
            $table->decimal('hero_stack_bb', 8, 2);
            $table->string('board', 14)->nullable();
            $table->unsignedInteger('total_pot');
            $table->unsignedInteger('hero_invested');
            $table->unsignedInteger('hero_won');
            $table->integer('hero_net');
            $table->boolean('went_to_showdown');
            $table->boolean('hero_all_in');
            $table->unsignedTinyInteger('hero_finish_position')->nullable();
            $table->decimal('hero_prize', 10, 2)->nullable();
            $table->text('raw_text');
            $table->unsignedSmallInteger('parser_version');
            $table->timestamps();

            $table->unique(['user_id', 'external_id']); // une main n'est jamais comptée deux fois
            $table->index(['tournament_id', 'played_at']);
        });

        Schema::create('hand_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->string('name', 60);
            $table->unsignedInteger('stack');
            $table->string('position', 5)->nullable();
            $table->boolean('is_hero');
            $table->string('cards', 5)->nullable();
            $table->string('shown_rank', 30)->nullable();
            $table->unsignedInteger('invested');
            $table->unsignedInteger('won');
            $table->unsignedTinyInteger('finish_position')->nullable();
            $table->decimal('prize', 10, 2)->nullable();
        });

        Schema::create('hand_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hand_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('street', 7);
            $table->string('player', 60);
            $table->string('type', 10);
            $table->unsignedInteger('amount')->nullable();
            $table->unsignedInteger('added');
            $table->unsignedInteger('street_total');
            $table->boolean('is_all_in');
            $table->dateTime('acted_at');
            $table->unsignedSmallInteger('decision_seconds')->nullable();

            $table->index(['hand_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hand_actions');
        Schema::dropIfExists('hand_players');
        Schema::dropIfExists('hands');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('hand_imports');

        Schema::table('poker_sessions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
