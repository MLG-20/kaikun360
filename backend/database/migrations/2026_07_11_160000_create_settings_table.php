<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `settings` — paramétrage global de la plateforme (B13.4).
 *
 * Stockage clé-valeur typé : commissions, tarifs, coordonnées, contenus courts…
 * La valeur est conservée en texte et reconvertie selon `type` par le
 * SettingsRepository. Les clés connues ont des valeurs par défaut en code
 * (SettingsRepository::DEFAULTS) ; une ligne ici = une surcharge back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            // Type logique pour la reconversion : string|integer|float|boolean|json.
            $table->string('type')->default('string');
            // Regroupement d'affichage (commissions, tarifs, general…).
            $table->string('group')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
