<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille de l'espace client (F11.5, 2ᵉ lot) — les notifications et les fils.
 *
 * Demande explicite de l'utilisateur : « les messages et notifications déjà vus
 * ou lus, on doit pouvoir les mettre à la corbeille selon la guise du client ».
 * Ce sont, avec les demandes et les réservations, les quatre listes qui
 * s'allongent toutes seules dans un espace client — les seules qu'on subit.
 *
 * ⚠️ **La colonne ne se pose PAS au même endroit dans les deux cas, et c'est le
 * piège de ce lot :**
 *
 *   - une **notification** n'a qu'un destinataire (`notifiable_id`) : la colonne
 *     va sur la ligne elle-même ;
 *   - un **fil de discussion** en a plusieurs, et il est supervisé par le
 *     support. La colonne va donc sur le **pivot** `conversation_user` : le
 *     client range le fil de SA liste, l'agent le garde intégralement dans la
 *     sienne. Poser `hidden_at` sur `conversations` aurait fait disparaître le
 *     fil de l'écran de l'agent parce que le client a fait le ménage — soit
 *     exactement l'accident que toute la tranche cherche à éviter.
 *
 * ⚠️ **`notifications.id` est un UUID**, pas un entier : la route de
 * restauration `me/trash/{type}/{id}/restore` a dû perdre sa contrainte
 * `whereNumber` pour l'accepter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('read_at');
            $table->index('hidden_at');
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            // Voisine de `last_read_at` : les deux colonnes disent « ce que CE
            // participant a fait de ce fil », et rien du fil lui-même.
            $table->timestamp('hidden_at')->nullable()->after('last_read_at');
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'conversation_user'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // L'index se retire AVANT la colonne (MySQL).
                $blueprint->dropIndex(['hidden_at']);
                $blueprint->dropColumn('hidden_at');
            });
        }
    }
};
