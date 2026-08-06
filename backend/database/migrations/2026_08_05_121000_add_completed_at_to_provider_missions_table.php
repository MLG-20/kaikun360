<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `provider_missions.completed_at` (F8.16.a).
 *
 * ⚠️ **Une mission terminée ne disait pas QUAND elle l'avait été.** La table
 * porte `scheduled_at` (la date prévue, nullable) et le statut, rien d'autre :
 * pour dater l'achèvement il fallait se rabattre sur `updated_at`, que n'importe
 * quelle correction ultérieure — un titre retouché, une note ajoutée — décale.
 *
 * Or le reversement au prestataire devient exigible **au service rendu plus un
 * délai de sûreté** : sans date d'achèvement fiable, ce délai se serait remis à
 * courir à chaque modification de la mission, repoussant indéfiniment le
 * paiement du prestataire sans que personne ne comprenne pourquoi.
 *
 * Les réservations, elles, n'ont pas besoin de cette colonne : leur fin de
 * service est portée par `end_date` (ou `start_date` pour un départ daté), ce qui
 * vaut mieux qu'un horodatage de traitement — un cron lancé en retard ne doit
 * pas retarder le partenaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
