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
        Schema::table('records', function (Blueprint $table) {
            // Add the artist_id column to the records table
            $table->foreignId('artist_id')
                  ->nullable() // Nullable temporarily for migration
                  ->after('title')
                  ->constrained('artists')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // Drop the foreign key and the column
            $table->dropForeign(['artist_id']);
            $table->dropColumn('artist_id');
        });
    }
};
