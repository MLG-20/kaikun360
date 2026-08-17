<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `news_articles` — section « Actualités Kaikun » de la page d'accueil.
 *
 * Demande du client après avoir vu son propre prototype (2026-08-13) : une
 * section actualités/vidéo pilotable, sans redéploiement. Une ligne = un
 * article ; l'image de couverture est obligatoire (elle porte la carte),
 * texte et vidéo sont facultatifs.
 *
 * La vidéo a DEUX formes possibles, jamais les deux à la fois côté affichage :
 * un FICHIER déposé par l'équipe (déjà compressé de son côté — aucun
 * transcodage n'est fait ici, voir NewsArticle) OU une URL d'embed
 * (YouTube/Vimeo). Le fichier est prioritaire quand les deux sont saisis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('excerpt', 300)->nullable();
            $table->text('body')->nullable();
            $table->string('image_path');
            // Vidéo déposée (disque public, comme l'image — visible en
            // permanence, ne peut pas dépendre d'une URL signée qui expire).
            $table->string('video_path')->nullable();
            $table->string('video_url')->nullable();
            $table->boolean('is_published')->default(false);
            // Ordre d'affichage manuel (l'équipe peut vouloir épingler un
            // article plutôt que trier strictement par date de publication).
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
