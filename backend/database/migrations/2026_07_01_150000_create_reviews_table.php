<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `reviews` — avis polymorphes (couche transversale, B12.2).
 *
 * Un utilisateur note (1–5) et commente une ressource qu'il a réellement
 * consommée (`reviewable` : véhicule, expérience, nuitée…). L'avis passe par une
 * modération (`status`) avant d'être publié et compté dans la note agrégée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Cible polymorphe (Vehicle, TourismExperience, Stay…).
            $table->morphs('reviewable');

            $table->unsignedTinyInteger('rating');            // note 1–5
            $table->text('comment')->nullable();

            $table->string('status')->default('en_attente')->index();  // enum ReviewStatus
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();

            $table->timestamps();

            // Un seul avis par utilisateur et par ressource.
            $table->unique(['user_id', 'reviewable_type', 'reviewable_id'], 'reviews_user_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
