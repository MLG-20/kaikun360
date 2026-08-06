<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complète le justificatif d'un reversement de gestion locative (2026-08-06).
 *
 * ⚠️ **`owner_payouts.proof_path` existe depuis B4.4 et RIEN NE L'A JAMAIS
 * ÉCRITE.** Aucun endpoint ne téléversait de pièce : `markPayoutPaid` posait le
 * statut et la date, c'est tout. Pendant ce temps l'écran **Documents** du
 * back-office (F7.4.c) comptait les « justificatifs de reversement » — il
 * comptait donc invariablement **zéro**, et affichait le *chemin de stockage*
 * comme nom de fichier pour les lignes qui n'existaient pas.
 *
 * Le défaut a été relevé en construisant le registre F8.16.a, dont le
 * `POST .../pay` **exige** la pièce : il aurait été incohérent d'imposer la
 * preuve d'un côté et de la laisser facultative — et impossible — de l'autre.
 *
 * Deux colonnes seulement, alignées sur `partner_payouts` :
 *   - `proof_disk` — le disque réel, pour ne pas coder « local » en dur le jour
 *     où les pièces partiront sur S3 ;
 *   - `proof_original_name` — le nom que l'agent a téléversé. Sans lui, la seule
 *     chose à afficher est le chemin haché généré par `store()`, illisible, et
 *     le téléchargement rendrait un fichier sans nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->string('proof_disk')->nullable()->after('proof_path');
            $table->string('proof_original_name')->nullable()->after('proof_disk');
            // Qui a constaté le virement — même exigence de traçabilité que
            // `partner_payouts.paid_by` (CDC §12, actions financières sensibles).
            $table->foreignId('paid_by')->nullable()->after('proof_original_name')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['proof_disk', 'proof_original_name']);
        });
    }
};
