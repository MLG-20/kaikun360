<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Localisation Google Maps des annonces (F5.10).
 *
 * Le propriétaire/prestataire colle un lien Google Maps (copié depuis
 * l'application) plutôt que de saisir des coordonnées ou de pointer un lieu
 * sur une carte interactive : la plateforme appartenant à un client, aucune
 * clé API Google Maps facturable n'est disponible pour ce projet.
 *
 * `properties` a déjà `latitude`/`longitude` (jamais alimentés par aucun
 * formulaire) : `maps_link` s'y ajoute sans les toucher. Les trois autres
 * tables n'avaient AUCUN champ de localisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['properties', 'vehicles', 'mobility_services', 'tourism_experiences'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('maps_link', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['properties', 'vehicles', 'mobility_services', 'tourism_experiences'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('maps_link');
            });
        }
    }
};
