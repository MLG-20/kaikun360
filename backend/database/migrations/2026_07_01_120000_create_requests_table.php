<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `requests` — demandes clients génériques (couche transversale, B11.1).
 *
 * Point d'entrée unifié : un utilisateur exprime un besoin (quel que soit
 * l'univers), qui suit une machine à états stricte (recu → … → cloture) et peut
 * donner lieu à des devis (`quotes`, B11.3). Le modèle Eloquent est
 * `App\Models\ServiceRequest` (nom « Request » réservé à Illuminate\Http).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Univers concerné (cf. enum ServiceType) et contenu de la demande.
            $table->string('service_type')->index();
            $table->text('message');
            $table->unsignedBigInteger('budget_xof')->nullable();
            $table->string('city')->nullable();

            // Machine à états (cf. enum RequestStatus) et priorité.
            $table->string('status')->default('recu')->index();
            $table->string('priority')->default('normale')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
