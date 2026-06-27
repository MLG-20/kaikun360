<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `reports` — rapports de suivi (photos/vidéo) — module Build, B5.2.
 *
 * POLYMORPHE (`reportable_type`/`reportable_id`) : un rapport peut être attaché
 * à une demande de construction (`ConstructionRequest`) comme, plus tard, à un
 * projet diaspora (phase B8). Les photos sont stockées sous forme de liste de
 * chemins (disque privé), la vidéo via une URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Cible polymorphe (construction, diaspora…).
            $table->morphs('reportable');

            // Auteur du rapport (agent/suivi de chantier), facultatif.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Type de contenu (cf. enum ReportType).
            $table->string('type')->default('photo');
            // Liste de chemins de photos (disque privé) + URL vidéo éventuelle.
            $table->json('photos')->nullable();
            $table->string('video_url')->nullable();

            $table->text('comment')->nullable();
            // Date du rapport (jour du constat de chantier).
            $table->date('reported_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
