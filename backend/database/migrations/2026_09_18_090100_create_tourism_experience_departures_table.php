<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `tourism_experience_departures` (F21) — les dates de départ d'un
 * circuit, chacune avec ses propres places.
 *
 * Une réservation (`bookings`, polymorphe sur `TourismExperience`) ne
 * référence PAS une ligne de cette table par clé étrangère : elle porte sa
 * propre `start_date`, comme toute réservation du système. Le rapprochement
 * se fait par égalité de date (cf. `ExperienceBookingService`), pas par FK —
 * exactement la même logique que pour la suppression d'une offre (une
 * réservation ne doit jamais pointer vers une ligne qui peut disparaître).
 * Conséquence utile : retirer une date de départ n'a jamais d'effet sur les
 * réservations déjà prises, et la règle qui l'interdit quand des réservations
 * actives existent (`ExperienceDepartureSyncer`) est une protection métier,
 * pas une contrainte d'intégrité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourism_experience_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourism_experience_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->unsignedInteger('seats_total');
            $table->timestamps();

            $table->unique(['tourism_experience_id', 'start_date'], 'experience_departures_unique_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourism_experience_departures');
    }
};
