<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `home_hero_slides` — galerie de photos du héros de l'accueil (F15.1).
 *
 * Distinct du mécanisme F12 (`hero_banners`, une image par page) : le héros
 * de l'accueil est le seul à pouvoir porter PLUSIEURS photos, qui défilent en
 * fond, ou une courte vidéo à la place (voir `Settings` : `home.hero_video_path`
 * / `home.hero_video_url`, pas de table dédiée — un singleton ne mérite pas sa
 * propre table). Une ligne = une photo du diaporama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            // Ordre d'affichage dans le diaporama.
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_hero_slides');
    }
};
