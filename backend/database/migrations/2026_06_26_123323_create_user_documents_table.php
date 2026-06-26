<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `user_documents` — pièces justificatives (KYC) d'un utilisateur.
 *
 * Les fichiers eux-mêmes sont stockés sur un disque PRIVÉ (storage/app/private),
 * jamais en public. La table ne conserve que les métadonnées et le chemin ;
 * l'accès au fichier se fait via une URL signée temporaire (cf. B1.5 / B15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nature de la pièce (cf. enum DocumentType).
            $table->string('type');

            // Emplacement du fichier : disque + chemin relatif.
            $table->string('disk')->default('local');
            $table->string('path');

            // Métadonnées du fichier d'origine.
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0); // taille en octets

            // Statut de validation par un agent (cf. B13). Par défaut : en attente.
            $table->string('status')->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
