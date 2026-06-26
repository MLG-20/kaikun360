<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `rents` — échéances de loyer d'un mandat de gestion locative (B4.2).
 *
 * Chaque ligne représente un loyer à une échéance donnée, avec son statut de
 * paiement. Le locataire peut être un utilisateur enregistré (`tenant_id`) ou
 * simplement nommé (`tenant_name`) s'il n'a pas de compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rents', function (Blueprint $table) {
            $table->id();

            // Mandat (donc bien + propriétaire) auquel se rattache le loyer.
            $table->foreignId('mandate_id')->constrained('management_mandates')->cascadeOnDelete();

            // Locataire : compte utilisateur si disponible, sinon nom libre.
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tenant_name')->nullable();

            // Période concernée (ex. "Juin 2026") et échéance.
            $table->string('period_label')->nullable();
            $table->date('due_date');

            // Montant et statut de paiement (cf. enum RentStatus).
            $table->unsignedBigInteger('amount_xof');
            $table->string('status')->default('impaye')->index();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Recherche fréquente : loyers d'un mandat par échéance.
            $table->index(['mandate_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rents');
    }
};
