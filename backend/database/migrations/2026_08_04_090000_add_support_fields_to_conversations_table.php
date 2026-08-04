<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie « support pivot » (F8.12) — deux colonnes sur `conversations`.
 *
 * Le socle de F3.7 savait déjà porter N participants et des messages ; ce qui
 * lui manquait, c'est le CADRE DE TRAVAIL de l'équipe :
 *
 *   - `assigned_agent_id` : l'agent RESPONSABLE du fil. Il est aussi participant
 *     (pivot `conversation_user`), mais participer ne veut pas dire répondre :
 *     sans responsable nommé, une boîte partagée entre plusieurs comptes staff
 *     produit l'inverse de ce qu'on cherche — chacun croit que l'autre a
 *     répondu. C'est également le nom que le client voit en face de lui (même
 *     principe que l'interlocuteur du devis, F8.11).
 *     `nullOnDelete` : la suppression d'un compte agent ne doit pas emporter la
 *     conversation du client, elle la remet simplement dans la file.
 *
 *   - `closed_at` : un fil clos sort de la file de traitement sans disparaître.
 *     Sans lui, la boîte de réception ne fait que croître et l'agent ne sait
 *     plus distinguer ce qui attend une réponse de ce qui est réglé. Le client,
 *     lui, garde l'accès en lecture (et rouvre le fil en écrivant à nouveau).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_agent_id')
                ->nullable()
                ->after('subject')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('closed_at')->nullable()->after('last_message_at');

            // La file d'un agent : « mes fils ouverts, les plus récents d'abord ».
            $table->index(['assigned_agent_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['assigned_agent_id', 'closed_at']);
            $table->dropConstrainedForeignId('assigned_agent_id');
            $table->dropColumn('closed_at');
        });
    }
};
