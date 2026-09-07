<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `vehicle_showcase_cards` — section « Location de véhicules » de la
 * page d'accueil, qui remplace l'ancienne section « Protocole de confiance ».
 *
 * Demande du client (2026-09-07) : présenter son offre de location par
 * catégorie (berline, 4x4, minibus, ou toute autre qu'il invente), chaque
 * carte pilotée depuis le back-office — image, texte, lien. La catégorie est
 * un texte libre (comme `news_articles.category`) : pas de liste fermée, le
 * client range ses véhicules comme il l'entend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_showcase_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->string('description', 300)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('image_path');
            $table->string('link_url', 500);
            $table->string('link_label', 100)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_showcase_cards');
    }
};
