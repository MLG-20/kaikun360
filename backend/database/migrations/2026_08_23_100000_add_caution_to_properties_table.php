<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caution demandée par le propriétaire pour une location au mois (F5.8) :
 *   - `caution_xof`     : le montant MENSUEL en FCFA (librement déclaré,
 *                         indépendant du loyer `price_xof`) ;
 *   - `caution_months`  : le nombre de mois de caution demandés.
 *
 * Le montant TOTAL (`caution_xof × caution_months`) est calculé
 * automatiquement (`Property::caution_total_xof`, accesseur — pas de colonne
 * dédiée, pour ne jamais désynchroniser le total des deux valeurs sources).
 *
 * Aucun suivi de statut (retenue/restituée/perdue) — contrairement à la
 * caution des nuitées (`stays.caution_xof`), il n'existe aujourd'hui aucune
 * notion de bail/locataire à laquelle rattacher un tel suivi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->unsignedBigInteger('caution_xof')->nullable()->after('price_xof');
            $table->unsignedTinyInteger('caution_months')->nullable()->after('caution_xof');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['caution_xof', 'caution_months']);
        });
    }
};
