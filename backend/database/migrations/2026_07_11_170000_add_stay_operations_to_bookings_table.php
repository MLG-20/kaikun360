<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exploitation des nuitées côté back-office (B13.6).
 *
 * Ajoute à la table transversale `bookings` les données d'exploitation propres
 * aux séjours : horodatages de check-in / check-out et statut de ménage. Ces
 * colonnes ne concernent que les réservations de type Stay (nullable ailleurs),
 * dans le même esprit que `commission_xof`/`caution_status` ajoutés en B7.4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('status');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->string('housekeeping_status')->nullable()->after('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at', 'housekeeping_status']);
        });
    }
};
