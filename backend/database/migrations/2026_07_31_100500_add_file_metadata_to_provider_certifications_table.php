<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complète `provider_certifications` pour porter un VRAI justificatif.
 *
 * La colonne `file_path` existait depuis B6 mais n'a jamais été renseignée :
 * aucun contrôleur n'acceptait de fichier, « Mes services » ne faisait que
 * *déclarer* une certification (nom + organisme). Conséquence visible au
 * back-office (Comptes → Documents) : la colonne fichier restait vide pour
 * toutes les certifications, alors que le CDC §6 les compte parmi les « pièces
 * prestataires » à contrôler.
 *
 * On aligne donc la table sur `user_documents` (le patron KYC déjà éprouvé) :
 * disque + nom d'origine + type MIME + taille. Le fichier va sur le disque
 * PRIVÉ et ne se lit que par URL signée — un diplôme ou un agrément porte des
 * données personnelles, il n'a rien à faire sur le disque public (à la
 * différence d'un avatar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_certifications', function (Blueprint $table) {
            // Disque de stockage, comme pour les pièces KYC. Défaut « local »
            // = disque privé (cf. config/filesystems.php).
            $table->string('disk')->default('local')->after('file_path');

            // Nom du fichier tel que déposé par le prestataire. Indispensable
            // au back-office : `file_path` contient un nom aléatoire généré par
            // Laravel, illisible pour l'agent qui contrôle la pièce.
            $table->string('original_name')->nullable()->after('disk');

            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('size')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('provider_certifications', function (Blueprint $table) {
            $table->dropColumn(['disk', 'original_name', 'mime_type', 'size']);
        });
    }
};
