<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute l'horodatage d'annulation à `bookings` (couche transversale, B11.3).
 *
 * Le cahier des charges exige un horodatage d'annulation DISTINCT du statut de
 * paiement. Il est renseigné automatiquement dès qu'une réservation passe à un
 * statut d'annulation (cf. modèle Booking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
