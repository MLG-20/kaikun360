<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table des notifications "base de données" de Laravel (canal `database`).
 *
 * C'est le socle de l'écran « Mes notifications » de l'espace client (F3.6) :
 * chaque notification envoyée à un utilisateur via le canal `database` y crée
 * une ligne. Le contenu applicatif (titre, message, lien) est sérialisé dans
 * la colonne JSON `data` (voir la méthode `toArray()` de chaque Notification).
 *
 * Schéma standard Laravel (généré par `php artisan notifications:table`) :
 *   - id          : UUID (et non auto-incrément) — attribué par le framework.
 *   - type        : classe PHP de la notification (ex. RequestStatusChanged…).
 *   - notifiable  : relation polymorphe (morphs) vers le destinataire (User).
 *   - data        : charge utile JSON, lue par NotificationResource.
 *   - read_at     : horodatage de lecture (null tant que non lue).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
