<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B17.1 — Durcissement & performance : index de recherche sur les catalogues.
 *
 * Objectif : accélérer les endpoints de catalogue/recherche les plus consultés
 * en indexant les colonnes réellement filtrées ou triées par les controllers,
 * et qui n'étaient PAS déjà couvertes (par un index explicite, un index de clé
 * étrangère `constrained()`, ou un index composite existant).
 *
 * Déjà indexé en amont (rien à faire ici) :
 *   - properties : status, type, verification_level, tourist_zone, price_xof,
 *     composite (status, type), + FK region/department/commune/owner.
 *   - bookings   : status, user_id, composite (bookable_type, bookable_id,
 *     start_date, end_date).
 *   - vehicles / mobility_services / tourism_experiences : type, status,
 *     provider_id, departure_at (mobility).
 *
 * Volontairement NON indexé (bénéfice quasi nul, très basse cardinalité) :
 *   - vehicles.capacity / vehicles.has_driver, stays.capacity.
 *   - Les recherches plein texte `LIKE '%terme%'` (title, brand, model…) :
 *     un index B-tree n'aide pas un joker en tête ; hors périmètre B17.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nuitées : filtre par fourchette de prix + tri par prix.
        Schema::table('stays', function (Blueprint $table) {
            $table->index('price_per_night_xof');
        });

        // Véhicules : filtre prix max + tri par prix.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->index('price_per_day_xof');
        });

        // Services de mobilité : recherche par ville de départ / destination (=).
        Schema::table('mobility_services', function (Blueprint $table) {
            $table->index('departure');
            $table->index('destination');
        });

        // Expériences : filtre destination (=) + fourchette/tri de prix.
        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->index('destination');
            $table->index('price_xof');
        });

        // Paiements : filtre de statut dans la supervision back-office.
        Schema::table('payments', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->dropIndex(['price_per_night_xof']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['price_per_day_xof']);
        });

        Schema::table('mobility_services', function (Blueprint $table) {
            $table->dropIndex(['departure']);
            $table->dropIndex(['destination']);
        });

        Schema::table('tourism_experiences', function (Blueprint $table) {
            $table->dropIndex(['destination']);
            $table->dropIndex(['price_xof']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
