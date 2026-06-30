<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute à `bookings` la commission plateforme et le statut de caution (B7.4).
 *
 * `commission_xof` : commission Kaikun calculée à la réservation (mobilité, puis
 * généralisée). `caution_status` : suivi de la caution (retenue/restituée/perdue),
 * utilisé par les locations de véhicule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_xof')->nullable()->after('amount_xof');
            $table->string('caution_status')->nullable()->after('caution_xof');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['commission_xof', 'caution_status']);
        });
    }
};
