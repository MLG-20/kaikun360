<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `conversations` — fil de discussion de la messagerie (phase F3.7).
 *
 * Socle GÉNÉRIQUE et RÉUTILISABLE : une conversation regroupe plusieurs
 * participants (table pivot `conversation_user`) et une suite de messages
 * (`messages`). Elle est volontairement DÉCOUPLÉE des rôles : n'importe quels
 * utilisateurs (client ↔ agent Kaikun, client ↔ propriétaire/prestataire…)
 * peuvent y figurer. Les espaces pro (F4/F5/F6) réutiliseront le même socle.
 *
 * `context_type` / `context_id` forment un lien polymorphe FACULTATIF vers la
 * ressource à l'origine de l'échange (une demande, une réservation, un bien…),
 * afin d'afficher « À propos de votre demande #REF » sans coupler la table aux
 * modèles métier. `last_message_at` est dénormalisé pour trier les fils par
 * activité récente sans jointure coûteuse sur `messages`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Sujet libre facultatif (ex. « Question sur la villa Almadies »).
            $table->string('subject')->nullable();

            // Lien polymorphe facultatif vers la ressource contextuelle
            // (App\Models\ServiceRequest, App\Models\Booking, Property…).
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            // Horodatage du dernier message (dénormalisé) : tri des fils par
            // activité. Nullable tant qu'aucun message n'a été posté.
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            // Retrouver rapidement les conversations d'une ressource donnée.
            $table->index(['context_type', 'context_id']);
            // Tri des fils les plus récents en tête.
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
