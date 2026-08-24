<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vide `stays.caution_xof` (F5.8) : la caution reste réservée aux locations
 * au mois (`properties.caution_xof`) — le formulaire de bien ne propose plus
 * de la saisir pour une nuitée, cette migration nettoie les valeurs déjà
 * enregistrées (démo ou dépôts antérieurs à ce choix) pour que plus aucune
 * fiche nuitée n'affiche de caution.
 *
 * ⚠️ Ne touche PAS `bookings.caution_xof` (copié à la réservation, pour les
 * réservations déjà en cours dont le sort reste à trancher côté back-office)
 * ni `vehicles.caution_xof` (mobilité, hors sujet).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stays')->where('caution_xof', '>', 0)->update(['caution_xof' => 0]);
    }

    public function down(): void
    {
        // Irréversible par nature (les montants d'origine ne sont pas conservés).
    }
};
