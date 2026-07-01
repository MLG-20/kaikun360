<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `providers` — profil prestataire marketplace (module Pro, B10.1).
 *
 * Relation 1–1 avec un utilisateur. Porte la catégorie, le statut de validation,
 * la charte qualité (avertissements/sanction) et la note agrégée (remplie par le
 * module Reviews, B12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name');
            $table->string('category')->index();
            $table->text('bio')->nullable();

            // Statut de validation (cf. enum ProviderStatus) + traçabilité.
            $table->string('status')->default('en_attente')->index();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();

            // Charte qualité : avertissements et note de sanction.
            $table->unsignedInteger('warnings_count')->default(0);
            $table->text('sanction_note')->nullable();

            // Note agrégée (remplie par le module Reviews, B12).
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
