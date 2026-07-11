<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `media` — médias polymorphes (couche transversale, B12.1).
 *
 * Un média (image ou vidéo) est rattaché à n'importe quelle ressource
 * (`mediable` : bien immobilier, véhicule, expérience…). On distingue l'image
 * principale (`is_primary`) de la galerie secondaire, et on ordonne via
 * `position`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Cible polymorphe (Property, Vehicle, TourismExperience…).
            $table->morphs('mediable');

            // Auteur du dépôt (propriétaire du bien, agent…).
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('image');            // enum MediaType

            // Image : chemin de stockage + disque ; vidéo : URL externe.
            $table->string('disk')->default('public');
            $table->string('path')->nullable();
            $table->string('url')->nullable();

            // Métadonnées utiles (validation, affichage).
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Image principale vs galerie, et ordre d'affichage.
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->string('status')->default('actif')->index();  // enum MediaStatus

            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
