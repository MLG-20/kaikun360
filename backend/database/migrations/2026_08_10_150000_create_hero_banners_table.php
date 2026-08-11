<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `hero_banners` — bandeaux d'en-tête des pages publiques (F12).
 *
 * Une ligne = une SURCHARGE saisie au back-office pour une clé de bandeau
 * connue (cf. App\Support\Heroes\HeroCatalog). Tant qu'aucune ligne n'existe,
 * les pages affichent exactement ce qu'elles affichaient avant : leur dégradé
 * de marque et les textes écrits dans leurs gabarits. Rien n'est donc « vide
 * par défaut » — c'est le principe déjà retenu pour les réglages (B13.4).
 *
 * Les colonnes de texte sont NULLABLES et le restent : `null` ne veut pas dire
 * « titre vide », il veut dire « ne rien surcharger, garder le texte d'origine
 * de la page ». Une chaîne vide et `null` ne sont donc PAS équivalents ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_banners', function (Blueprint $table) {
            $table->id();
            // Clé de bandeau (`immobilier`, `recherche.nuitees`, `defaut`…).
            // Volontairement une chaîne libre en base plutôt qu'un enum SQL :
            // le catalogue des clés vit dans le code et bouge avec les pages,
            // une migration à chaque nouvelle page serait absurde.
            $table->string('key')->unique();
            // Image de fond compressée (disque `public`), ou null = pas d'image
            // propre → le bandeau hérite de celle de sa page parente.
            $table->string('image_path')->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->text('lead')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_banners');
    }
};
