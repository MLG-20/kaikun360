<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `link_url`/`link_label` sur `news_articles` (F17, 2026-08-21).
 *
 * Permet une « carte » sans article rédigé : image + titre + lien de son
 * choix, sur la MÊME ligne que les vrais articles — voir NewsArticle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->string('link_url', 500)->nullable()->after('video_url');
            $table->string('link_label', 100)->nullable()->after('link_url');
        });
    }

    public function down(): void
    {
        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropColumn(['link_url', 'link_label']);
        });
    }
};
