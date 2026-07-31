<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la photo de profil (ou le LOGO, pour un profil « entreprise ») au
 * profil utilisateur.
 *
 * ⚠️ Contrairement aux pièces KYC (`user_documents`, disque PRIVÉ + URL signée),
 * un avatar est fait pour être VU : il s'affiche dans l'en-tête de l'espace, en
 * regard d'un devis, dans une fiche prestataire. Il est donc stocké sur le
 * disque **public** (`storage/app/public`, exposé par `php artisan storage:link`)
 * et servi par une URL stable, sans signature ni passage par PHP.
 *
 * Une seule colonne pour les deux usages : le sens de l'image se déduit du
 * `type` du profil (entreprise → logo, sinon → photo). Dupliquer la colonne
 * aurait imposé, à chaque lecture, de choisir laquelle regarder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Chemin relatif au disque public (ex. « avatars/12/xyz.jpg »).
            // Nullable : l'immense majorité des comptes n'a pas encore d'image,
            // et l'en-tête retombe alors sur l'initiale du nom.
            $table->string('avatar_path')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
