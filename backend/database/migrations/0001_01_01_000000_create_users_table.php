<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // nom complet de l'utilisateur

            // Identifiants de connexion : l'utilisateur peut se connecter par email
            // OU par téléphone (cf. cahier des charges B1). Les deux sont uniques.
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();       // téléphone (format international conseillé)

            // Horodatages de vérification des canaux (email + SMS).
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            $table->string('password');

            // Localisation principale (ville sénégalaise) — sert aux filtres/affichage.
            $table->string('city')->nullable();

            // Statut du compte (cf. enum App\Modules\Core\Enums\UserStatus).
            // Par défaut "en_attente_verification" : le compte n'est pleinement actif
            // qu'après vérification email/téléphone (exigence de sécurité B15).
            $table->string('status')->default('en_attente_verification')->index();

            // NB : le RÔLE n'est volontairement PAS une colonne ici. Les rôles sont
            // gérés par Spatie Permission (table model_has_roles), source de vérité
            // unique, ce qui évite toute incohérence et permet plusieurs rôles.

            $table->rememberToken();
            $table->timestamps();                                // created_at = date de création
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
