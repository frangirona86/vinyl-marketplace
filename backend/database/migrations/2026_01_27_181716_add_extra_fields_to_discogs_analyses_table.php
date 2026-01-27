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
            $table->json('format_descriptions')->nullable()->after('format');
            $table->integer('artist_id')->nullable()->after('artist_name');
            $table->string('artist_thumbnail')->nullable()->after('artist_id');
            // notes field might already exist, only add if not
            if (!Schema::hasColumn('discogs_analyses', 'notes')) {
                $table->text('notes')->nullable()->after('cover_image');
            }
            $table->string('data_quality')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discogs_analyses', function (Blueprint $table) {
            $table->dropColumn(['format_descriptions', 'artist_id', 'artist_thumbnail', 'data_quality']);
        });
    }
};
