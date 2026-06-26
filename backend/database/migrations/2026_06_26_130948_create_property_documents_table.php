<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `property_documents` — pièces justificatives d'un bien immobilier
 * (titre foncier, bail, plan...). Même principe que `user_documents` :
 * fichier sur disque privé, accès par URL signée, validation par un agent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Nature du document (titre_foncier, bail, plan, autre...).
            $table->string('type');

            // Emplacement du fichier (disque privé + chemin).
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            // Statut de validation par un agent.
            $table->string('validation_status')->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
