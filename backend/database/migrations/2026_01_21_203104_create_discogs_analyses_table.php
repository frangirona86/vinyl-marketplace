<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("discogs_analyses", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("discogs_id")->index();
            $table->string("title");
            $table->string("artist_name")->nullable();
            $table->integer("year")->nullable();
            $table->string("country")->nullable();
            $table->string("label")->nullable();
            $table->string("catalog_number")->nullable();
            $table->json("genres")->nullable();
            $table->string("format")->nullable();
            
            // Community stats
            $table->integer("have")->default(0);
            $table->integer("want")->default(0);
            $table->decimal("rating_average", 3, 2)->default(0);
            $table->integer("rating_count")->default(0);
            
            // Marketplace stats
            $table->integer("num_for_sale")->default(0);
            $table->decimal("lowest_price", 10, 2)->nullable();
            $table->string("lowest_price_currency", 3)->nullable();
            $table->json("price_suggestions")->nullable();
            
            // Calculated metrics
            $table->decimal("demand_ratio", 8, 4)->default(0);
            $table->boolean("is_rare")->default(false);
            $table->boolean("is_in_demand")->default(false);
            
            // Full data snapshot
            $table->json("raw_data")->nullable();
            
            // Tracking
            $table->string("cover_image")->nullable();
            $table->text("notes")->nullable();
            $table->boolean("is_watchlist")->default(false);
            $table->timestamp("fetched_at")->nullable();
            $table->timestamps();
            
            // Index for common queries
            $table->index(["have", "want"]);
            $table->index("demand_ratio");
            $table->index("is_watchlist");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("discogs_analyses");
    }
};
