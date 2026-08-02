<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `quotes.agent_id` — l'agent qui a composé le devis (F8.11).
 *
 * POURQUOI CETTE COLONNE ?
 * ------------------------
 * Un devis sur-mesure n'est pas un article de catalogue : le client n'achète pas
 * une prestation, il accorde sa confiance à quelqu'un. Jusqu'ici la table ne
 * gardait AUCUNE trace de l'auteur du chiffrage — le client recevait un montant
 * tombé d'une plateforme anonyme, et personne n'était prévenu quand il
 * l'acceptait.
 *
 * Cette colonne sert donc deux usages, tous deux visibles par l'utilisateur :
 *   1. afficher un interlocuteur NOMMÉ au client (« votre projet est suivi
 *      par X »), sur l'écran du devis comme dans l'e-mail ;
 *   2. notifier CET agent-là — et pas toute l'équipe — quand son devis est
 *      accepté ou refusé.
 *
 * Nullable : les devis créés avant cette phase (et ceux du seeder) n'ont pas
 * d'auteur connu. Les écrans doivent donc tolérer l'absence d'agent.
 * `nullOnDelete` : le départ d'un agent ne doit jamais effacer un devis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('agent_id')
                ->nullable()
                ->after('request_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });
    }
};
