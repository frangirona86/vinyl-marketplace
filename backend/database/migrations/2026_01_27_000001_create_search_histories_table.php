<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 255);
            $table->string('type', 50)->default('general'); // general, artist, genre, vinyl
            $table->integer('results_count')->default(0);
            $table->json('filters')->nullable(); // Any filters applied
            $table->json('selected_result')->nullable(); // What the user clicked on
            $table->timestamps();

            // Index for efficient session-based queries
            $table->index(['session_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Create full-text search index only for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                CREATE INDEX IF NOT EXISTS discogs_analyses_search_idx 
                ON discogs_analyses 
                USING gin(to_tsvector('english', 
                    COALESCE(title, '') || ' ' || 
                    COALESCE(artist_name, '') || ' ' || 
                    COALESCE(label, '') || ' ' ||
                    COALESCE(country, '')
                ))
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS discogs_analyses_search_idx");
        }
        Schema::dropIfExists('search_histories');
    }
};
