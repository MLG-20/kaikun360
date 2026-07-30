<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nature du règlement sur `payments` : acompte / solde / intégral (F7.3.h).
 *
 * Dernière ligne non couverte du module *Paiements* du CDC §6. La table acceptait
 * déjà plusieurs règlements par réservation (sa migration d'origine cite « acompte,
 * solde »), mais aucune colonne ne les distinguait : impossible de dire, devant un
 * paiement de 50 000 F sur une réservation de 180 000 F, s'il s'agissait d'un
 * acompte ou d'une erreur.
 *
 * Défaut `integral` : tous les règlements existants sont des paiements complets,
 * puisque le paiement partiel n'était pas possible avant cette tranche. La colonne
 * est donc juste sur l'historique, sans reprise de données.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind')->default('integral')->after('commission_xof')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
