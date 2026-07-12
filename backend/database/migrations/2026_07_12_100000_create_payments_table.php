<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `payments` — transactions de paiement (B14, PayTech).
 *
 * Une réservation peut donner lieu à plusieurs paiements (acompte, solde,
 * remboursement). Le statut suit l'enum `PaymentStatus`, aligné sur les états
 * PayTech. `provider_reference` est l'identifiant renvoyé par le PSP ;
 * `signature_verified` trace qu'une notification a été authentifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // référence interne Kaikun
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('provider')->default('paytech');
            $table->unsignedBigInteger('amount_xof');
            $table->unsignedBigInteger('commission_xof')->default(0);
            $table->string('status')->default('initie');
            $table->string('mode')->nullable();             // moyen de paiement (carte, mobile money…)
            $table->string('provider_reference')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
