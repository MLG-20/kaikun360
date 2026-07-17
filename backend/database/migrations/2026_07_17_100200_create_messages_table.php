<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `messages` — messages d'une conversation (phase F3.7).
 *
 * Chaque message appartient à une conversation (`conversation_id`) et à son
 * auteur (`sender_id`). Le corps est du texte brut (échappé à l'affichage côté
 * frontend). L'index composite (conversation_id, created_at) sert l'affichage
 * chronologique d'un fil et le calcul des non-lus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Auteur du message. `cascadeOnDelete` : si le compte est supprimé,
            // ses messages le sont aussi (cohérent avec la suppression de compte).
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
