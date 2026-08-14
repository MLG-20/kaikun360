<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liste d'attente avant ouverture officielle (2026-08-14).
 *
 * Le client garde une page « Bientôt disponible » sur le domaine public pour
 * constituer une masse critique de propriétaires/prestataires/clients avant de
 * basculer le DNS vers la vraie plateforme. Cette table porte NOTRE version,
 * détachée de la sienne, élargie à 5 catégories (Propriétaire, Prestataire,
 * Client, Team building, Diaspora). L'inscrit n'a pas de compte : on stocke
 * donc son nom/téléphone directement, comme `contact_messages`.
 *
 * `details` porte les champs spécifiques à la catégorie choisie (même
 * convention que `team_building_requests.needs`) : la forme du prospect change
 * selon ce qu'il vient chercher, un schéma unique de colonnes serait en grande
 * partie vide pour chacun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            // proprietaire | prestataire | client | team_building | diaspora
            $table->string('category')->index();
            $table->json('details')->nullable();
            $table->text('precisions')->nullable();
            // Statut de traitement : « nouveau » à la réception, « traite » ensuite
            // (même cycle que `contact_messages` — pas de machine à états stricte).
            $table->string('status')->default('nouveau')->index();
            // Agent/admin ayant traité l'inscription (null tant que non traitée).
            // Réservé au futur écran back-office, non construit dans ce lot.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
