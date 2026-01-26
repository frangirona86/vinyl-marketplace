<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discogs_analyses', function (Blueprint $table) {
            $table->json('youtube_tracks')->nullable()->after('ai_analysis');
            $table->boolean('has_youtube')->default(false)->after('youtube_tracks');
            $table->timestamp('youtube_fetched_at')->nullable()->after('has_youtube');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discogs_analyses', function (Blueprint $table) {
            $table->dropColumn(['youtube_tracks', 'has_youtube', 'youtube_fetched_at']);
        });
    }
};
