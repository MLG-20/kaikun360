<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revue de sécurité (2026-08) : la vérification d'un code à 6 chiffres
 * n'était bornée que par le throttle IP de la route (10/min) — un attaquant
 * distribuant ses tentatives sur plusieurs IP pouvait deviner un code
 * (10⁶ possibilités) dans sa fenêtre de validité de 15 minutes, sans jamais
 * déclencher de verrou. `failed_attempts` compte les essais ratés PAR CODE ;
 * `VerificationService::verify()` invalide le code au-delà du seuil, quel que
 * soit le nombre d'IP utilisées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('verification_codes', function (Blueprint $table) {
            $table->dropColumn('failed_attempts');
        });
    }
};
