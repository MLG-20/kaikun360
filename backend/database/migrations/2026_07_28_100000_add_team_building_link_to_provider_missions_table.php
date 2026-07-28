<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relie une mission prestataire à une demande de team building (F7.2.h).
 *
 * CDC §6 « Team building » exige l'« affectation prestataires » : à partir d'une
 * demande d'entreprise, le back-office affecte des prestataires validés pour
 * fournir chaque brique du pack (lieu, hébergement, restauration, activité,
 * mobilité, animation). Plutôt que dupliquer la notion d'affectation, on
 * rattache une mission existante (module Pro, cycle affectée→acceptée→…→terminée,
 * commission figée, visible dans les revenus du prestataire) à la demande TB via
 * une clé étrangère facultative + la catégorie de pack concernée.
 *
 * Colonnes NULLABLES → aucune mission « classique » (hors team building) n'est
 * impactée : migration purement additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            // Demande de team building d'origine (null pour une mission ordinaire).
            $table->foreignId('team_building_request_id')
                ->nullable()
                ->after('client_id')
                ->constrained('team_building_requests')
                ->nullOnDelete();

            // Brique du pack couverte par la mission (lieu / hebergement / … ).
            $table->string('category')->nullable()->after('team_building_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_building_request_id');
            $table->dropColumn('category');
        });
    }
};
