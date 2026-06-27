<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `owner_payouts` — reversements au propriétaire (module Manage, B4.4).
 *
 * Représente le versement, par Kaikun au propriétaire, du produit de la gestion
 * locative (loyers encaissés moins commission/dépenses) pour une période donnée.
 * Distinct des payouts prestataires/PSP (ledger, phases B11/B14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('mandate_id')->constrained('management_mandates')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Période couverte (ex. "Juin 2026") et montant reversé.
            $table->string('period_label')->nullable();
            $table->unsignedBigInteger('amount_xof');

            // Statut (cf. enum OwnerPayoutStatus) + date effective + justificatif.
            $table->string('status')->default('en_attente')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('proof_path')->nullable(); // justificatif sur disque privé

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payouts');
    }
};
