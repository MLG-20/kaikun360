<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille de l'espace CLIENT (F11.5) — masquage PERSONNEL, pas suppression.
 *
 * ⚠️ **Pourquoi `hidden_at` et surtout pas `SoftDeletes` ici.** Les objets d'un
 * client ne lui appartiennent pas en propre : une demande est la file de
 * travail d'un agent, une réservation est un CONTRAT entre lui, Kaikun et un
 * partenaire. Un `deleted_at` retirerait la ligne des requêtes de *tout le
 * monde* — back-office compris — et ferait disparaître du dossier de Kaikun une
 * pièce dont dépendent la comptabilité, les reversements au partenaire et le
 * règlement d'un éventuel litige. Le client rangerait sa liste en effaçant la
 * preuve.
 *
 * `hidden_at` dit donc exactement ce qu'il fait : « je ne veux plus voir cette
 * ligne dans MA liste ». Elle n'est honorée que dans les deux requêtes de
 * l'espace client (`GET /requests/my`, `GET /bookings/my`) ; le back-office,
 * les exports et les calculs d'argent continuent de tout voir, sans une seule
 * ligne de code à changer chez eux.
 *
 * ⚠️ **Conséquence à afficher, pas à taire** : rien n'étant supprimé, il n'y a
 * AUCUN compte à rebours sur ces lignes-là — contrairement aux annonces de
 * F11.4, qui partent pour de bon au bout de 30 jours. La corbeille montre les
 * deux familles, elle doit dire laquelle attend une purge et laquelle attend
 * seulement d'être rappelée.
 *
 * ⚠️ Les deux tables portent un `user_id` UNIQUE, qui est bien le client : pas
 * besoin d'une table de liaison « qui a masqué quoi ». Le jour où une ligne
 * aurait deux lecteurs distincts (elle n'en a pas), il faudrait la créer.
 */
return new class extends Migration
{
    /**
     * Les deux tables de l'espace client, avec le nom RÉEL de la table — la
     * demande générique vit dans `requests` et non `service_requests` (le
     * modèle s'appelle `ServiceRequest` seulement pour ne pas entrer en
     * conflit avec `Illuminate\Http\Request`).
     */
    private const TABLES = [
        'requests',  // demandes de service (écran « Mes demandes »)
        'bookings',  // réservations, tous univers confondus
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('hidden_at')->nullable()->after('created_at');
                // Indexée pour la même raison qu'en F11.4 : la colonne devient
                // un filtre de toutes les listes de l'espace client.
                $blueprint->index('hidden_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // L'index se retire AVANT la colonne : MySQL refuse de supprimer
                // une colonne encore portée par un index.
                $blueprint->dropIndex(['hidden_at']);
                $blueprint->dropColumn('hidden_at');
            });
        }
    }
};
