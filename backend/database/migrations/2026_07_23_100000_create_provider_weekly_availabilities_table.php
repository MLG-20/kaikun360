<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `provider_weekly_availabilities` — planning hebdomadaire récurrent d'un
 * prestataire (module Pro, F5.4).
 *
 * Une ligne par jour de la semaine (`weekday` 0 = lundi … 6 = dimanche). Le jour
 * peut être ouvert (`is_open`) avec une plage horaire, ou fermé. Ce planning
 * décrit les **horaires habituels** ; les absences ponctuelles (congés) sont
 * gérées à part dans `provider_unavailabilities` et priment sur ce planning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_weekly_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            // 0 = lundi … 6 = dimanche (semaine française).
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_open')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->timestamps();

            // Un seul enregistrement par prestataire et par jour de semaine.
            $table->unique(['provider_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_weekly_availabilities');
    }
};
