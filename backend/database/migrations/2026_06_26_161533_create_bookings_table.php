<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `bookings` — réservations (couche transversale, introduite en B3.3).
 *
 * Relation POLYMORPHE `bookable` : une réservation peut porter sur une nuitée
 * (Stay), un véhicule (Vehicle), une expérience (Experience)… Cette table sera
 * enrichie en phase B11 (couche Requests/Quotes/Bookings). Le statut s'appuie
 * sur l'enum App\Enums\BookingStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Le client qui réserve.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Cible polymorphe de la réservation (Stay, Vehicle, Experience…).
            $table->morphs('bookable');

            // Période réservée (pour les nuitées : start = arrivée, end = départ/exclusif).
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('guests')->nullable();

            // Montants (en XOF).
            $table->unsignedBigInteger('amount_xof')->default(0);
            $table->unsignedBigInteger('caution_xof')->default(0);

            // Statut de réservation (distinct du statut de paiement — cf. B11/B14).
            $table->string('status')->default('en_attente')->index();

            $table->timestamps();

            // Recherche rapide des réservations d'une cible sur une période.
            $table->index(['bookable_type', 'bookable_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
