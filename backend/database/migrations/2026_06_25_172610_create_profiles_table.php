<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `profiles` — informations métier d'un utilisateur.
 *
 * Relation 1–1 avec `users` : chaque utilisateur possède exactement un profil,
 * qui porte sa "casquette" (client, propriétaire, prestataire, entreprise,
 * diaspora), son état de vérification et ses préférences.
 *
 * Les pièces justificatives (documents KYC) seront gérées dans une table dédiée
 * en phase B1.5 (POST /users/me/documents), pas ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            // Lien 1–1 vers l'utilisateur. unique() garantit un seul profil par user.
            // cascadeOnDelete : supprimer l'utilisateur supprime aussi son profil.
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Type de profil (cf. enum App\Modules\Core\Enums\ProfileType).
            $table->string('type')->index();

            // État de vérification du profil (KYC) : non_verifie, en_cours, verifie, rejete.
            // Par défaut "non_verifie" à la création.
            $table->string('verification_status')->default('non_verifie')->index();

            // Préférences libres de l'utilisateur (langue, notifications, etc.),
            // stockées en JSON pour rester flexibles sans multiplier les colonnes.
            $table->json('preferences')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
