<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `providers.category` référence désormais `provider_categories.key`, au lieu
 * d'être une simple chaîne dont la validité n'était garantie que côté enum PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->foreign('category')->references('key')->on('provider_categories');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['category']);
        });
    }
};
