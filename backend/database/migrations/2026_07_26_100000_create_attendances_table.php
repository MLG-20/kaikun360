<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `attendances` — pointeuse de l'équipe back-office (F7.1.c).
 *
 * Une ligne = une **session de présence** d'un membre de l'équipe : un pointage
 * d'entrée (`started_at`) et, quand il quitte, un pointage de sortie
 * (`ended_at`). Une session ouverte (sans sortie) matérialise « en poste
 * actuellement ». La feuille de présence mensuelle est calculée par agrégation
 * de ces sessions (voir Services\AttendanceSheet).
 *
 * Règle métier (garantie côté application) : au plus **une** session ouverte par
 * personne à la fois — on ne peut pas pointer une entrée sans avoir soldé la
 * précédente sortie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            // L'employé pointé. Si le compte est supprimé, ses pointages le sont aussi.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at');           // pointage d'entrée
            $table->timestamp('ended_at')->nullable();  // pointage de sortie (null = en poste)

            $table->timestamps();

            // Accès fréquent : « les sessions d'un employé, les plus récentes d'abord »
            // et « la session ouverte d'un employé ».
            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
