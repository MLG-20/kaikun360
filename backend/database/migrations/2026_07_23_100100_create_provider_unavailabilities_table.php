<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `provider_unavailabilities` — périodes d'indisponibilité ponctuelles d'un
 * prestataire (module Pro, F5.4).
 *
 * Congés / absences sur une plage de dates (`start_date` → `end_date`, incluses),
 * avec un motif facultatif. Ces périodes **priment sur le planning hebdomadaire**
 * (`provider_weekly_availabilities`) : le prestataire est indisponible sur ces
 * dates même si le jour est ouvert dans son planning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_unavailabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['provider_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_unavailabilities');
    }
};
