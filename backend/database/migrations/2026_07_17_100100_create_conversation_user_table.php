<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot `conversation_user` — participants d'une conversation (phase F3.7).
 *
 * Relation N–N entre `conversations` et `users`. Chaque ligne porte le
 * `last_read_at` PROPRE au participant : c'est lui qui permet de calculer les
 * messages non lus (tout message postérieur à `last_read_at`, et non émis par
 * le participant, est « non lu »). La contrainte d'unicité empêche d'ajouter
 * deux fois le même utilisateur au même fil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Dernière fois que CE participant a ouvert le fil. Null = jamais lu.
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
    }
};
