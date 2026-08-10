<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille des espaces utilisateurs (F11.4) — effacement DIFFÉRÉ des annonces.
 *
 * Jusqu'ici la plateforme ne connaissait aucun effacement différé : pas une
 * seule colonne `deleted_at`, pas un seul modèle en `SoftDeletes`. Supprimer
 * une annonce l'effaçait pour de bon, sans retour possible — d'où cette
 * tranche : on range sans détruire.
 *
 * ⚠️ **Les cinq tables retenues sont celles qui REMPLISSENT LES ONGLETS** d'un
 * espace : ce sont elles qu'on veut alléger. Les pièces jointes (photos,
 * documents, certifications, jalons) restent en suppression définitive —
 * décision produit : une corbeille qui avale aussi les petites pièces devient
 * elle-même un fouillis à trier.
 *
 * ⚠️ **La colonne est INDEXÉE, et ce n'est pas une précaution de confort.**
 * `SoftDeletes` ajoute un `where deleted_at is null` à *toutes* les requêtes de
 * ces tables — y compris le catalogue public, qui est l'écran le plus consulté
 * du site. Sans index, chaque recherche paierait ce filtre par un parcours
 * complet de la table.
 */
return new class extends Migration
{
    /**
     * Les tables qui gagnent une corbeille. L'ordre n'a pas d'importance :
     * aucune contrainte ne lie ces colonnes entre elles.
     */
    private const TABLES = [
        'properties',           // biens immobiliers (espace propriétaire)
        'vehicles',             // véhicules (espace prestataire)
        'mobility_services',    // services de mobilité (espace prestataire)
        'tourism_experiences',  // expériences touristiques (espace prestataire)
        'stays',                // offres de nuitée (espace propriétaire)
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
                $blueprint->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // ⚠️ L'index se retire AVANT la colonne : MySQL refuse de
                // supprimer une colonne encore portée par un index.
                $blueprint->dropIndex(['deleted_at']);
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
