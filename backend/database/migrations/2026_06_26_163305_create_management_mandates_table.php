<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `management_mandates` — mandats de gestion locative (module Manage, B4).
 *
 * Un mandat lie un bien (et son propriétaire) à Kaikun, qui en assure la gestion
 * locative en échange d'une commission. Il porte la durée et le statut du mandat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_mandates', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Bien géré + son propriétaire (dénormalisé pour des requêtes simples).
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Taux de commission Kaikun (pourcentage, ex. 10.00 = 10 %).
            $table->decimal('commission_rate', 5, 2)->default(0);

            // Durée du mandat.
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Statut (cf. enum MandateStatus).
            $table->string('status')->default('en_attente')->index();

            // Conditions particulières (texte libre).
            $table->text('terms')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_mandates');
    }
};
