<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `expenses` — dépenses liées à un bien (maintenance, réparations) — B4.3.
 *
 * Une dépense peut être rattachée à un incident (réparation suite à un signalement).
 * Le justificatif éventuel est un fichier sur disque privé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            // Incident à l'origine de la dépense (facultatif).
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();

            $table->string('label');
            // Catégorie (cf. enum ExpenseCategory).
            $table->string('category')->default('autre')->index();

            $table->unsignedBigInteger('amount_xof');
            $table->date('spent_at');

            // Chemin du justificatif (disque privé), facultatif.
            $table->string('receipt_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
