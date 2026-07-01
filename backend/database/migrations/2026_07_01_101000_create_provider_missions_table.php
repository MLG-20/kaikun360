<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `provider_missions` — missions affectées à un prestataire (module Pro, B10.3).
 *
 * Kaikun affecte une mission (prestation) à un prestataire validé, avec un
 * montant et la commission plateforme figée (via CommissionCalculator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_missions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            // Client bénéficiaire (facultatif à ce stade).
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('amount_xof');
            $table->unsignedBigInteger('commission_xof')->default(0);

            $table->string('status')->default('affectee')->index();
            $table->timestamp('scheduled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_missions');
    }
};
