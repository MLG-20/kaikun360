<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `provider_certifications` — documents de certification (module Pro, B10.1).
 *
 * Justificatifs fournis par le prestataire à l'inscription (diplôme, agrément,
 * assurance…). Le fichier est stocké sur disque privé ; un agent peut marquer la
 * certification comme vérifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();

            $table->string('name');
            $table->string('issuer')->nullable(); // organisme émetteur
            $table->string('file_path')->nullable(); // justificatif (disque privé)
            $table->boolean('verified')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_certifications');
    }
};
