<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `verification_codes` — codes à usage unique et à durée limitée.
 *
 * Sert à la fois à la vérification de compte (e-mail/téléphone) et à la
 * réinitialisation de mot de passe (cf. phase B1.4). Le code n'est JAMAIS
 * stocké en clair : on n'enregistre que son hash (comme un mot de passe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Finalité du code : 'account_verification' ou 'password_reset'.
            $table->string('purpose')->index();

            // Canal concerné : 'email' ou 'phone'.
            $table->string('channel');

            // Hash du code (jamais le code en clair).
            $table->string('code_hash');

            // Expiration (typiquement +15 min) et date de consommation.
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            // On retrouve vite le dernier code valide d'un utilisateur pour un usage donné.
            $table->index(['user_id', 'purpose', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
