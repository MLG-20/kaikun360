<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vide `vehicles.caution_xof` (F5.8) : même décision que pour les nuitées
 * (cf. `2026_08_24_150000_clear_caution_xof_on_stays_table`) — la caution
 * reste réservée à la gestion locative (`properties.caution_xof`). Le champ
 * avait déjà été retiré du formulaire prestataire le 2026-08-23 ; cette
 * migration nettoie les valeurs déjà enregistrées (démo ou dépôts antérieurs).
 *
 * ⚠️ Ne touche PAS `bookings.caution_xof` (copié à la réservation, pour les
 * réservations déjà en cours dont le sort reste à trancher côté back-office).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vehicles')->where('caution_xof', '>', 0)->update(['caution_xof' => 0]);
    }

    public function down(): void
    {
        // Irréversible par nature (les montants d'origine ne sont pas conservés).
    }
};
