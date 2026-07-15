<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages de contact (F2.8.1).
 *
 * Questions envoyées depuis la page Contact publique, à traiter par l'équipe
 * (permission `traiter:demandes`). L'expéditeur n'a pas besoin de compte :
 * on stocke donc son nom et son e-mail directement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            // Statut de traitement : « nouveau » à la réception, « traite » ensuite.
            $table->string('status')->default('nouveau')->index();
            // Agent/admin ayant traité le message (null tant que non traité).
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
