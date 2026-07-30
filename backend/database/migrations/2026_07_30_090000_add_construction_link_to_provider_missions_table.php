<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relie une mission prestataire à un chantier de construction (F7.3.e3).
 *
 * CDC §6 « Construction » exige des « prestataires BTP » : à partir d'un dossier,
 * le back-office affecte des prestataires validés par corps d'état (maçon,
 * électricien, plombier…). Même parti pris qu'en F7.2.h pour le team building :
 * on ne duplique pas la notion d'affectation, on rattache une **mission Pro**
 * existante — cycle affectée → acceptée → … → terminée, commission figée, visible
 * dans les revenus du prestataire.
 *
 * ⚠️ La colonne `category`, ajoutée pour le team building, est **réutilisée telle
 * quelle** : c'est une chaîne libre qui porte la brique de pack pour une mission
 * TB et le **lot BTP** (`ConstructionLot`) pour une mission de chantier. Les deux
 * vocabulaires ne se croisent jamais puisqu'ils sont distingués par la clé
 * étrangère renseignée. Ajouter une seconde colonne de catégorie aurait laissé
 * l'une des deux vide sur chaque ligne.
 *
 * Colonne NULLABLE → aucune mission existante n'est impactée : purement additif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            $table->foreignId('construction_request_id')
                ->nullable()
                ->after('team_building_request_id')
                ->constrained('construction_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('provider_missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('construction_request_id');
        });
    }
};
