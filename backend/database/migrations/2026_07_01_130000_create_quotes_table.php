<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `quotes` — devis génériques rattachés à une demande (couche transversale, B11.3).
 *
 * Un agent propose un devis (montant, détails, validité) en réponse à une
 * demande ; le client l'accepte ou le refuse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

            $table->unsignedBigInteger('amount_xof');
            // Détails structurés (lignes, conditions…).
            $table->json('details')->nullable();
            // Validité du devis.
            $table->date('valid_until')->nullable();

            $table->string('status')->default('brouillon')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
