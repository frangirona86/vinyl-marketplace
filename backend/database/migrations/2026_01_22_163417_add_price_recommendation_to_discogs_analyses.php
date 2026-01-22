<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->decimal("recommended_price_min", 10, 2)->nullable()->after("lowest_price_currency");
            $table->decimal("recommended_price_max", 10, 2)->nullable()->after("recommended_price_min");
            $table->integer("ai_score")->nullable()->after("demand_ratio");
            $table->string("ai_recommendation", 50)->nullable()->after("ai_score");
            $table->text("ai_analysis")->nullable()->after("ai_recommendation");
            $table->integer("previous_have")->nullable()->after("raw_data");
            $table->integer("previous_want")->nullable()->after("previous_have");
            $table->decimal("previous_lowest_price", 10, 2)->nullable()->after("previous_want");
            $table->timestamp("last_refreshed_at")->nullable()->after("fetched_at");
        });
    }

    public function down(): void
    {
        Schema::table("discogs_analyses", function (Blueprint $table) {
            $table->dropColumn([
                "recommended_price_min",
                "recommended_price_max",
                "ai_score",
                "ai_recommendation",
                "ai_analysis",
                "previous_have",
                "previous_want",
                "previous_lowest_price",
                "last_refreshed_at",
            ]);
        });
    }
};
